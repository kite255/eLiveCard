<?php

namespace App\Http\Controllers;

use App\Models\Invitee;
use App\Models\InviteeUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class InviteePageController extends Controller
{
    public function show(Request $request, string $shortCode)
    {
        $invitee = Invitee::query()
            ->with(['event', 'cardType'])
            ->where('short_code', $shortCode)
            ->firstOrFail();

        abort_unless(
            $this->canOpenInvitation($invitee),
            403,
            'This invitation is not active.'
        );

        $this->trackInviteeOpen($invitee, $request);

        $invitee->refresh();

        $event = $invitee->event;

        $generatedCardUrl = $this->generatedCardUrl($invitee);
        $programItems = $this->programItems($event);
        $organizerPhone = $this->organizerPhone($event);

        $whatsAppOrganizerUrl = $organizerPhone
            ? 'https://wa.me/' . preg_replace('/\D+/', '', $organizerPhone)
            : null;

        $coverImageUrl = $this->coverImageUrl($event);
        $allowedGuests = $this->allowedGuests($invitee);

        return view('invitees.show', [
            'invitee' => $invitee,
            'event' => $event,
            'generatedCardUrl' => $generatedCardUrl,
            'programItems' => $programItems,
            'organizerPhone' => $organizerPhone,
            'whatsAppOrganizerUrl' => $whatsAppOrganizerUrl,
            'coverImageUrl' => $coverImageUrl,
            'allowedGuests' => $allowedGuests,
            'approvedWishes' => $this->approvedWishes($event?->id),
            'approvedPhotos' => $this->approvedPhotos($event?->id),
        ]);
    }

    public function rsvp(Request $request, string $shortCode)
    {
        $invitee = Invitee::query()
            ->with(['event', 'cardType'])
            ->where('short_code', $shortCode)
            ->firstOrFail();

        abort_unless(
            $this->canOpenInvitation($invitee),
            403,
            'This invitation is not active.'
        );

        $allowedGuests = $this->allowedGuests($invitee);

        $request->validate([
            'status' => ['required', Rule::in(['attending', 'not_attending', 'pending'])],
            'confirmed_guests' => [
                Rule::requiredIf(fn () => $request->status === 'attending'),
                'nullable',
                'integer',
                'min:1',
                'max:' . $allowedGuests,
            ],
        ], [
            'confirmed_guests.required' => 'Please select how many guests will attend.',
            'confirmed_guests.min' => 'Confirmed guests must be at least 1.',
            'confirmed_guests.max' => 'Confirmed guests cannot exceed the allowed guest limit.',
        ]);

        $confirmedGuests = match ($request->status) {
            'attending' => (int) $request->confirmed_guests,
            'not_attending' => 0,
            default => 0,
        };

        $invitee->update([
            'rsvp_status' => $request->status,
            'confirmed_guests' => $confirmedGuests,
            'rsvp_confirmed_at' => now(),
        ]);

        $message = match ($request->status) {
            'attending' => 'Thank you. Your attendance has been confirmed for ' . $confirmedGuests . ' guest(s).',
            'not_attending' => 'Thank you. Your response has been recorded successfully.',
            default => 'Thank you. Your RSVP status has been updated successfully.',
        };

        return redirect()
            ->route('invitee.page', $invitee->short_code)
            ->with('success', $message);
    }

    public function storeWish(Request $request, string $shortCode)
    {
        $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'message' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $invitee = Invitee::query()
            ->with('event')
            ->where('short_code', $shortCode)
            ->firstOrFail();

        abort_unless(
            $this->canOpenInvitation($invitee),
            403,
            'This invitation is not active.'
        );

        if (! Schema::hasTable('invitee_uploads')) {
            return redirect()
                ->route('invitee.page', $invitee->short_code)
                ->with('info', 'Your wish was received, but the approval table is not enabled yet.');
        }

        $this->createInviteeUpload([
            'event_id' => $invitee->event_id,
            'invitee_id' => $invitee->id,
            'type' => 'wish',
            'name' => $request->filled('name') ? $request->name : $invitee->name,
            'message' => $request->message,
            'file_path' => null,
            'status' => 'pending',
            'approved_by' => null,
            'approved_at' => null,
            'rejected_at' => null,
            'admin_note' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('invitee.page', $invitee->short_code)
            ->with('success', 'Thank you. Your wishes have been submitted for approval.');
    }

    public function storePhoto(Request $request, string $shortCode)
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'caption' => ['nullable', 'string', 'max:255'],
        ], [
            'photo.required' => 'Please select a photo to upload.',
            'photo.image' => 'The uploaded file must be an image.',
            'photo.max' => 'The photo must not be larger than 5MB.',
        ]);

        $invitee = Invitee::query()
            ->with('event')
            ->where('short_code', $shortCode)
            ->firstOrFail();

        abort_unless(
            $this->canOpenInvitation($invitee),
            403,
            'This invitation is not active.'
        );

        if (! Schema::hasTable('invitee_uploads')) {
            return redirect()
                ->route('invitee.page', $invitee->short_code)
                ->with('info', 'Photo upload is not enabled yet.');
        }

        $path = $request->file('photo')->store(
            'events/' . $invitee->event_id . '/invitee-uploads',
            'public'
        );

        $this->createInviteeUpload([
            'event_id' => $invitee->event_id,
            'invitee_id' => $invitee->id,
            'type' => 'photo',
            'name' => $invitee->name,
            'message' => $request->caption,
            'file_path' => $path,
            'status' => 'pending',
            'approved_by' => null,
            'approved_at' => null,
            'rejected_at' => null,
            'admin_note' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('invitee.page', $invitee->short_code)
            ->with('success', 'Thank you. Your photo has been submitted for approval.');
    }

    protected function createInviteeUpload(array $data): void
    {
        if (class_exists(InviteeUpload::class)) {
            InviteeUpload::query()->create($data);
            return;
        }

        DB::table('invitee_uploads')->insert(
            collect($data)
                ->only(Schema::getColumnListing('invitee_uploads'))
                ->all()
        );
    }

    protected function approvedWishes(?int $eventId)
    {
        if (! $eventId || ! Schema::hasTable('invitee_uploads')) {
            return collect();
        }

        return DB::table('invitee_uploads')
            ->leftJoin('invitees', 'invitee_uploads.invitee_id', '=', 'invitees.id')
            ->where('invitee_uploads.event_id', $eventId)
            ->where('invitee_uploads.type', 'wish')
            ->where('invitee_uploads.status', 'approved')
            ->latest('invitee_uploads.approved_at')
            ->latest('invitee_uploads.created_at')
            ->select([
                'invitee_uploads.*',
                DB::raw('COALESCE(invitee_uploads.name, invitees.name) as display_name'),
            ])
            ->get();
    }

    protected function approvedPhotos(?int $eventId)
    {
        if (! $eventId || ! Schema::hasTable('invitee_uploads')) {
            return collect();
        }

        return DB::table('invitee_uploads')
            ->leftJoin('invitees', 'invitee_uploads.invitee_id', '=', 'invitees.id')
            ->where('invitee_uploads.event_id', $eventId)
            ->where('invitee_uploads.type', 'photo')
            ->where('invitee_uploads.status', 'approved')
            ->whereNotNull('invitee_uploads.file_path')
            ->latest('invitee_uploads.approved_at')
            ->latest('invitee_uploads.created_at')
            ->select([
                'invitee_uploads.*',
                DB::raw('COALESCE(invitee_uploads.name, invitees.name) as display_name'),
            ])
            ->get()
            ->map(function ($photo) {
                $photo->file_url = Storage::disk('public')->url($photo->file_path);
                return $photo;
            });
    }

    protected function allowedGuests(Invitee $invitee): int
    {
        if (isset($invitee->final_allowed_guests) && (int) $invitee->final_allowed_guests > 0) {
            return (int) $invitee->final_allowed_guests;
        }

        if ((int) $invitee->allowed_guests > 0) {
            return (int) $invitee->allowed_guests;
        }

        if ($invitee->cardType && (int) ($invitee->cardType->allowed_guests ?? 0) > 0) {
            return (int) $invitee->cardType->allowed_guests;
        }

        if ($invitee->cardType && (int) ($invitee->cardType->allowed_people ?? 0) > 0) {
            return (int) $invitee->cardType->allowed_people;
        }

        return 1;
    }

    protected function trackInviteeOpen(Invitee $invitee, Request $request): void
    {
        if (method_exists($invitee, 'recordInvitationOpen')) {
            $invitee->recordInvitationOpen(
                $request->ip(),
                $request->userAgent()
            );

            return;
        }

        if (! Schema::hasColumn('invitees', 'open_count')) {
            return;
        }

        $invitee->forceFill([
            'first_opened_at' => $invitee->first_opened_at ?? now(),
            'last_opened_at' => now(),
            'open_count' => ((int) $invitee->open_count) + 1,
            'last_open_ip' => $request->ip(),
            'last_open_user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ])->save();
    }

    protected function canOpenInvitation(Invitee $invitee): bool
    {
        $allowedStatuses = [
            'active',
            'sent',
            'generated',
        ];

        if (defined(Invitee::class . '::CARD_STATUS_ACTIVE')) {
            $allowedStatuses[] = Invitee::CARD_STATUS_ACTIVE;
        }

        if (defined(Invitee::class . '::CARD_STATUS_SENT')) {
            $allowedStatuses[] = Invitee::CARD_STATUS_SENT;
        }

        if (defined(Invitee::class . '::CARD_STATUS_GENERATED')) {
            $allowedStatuses[] = Invitee::CARD_STATUS_GENERATED;
        }

        return in_array($invitee->card_status, array_unique($allowedStatuses), true);
    }

    protected function generatedCardUrl(Invitee $invitee): ?string
    {
        if (isset($invitee->generated_card_url) && filled($invitee->generated_card_url)) {
            return $invitee->generated_card_url;
        }

        if (filled($invitee->generated_card_path) && Storage::disk('public')->exists($invitee->generated_card_path)) {
            return Storage::disk('public')->url($invitee->generated_card_path);
        }

        if (filled($invitee->card_path) && Storage::disk('public')->exists($invitee->card_path)) {
            return Storage::disk('public')->url($invitee->card_path);
        }

        if (method_exists($invitee, 'generatedCards')) {
            $generatedCard = $invitee->generatedCards()
                ->whereNotNull('file_path')
                ->latest()
                ->first();

            if ($generatedCard && Storage::disk('public')->exists($generatedCard->file_path)) {
                return Storage::disk('public')->url($generatedCard->file_path);
            }
        }

        return null;
    }

    protected function coverImageUrl($event): ?string
    {
        if (! $event) {
            return null;
        }

        if (isset($event->cover_image_url) && filled($event->cover_image_url)) {
            return $event->cover_image_url;
        }

        if (filled($event->cover_image) && Storage::disk('public')->exists($event->cover_image)) {
            return Storage::disk('public')->url($event->cover_image);
        }

        return null;
    }

    protected function programItems($event): array
    {
        if (! $event) {
            return [];
        }

        if (isset($event->program_items) && is_array($event->program_items)) {
            return $event->program_items;
        }

        if (isset($event->program) && filled($event->program)) {
            return collect(preg_split('/\r\n|\r|\n/', $event->program))
                ->map(fn ($item) => trim($item))
                ->filter()
                ->values()
                ->all();
        }

        return [
            'Guest Arrival',
            'Opening Prayer',
            'Welcome Remarks',
            'Main Ceremony',
            'Photos',
            'Closing',
        ];
    }

    protected function organizerPhone($event): ?string
    {
        if (! $event) {
            return null;
        }

        if (isset($event->effective_organizer_phone) && filled($event->effective_organizer_phone)) {
            return $event->effective_organizer_phone;
        }

        foreach (['organizer_phone', 'contact_phone', 'phone'] as $field) {
            if (isset($event->{$field}) && filled($event->{$field})) {
                return $event->{$field};
            }
        }

        return config('app.organizer_phone')
            ?? config('services.elive.contact_phone')
            ?? null;
    }
}
