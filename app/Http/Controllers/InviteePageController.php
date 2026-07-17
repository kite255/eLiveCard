<?php

namespace App\Http\Controllers;

use App\Models\Invitee;
use App\Models\InviteeUpload;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

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

        abort_unless(
            $invitee->event,
            404,
            'The event linked to this invitation was not found.'
        );

        $this->trackInviteeOpen($invitee, $request);
        $this->auditInvitationOpen($invitee, $request);

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
            'approvedWishes' => $this->shouldShowWishes($event)
                ? $this->approvedWishes($event?->id)
                : collect(),
            'myWishes' => $this->shouldShowWishes($event)
                ? $this->inviteeWishes($invitee)
                : collect(),
            'approvedPhotos' => $this->shouldShowPhotoUpload($event)
                ? $this->approvedPhotos($event?->id)
                : collect(),
            'myPhotos' => $this->shouldShowPhotoUpload($event)
                ? $this->inviteePhotos($invitee)
                : collect(),
            'showCoverImage' => $this->shouldShowCoverImage($event),
            'showProgram' => $this->shouldShowProgram($event),
            'showCountdown' => $this->shouldShowCountdown($event),
            'showWishes' => $this->shouldShowWishes($event),
            'showPhotoUpload' => $this->shouldShowPhotoUpload($event),
            'showOrganizerContact' => $this->shouldShowOrganizerContact($event),
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

        $oldValues = [
            'rsvp_status' => $invitee->rsvp_status,
            'confirmed_guests' => $invitee->confirmed_guests,
            'rsvp_confirmed_at' => $invitee->rsvp_confirmed_at,
        ];

        $invitee->update([
            'rsvp_status' => $request->status,
            'confirmed_guests' => $confirmedGuests,
            'rsvp_confirmed_at' => now(),
        ]);

        AuditLogService::record(
            action: 'rsvp.updated',
            subject: $invitee,
            eventId: $invitee->event_id,
            description: 'Invitee submitted an RSVP response from the private invitation page.',
            oldValues: $oldValues,
            newValues: [
                'rsvp_status' => $invitee->rsvp_status,
                'confirmed_guests' => $invitee->confirmed_guests,
                'rsvp_confirmed_at' => $invitee->rsvp_confirmed_at,
            ],
            metadata: [
                'source' => 'invitee_page',
                'allowed_guests' => $allowedGuests,
                'ip_address' => $request->ip(),
            ],
        );

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

        abort_unless(
            $this->shouldShowWishes($invitee->event),
            403,
            'Wish submission is disabled for this event.'
        );

        if (! $this->inviteeUploadsTableIsReady()) {
            return redirect()
                ->route('invitee.page', $invitee->short_code)
                ->with('warning', 'Wishes approval is not enabled yet. Please contact the organizer.');
        }

        $this->ensureSubmissionRateLimit(
            key: 'wish',
            invitee: $invitee,
            request: $request,
        );

        $wish = InviteeUpload::create([
            'event_id' => $invitee->event_id,
            'invitee_id' => $invitee->id,
            'type' => InviteeUpload::TYPE_WISH,
            'message' => trim((string) $request->message),
            'file_path' => null,
            'status' => InviteeUpload::STATUS_PENDING,
        ]);

        AuditLogService::created(
            subject: $wish,
            eventId: $invitee->event_id,
            description: 'Invitee submitted a wish for admin approval.',
            metadata: [
                'source' => 'invitee_page',
                'invitee_id' => $invitee->id,
                'submission_type' => 'wish',
                'message_length' => mb_strlen((string) $wish->message),
                'ip_address' => $request->ip(),
            ],
        );

        return redirect()
            ->route('invitee.page', $invitee->short_code)
            ->with('success', 'Thank you. Your wish has been submitted and is waiting for admin approval.');
    }

    public function updateWish(
        Request $request,
        string $shortCode,
        InviteeUpload $wish
    ) {
        $invitee = Invitee::query()
            ->with('event')
            ->where('short_code', $shortCode)
            ->firstOrFail();

        abort_unless(
            $this->canOpenInvitation($invitee),
            403,
            'This invitation is not active.'
        );

        abort_unless(
            $this->shouldShowWishes($invitee->event),
            403,
            'Wishes are disabled for this event.'
        );

        abort_unless(
            $wish->event_id === $invitee->event_id
            && $wish->invitee_id === $invitee->id
            && $wish->type === InviteeUpload::TYPE_WISH,
            404
        );

        abort_unless(
            $wish->status === InviteeUpload::STATUS_PENDING,
            403,
            'Only pending wishes can be edited.'
        );

        $validated = $request->validate([
            'message' => [
                'required',
                'string',
                'min:3',
                'max:1000',
            ],
        ], [
            'message.required' => 'Please enter your wish.',
            'message.min' => 'Your wish must contain at least 3 characters.',
            'message.max' => 'Your wish cannot exceed 1000 characters.',
        ]);

        $this->ensureSubmissionRateLimit(
            key: 'wish-edit',
            invitee: $invitee,
            request: $request,
        );

        $oldValues = [
            'message' => $wish->message,
            'status' => $wish->status,
        ];

        $wish->update([
            'message' => trim((string) $validated['message']),
        ]);

        AuditLogService::updated(
            subject: $wish,
            eventId: $invitee->event_id,
            description: 'Invitee edited a pending wish from the private invitation page.',
            oldValues: $oldValues,
            newValues: [
                'message' => $wish->message,
                'status' => $wish->status,
            ],
            metadata: [
                'source' => 'invitee_page',
                'invitee_id' => $invitee->id,
                'submission_type' => 'wish',
                'ip_address' => $request->ip(),
            ],
        );

        return redirect()
            ->route('invitee.page', $invitee->short_code)
            ->with(
                'success',
                'Your wish has been updated and is still waiting for approval.'
            );
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

        abort_unless(
            $this->shouldShowPhotoUpload($invitee->event),
            403,
            'Photo uploads are disabled for this event.'
        );

        if (! $this->inviteeUploadsTableIsReady()) {
            return redirect()
                ->route('invitee.page', $invitee->short_code)
                ->with('warning', 'Photo approval is not enabled yet. Please contact the organizer.');
        }

        $this->ensureSubmissionRateLimit(
            key: 'photo',
            invitee: $invitee,
            request: $request,
        );

        $photo = $request->file('photo');
        $extension = $this->safeImageExtension(
            (string) $photo->getMimeType()
        );
        $filename = Str::uuid().'.'.$extension;

        $path = $photo->storeAs(
            'events/'.$invitee->event_id.'/invitee-uploads',
            $filename,
            'public',
        );

        try {
            $upload = InviteeUpload::create([
                'event_id' => $invitee->event_id,
                'invitee_id' => $invitee->id,
                'type' => InviteeUpload::TYPE_PHOTO,
                'message' => $request->filled('caption')
                    ? trim((string) $request->caption)
                    : null,
                'file_path' => $path,
                'status' => InviteeUpload::STATUS_PENDING,
            ]);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($path);

            AuditLogService::record(
                action: 'invitee_upload.photo_failed',
                subject: $invitee,
                eventId: $invitee->event_id,
                description: 'Invitee photo upload failed.',
                metadata: [
                    'source' => 'invitee_page',
                    'invitee_id' => $invitee->id,
                    'error' => $exception->getMessage(),
                ],
            );

            throw $exception;
        }

        AuditLogService::created(
            subject: $upload,
            eventId: $invitee->event_id,
            description: 'Invitee submitted a photo for admin approval.',
            metadata: [
                'source' => 'invitee_page',
                'invitee_id' => $invitee->id,
                'submission_type' => 'photo',
                'file_path' => $path,
                'mime_type' => $photo->getMimeType(),
                'size_bytes' => $photo->getSize(),
                'ip_address' => $request->ip(),
            ],
        );

        return redirect()
            ->route('invitee.page', $invitee->short_code)
            ->with('success', 'Thank you. Your photo has been submitted and is waiting for admin approval.');
    }

    public function updatePhoto(
        Request $request,
        string $shortCode,
        InviteeUpload $photo
    ) {
        $invitee = Invitee::query()
            ->with('event')
            ->where('short_code', $shortCode)
            ->firstOrFail();

        abort_unless(
            $this->canOpenInvitation($invitee),
            403,
            'This invitation is not active.'
        );

        abort_unless(
            $this->shouldShowPhotoUpload($invitee->event),
            403,
            'Photo uploads are disabled for this event.'
        );

        $this->authorizeInviteePhoto($invitee, $photo);

        abort_unless(
            $photo->status === InviteeUpload::STATUS_PENDING,
            403,
            'Only pending photos can be edited.'
        );

        $validated = $request->validate([
            'caption' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'photo.image' => 'The replacement file must be an image.',
            'photo.max' => 'The photo must not be larger than 5MB.',
            'caption.max' => 'The caption cannot exceed 255 characters.',
        ]);

        $this->ensureSubmissionRateLimit(
            key: 'photo-edit',
            invitee: $invitee,
            request: $request,
        );

        $oldPath = $photo->file_path;
        $newPath = null;

        if ($request->hasFile('photo')) {
            $replacement = $request->file('photo');
            $extension = $this->safeImageExtension(
                (string) $replacement->getMimeType()
            );
            $filename = Str::uuid().'.'.$extension;

            $newPath = $replacement->storeAs(
                'events/'.$invitee->event_id.'/invitee-uploads',
                $filename,
                'public',
            );
        }

        $oldValues = [
            'message' => $photo->message,
            'file_path' => $photo->file_path,
            'status' => $photo->status,
        ];

        try {
            $photo->update([
                'message' => $request->filled('caption')
                    ? trim((string) $validated['caption'])
                    : null,
                'file_path' => $newPath ?: $photo->file_path,
            ]);
        } catch (Throwable $exception) {
            if ($newPath) {
                Storage::disk('public')->delete($newPath);
            }

            AuditLogService::record(
                action: 'invitee_upload.photo_update_failed',
                subject: $photo,
                eventId: $invitee->event_id,
                description: 'Invitee photo update failed.',
                metadata: [
                    'source' => 'invitee_page',
                    'invitee_id' => $invitee->id,
                    'photo_id' => $photo->id,
                    'error' => $exception->getMessage(),
                ],
            );

            throw $exception;
        }

        if (
            $newPath
            && filled($oldPath)
            && $oldPath !== $newPath
            && Storage::disk('public')->exists($oldPath)
        ) {
            Storage::disk('public')->delete($oldPath);
        }

        $photo->refresh();

        AuditLogService::updated(
            subject: $photo,
            eventId: $invitee->event_id,
            description: $newPath
                ? 'Invitee replaced a pending photo and updated its caption.'
                : 'Invitee updated the caption of a pending photo.',
            oldValues: $oldValues,
            newValues: [
                'message' => $photo->message,
                'file_path' => $photo->file_path,
                'status' => $photo->status,
            ],
            metadata: [
                'source' => 'invitee_page',
                'invitee_id' => $invitee->id,
                'submission_type' => 'photo',
                'photo_replaced' => (bool) $newPath,
                'ip_address' => $request->ip(),
            ],
        );

        return redirect()
            ->route('invitee.page', $invitee->short_code)
            ->with(
                'success',
                $newPath
                    ? 'Your photo has been replaced and is still waiting for approval.'
                    : 'Your photo caption has been updated.'
            );
    }

    public function deletePhoto(
        Request $request,
        string $shortCode,
        InviteeUpload $photo
    ) {
        $invitee = Invitee::query()
            ->with('event')
            ->where('short_code', $shortCode)
            ->firstOrFail();

        abort_unless(
            $this->canOpenInvitation($invitee),
            403,
            'This invitation is not active.'
        );

        abort_unless(
            $this->shouldShowPhotoUpload($invitee->event),
            403,
            'Photo uploads are disabled for this event.'
        );

        $this->authorizeInviteePhoto($invitee, $photo);

        abort_unless(
            $photo->status === InviteeUpload::STATUS_PENDING,
            403,
            'Only pending photos can be deleted.'
        );

        $this->ensureSubmissionRateLimit(
            key: 'photo-delete',
            invitee: $invitee,
            request: $request,
        );

        $snapshot = [
            'id' => $photo->id,
            'message' => $photo->message,
            'file_path' => $photo->file_path,
            'status' => $photo->status,
        ];

        AuditLogService::deleted(
            subject: $photo,
            eventId: $invitee->event_id,
            description: 'Invitee deleted a pending photo from the private invitation page.',
            metadata: [
                'source' => 'invitee_page',
                'invitee_id' => $invitee->id,
                'submission_type' => 'photo',
                'photo' => $snapshot,
                'ip_address' => $request->ip(),
            ],
        );

        $storedPath = $photo->file_path;
        $photo->delete();

        if (
            filled($storedPath)
            && Storage::disk('public')->exists($storedPath)
        ) {
            Storage::disk('public')->delete($storedPath);
        }

        return redirect()
            ->route('invitee.page', $invitee->short_code)
            ->with('success', 'Your pending photo has been deleted.');
    }

    protected function authorizeInviteePhoto(
        Invitee $invitee,
        InviteeUpload $photo
    ): void {
        abort_unless(
            (int) $photo->event_id === (int) $invitee->event_id
            && (int) $photo->invitee_id === (int) $invitee->id
            && $photo->type === InviteeUpload::TYPE_PHOTO,
            404
        );
    }

    protected function inviteeUploadsTableIsReady(): bool
    {
        if (! Schema::hasTable('invitee_uploads')) {
            return false;
        }

        $requiredColumns = [
            'event_id',
            'invitee_id',
            'type',
            'message',
            'file_path',
            'status',
        ];

        return collect($requiredColumns)
            ->every(fn (string $column): bool =>
                Schema::hasColumn('invitee_uploads', $column)
            );
    }

    protected function inviteeWishes(Invitee $invitee)
    {
        if (! $this->inviteeUploadsTableIsReady()) {
            return collect();
        }

        return InviteeUpload::query()
            ->where('event_id', $invitee->event_id)
            ->where('invitee_id', $invitee->id)
            ->where('type', InviteeUpload::TYPE_WISH)
            ->latest('created_at')
            ->get();
    }

    protected function inviteePhotos(Invitee $invitee)
    {
        if (! $this->inviteeUploadsTableIsReady()) {
            return collect();
        }

        return InviteeUpload::query()
            ->where('event_id', $invitee->event_id)
            ->where('invitee_id', $invitee->id)
            ->where('type', InviteeUpload::TYPE_PHOTO)
            ->whereNotNull('file_path')
            ->latest('created_at')
            ->get()
            ->filter(fn (InviteeUpload $photo): bool =>
                $photo->hasStoredFile()
            )
            ->values();
    }

    protected function approvedWishes(?int $eventId)
    {
        if (! $eventId || ! $this->inviteeUploadsTableIsReady()) {
            return collect();
        }

        return InviteeUpload::query()
            ->with('invitee:id,name')
            ->forEvent($eventId)
            ->wishes()
            ->approved()
            ->latest('approved_at')
            ->latest('created_at')
            ->get()
            ->each(function (InviteeUpload $wish): void {
                $wish->setAttribute(
                    'display_name',
                    $wish->invitee?->name ?? 'Guest',
                );
            });
    }

    protected function approvedPhotos(?int $eventId)
    {
        if (! $eventId || ! $this->inviteeUploadsTableIsReady()) {
            return collect();
        }

        return InviteeUpload::query()
            ->with('invitee:id,name')
            ->forEvent($eventId)
            ->photos()
            ->approved()
            ->whereNotNull('file_path')
            ->latest('approved_at')
            ->latest('created_at')
            ->get()
            ->filter(fn (InviteeUpload $photo): bool =>
                $photo->hasStoredFile()
            )
            ->each(function (InviteeUpload $photo): void {
                $photo->setAttribute(
                    'display_name',
                    $photo->invitee?->name ?? 'Guest',
                );
            })
            ->values();
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
        ])->saveQuietly();
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

        $eventStatus = $invitee->event?->status;

        if (in_array($eventStatus, ['cancelled', 'completed'], true)) {
            return false;
        }

        if (in_array($invitee->card_status, ['cancelled', 'revoked', 'blocked', 'disabled'], true)) {
            return false;
        }

        return in_array(
            $invitee->card_status,
            array_unique($allowedStatuses),
            true
        );
    }

    protected function generatedCardUrl(Invitee $invitee): ?string
    {
        if (isset($invitee->generated_card_url) && filled($invitee->generated_card_url)) {
            return $invitee->generated_card_url;
        }

        $paths = [
            $invitee->generated_card_path ?? null,
            $invitee->card_path ?? null,
        ];

        foreach ($paths as $path) {
            $path = $this->normalizePublicStoragePath($path);

            if (filled($path) && Storage::disk('public')->exists($path)) {
                return Storage::disk('public')->url($path);
            }
        }

        if (method_exists($invitee, 'generatedCards')) {
            $generatedCard = $invitee->generatedCards()
                ->whereNotNull('file_path')
                ->latest()
                ->first();

            $path = $this->normalizePublicStoragePath($generatedCard?->file_path);

            if (filled($path) && Storage::disk('public')->exists($path)) {
                return Storage::disk('public')->url($path);
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

        $coverImage = $this->normalizePublicStoragePath($event->cover_image ?? null);

        if (filled($coverImage) && Storage::disk('public')->exists($coverImage)) {
            return Storage::disk('public')->url($coverImage);
        }

        return null;
    }

    protected function normalizePublicStoragePath(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        $path = trim($path);

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            $urlPath = parse_url($path, PHP_URL_PATH);
            $path = is_string($urlPath) ? $urlPath : $path;
        }

        $path = ltrim($path, '/');

        foreach (['storage/', 'public/'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $path = substr($path, strlen($prefix));
            }
        }

        return filled($path) ? $path : null;
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

        foreach (['organizer_phone', 'contact_person_phone', 'contact_phone', 'phone'] as $field) {
            if (isset($event->{$field}) && filled($event->{$field})) {
                return $event->{$field};
            }
        }

        return config('app.organizer_phone')
            ?? config('services.elive.contact_phone')
            ?? null;
    }

    protected function auditInvitationOpen(
        Invitee $invitee,
        Request $request
    ): void {
        $key = sprintf(
            'invitee-page-open-audit:%d:%s:%s',
            $invitee->id,
            sha1((string) $request->ip()),
            now()->format('Y-m-d')
        );

        if (! Cache::add($key, true, now()->addDay())) {
            return;
        }

        AuditLogService::record(
            action: 'invitee_page.opened',
            subject: $invitee,
            eventId: $invitee->event_id,
            description: 'Private invitation page was opened.',
            metadata: [
                'source' => 'invitee_page',
                'invitee_id' => $invitee->id,
                'short_code' => $invitee->short_code,
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit(
                    (string) $request->userAgent(),
                    500,
                    ''
                ),
            ],
        );
    }

    protected function ensureSubmissionRateLimit(
        string $key,
        Invitee $invitee,
        Request $request
    ): void {
        $cacheKey = sprintf(
            'invitee-submission:%s:%d:%s',
            $key,
            $invitee->id,
            sha1((string) $request->ip())
        );

        if (! Cache::add($cacheKey, true, now()->addSeconds(30))) {
            throw ValidationException::withMessages([
                $key === 'photo' ? 'photo' : 'message' =>
                    'Please wait a moment before submitting again.',
            ]);
        }
    }

    protected function safeImageExtension(string $mimeType): string
    {
        return match (strtolower($mimeType)) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw ValidationException::withMessages([
                'photo' => 'The uploaded image format is not supported.',
            ]),
        };
    }

    protected function shouldShowCoverImage($event): bool
    {
        return $event && method_exists($event, 'shouldShowCoverImage')
            ? $event->shouldShowCoverImage()
            : (bool) ($event?->show_cover_image ?? true);
    }

    protected function shouldShowProgram($event): bool
    {
        return $event && method_exists($event, 'shouldShowProgram')
            ? $event->shouldShowProgram()
            : (bool) ($event?->show_program ?? true);
    }

    protected function shouldShowCountdown($event): bool
    {
        return $event && method_exists($event, 'shouldShowCountdown')
            ? $event->shouldShowCountdown()
            : (bool) ($event?->show_countdown ?? true);
    }

    protected function shouldShowWishes($event): bool
    {
        return $event && method_exists($event, 'shouldShowWishes')
            ? $event->shouldShowWishes()
            : (bool) ($event?->show_wishes ?? true);
    }

    protected function shouldShowPhotoUpload($event): bool
    {
        return $event && method_exists($event, 'shouldShowPhotoUpload')
            ? $event->shouldShowPhotoUpload()
            : (bool) ($event?->show_photo_upload ?? true);
    }

    protected function shouldShowOrganizerContact($event): bool
    {
        return $event && method_exists($event, 'shouldShowOrganizerContact')
            ? $event->shouldShowOrganizerContact()
            : (bool) ($event?->show_organizer_contact ?? true);
    }

}
