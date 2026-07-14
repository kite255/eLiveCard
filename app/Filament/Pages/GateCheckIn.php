<?php

namespace App\Filament\Pages;

use App\Models\Event;
use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class GateCheckIn extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-qr-code';

    protected static ?string $navigationGroup = 'Check-in Management';

    protected static ?string $navigationLabel = 'Gate Check-in';

    protected static ?string $title = 'Gate Check-in';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.gate-check-in';

    public Collection $events;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->canScanGuests() ?? false;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canScanGuests() ?? false;
    }

    public function mount()
    {
        $this->events = $this->authorizedEventsQuery()
            ->when(
                \Schema::hasColumn('events', 'event_date'),
                fn (Builder $query) => $query->orderByDesc('event_date'),
                fn (Builder $query) => $query->latest()
            )
            ->get();

        /**
         * If this user has only one assigned event,
         * send them directly to the professional check-in page.
         */
        if ($this->events->count() === 1) {
            return redirect()->route('gate.check-in.show', $this->events->first());
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
                    ->orWhereHas('assignedUsers', function (Builder $query) use ($user): void {
                        $query
                            ->where('users.id', $user->id)
                            ->where('event_user.is_active', true);
                    });
            });
        }

        if ($user->isCheckInOfficer()) {
            return $query->whereHas('assignedUsers', function (Builder $query) use ($user): void {
                $query
                    ->where('users.id', $user->id)
                    ->where('event_user.role', User::ROLE_CHECK_IN_OFFICER)
                    ->where('event_user.is_active', true);
            });
        }

        return $query->whereRaw('1 = 0');
    }
}