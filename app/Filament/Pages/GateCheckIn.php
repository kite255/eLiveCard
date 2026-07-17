<?php

namespace App\Filament\Pages;

use App\Models\Event;
use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class GateCheckIn extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-qr-code';

    protected static ?string $navigationGroup = 'Check-in Management';

    protected static ?string $navigationLabel = 'Gate Check-in';

    protected static ?string $title = 'Gate Check-in';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.gate-check-in';

    /**
     * Do not register this page in Filament navigation.
     *
     * The dashboard and event pages open the professional
     * event-specific scanner route directly.
     */
    protected static bool $shouldRegisterNavigation = false;

    public Collection $events;

    public static function canAccess(): bool
    {
        return auth()->user()?->canScanGuests() ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->events = $this->authorizedEventsQuery()
            ->when(
                Schema::hasColumn('events', 'event_date'),
                fn (Builder $query): Builder => $query
                    ->orderByDesc('event_date')
                    ->orderByDesc('id'),
                fn (Builder $query): Builder => $query->latest(),
            )
            ->get();

        /*
         * When the user has one authorized event, redirect directly
         * to the professional event-specific gate scanner.
         */
        if ($this->events->count() === 1) {
            $this->redirect(
                route('gate.check-in.show', [
                    'event' => $this->events->first()->getKey(),
                ]),
                navigate: false,
            );
        }
    }

    protected function authorizedEventsQuery(): Builder
    {
        $user = auth()->user();

        $query = Event::query();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        if ($user->isEventAdmin()) {
            return $query->where(function (Builder $query) use ($user): void {
                $query
                    ->where('user_id', $user->id)
                    ->orWhereHas(
                        'assignedUsers',
                        function (Builder $query) use ($user): void {
                            $query
                                ->where('users.id', $user->id)
                                ->where('event_user.is_active', true);
                        },
                    );
            });
        }

        if ($user->isCheckInOfficer()) {
            return $query->whereHas(
                'assignedUsers',
                function (Builder $query) use ($user): void {
                    $query
                        ->where('users.id', $user->id)
                        ->where(
                            'event_user.role',
                            User::ROLE_CHECK_IN_OFFICER,
                        )
                        ->where('event_user.is_active', true);
                },
            );
        }

        return $query->whereRaw('1 = 0');
    }
}