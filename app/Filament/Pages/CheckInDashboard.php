<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Models\Event;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\MaxWidth;

class CheckInDashboard extends ViewRecord
{
    protected static string $resource = EventResource::class;

    protected static string $view =
        'filament.resources.event-resource.pages.check-in-dashboard';

    protected static ?string $title = 'Check-in Dashboard';

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('openScanner')
                ->label('Open Gate Scanner')
                ->icon('heroicon-o-qr-code')
                ->color('primary')
                ->url(fn (): string => url('/admin/gate-check-in')),

            Actions\Action::make('backToEvent')
                ->label('Back to Event')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn (): string => EventResource::getUrl('view', [
                    'record' => $this->record,
                ])),
        ];
    }

    public function getViewData(): array
    {
        /** @var Event $event */
        $event = $this->record;

        $successfulStatuses = [
            'success',
            'checked_in',
            'valid',
            'partial',
        ];

        $failedStatuses = [
            'failed',
            'invalid',
            'rejected',
        ];

        $totalInvitees = $event->invitees()->count();

        $totalAllowedGuests = (int) $event->invitees()
            ->sum('allowed_guests');

        $checkedInInvitees = $event->invitees()
            ->where('checked_in_count', '>', 0)
            ->count();

        $totalGuestsAdmitted = (int) $event->checkIns()
            ->whereIn('status', $successfulStatuses)
            ->sum('guests_checked_in');

        $remainingGuests = max(
            $totalAllowedGuests - $totalGuestsAdmitted,
            0
        );

        $checkInRate = $totalAllowedGuests > 0
            ? round(($totalGuestsAdmitted / $totalAllowedGuests) * 100, 1)
            : 0;

        $partialCheckIns = $event->invitees()
            ->where('checked_in_count', '>', 0)
            ->whereColumn('checked_in_count', '<', 'allowed_guests')
            ->count();

        $fullyCheckedIn = $event->invitees()
            ->whereColumn('checked_in_count', '>=', 'allowed_guests')
            ->count();

        $failedAttempts = $event->checkIns()
            ->whereIn('status', $failedStatuses)
            ->count();

        $successfulTransactions = $event->checkIns()
            ->whereIn('status', $successfulStatuses)
            ->count();

        $recentCheckIns = $event->checkIns()
            ->with([
                'invitee.cardType',
                'checkedInBy',
            ])
            ->latest('checked_in_at')
            ->limit(15)
            ->get();

        $byCardType = $event->checkIns()
            ->join('invitees', 'invitees.id', '=', 'check_ins.invitee_id')
            ->leftJoin('card_types', 'card_types.id', '=', 'invitees.card_type_id')
            ->whereIn('check_ins.status', $successfulStatuses)
            ->selectRaw("COALESCE(card_types.name, 'Unassigned') as label")
            ->selectRaw('SUM(check_ins.guests_checked_in) as total')
            ->groupBy('card_types.name')
            ->orderByDesc('total')
            ->get();

        $byCategory = $event->checkIns()
            ->join('invitees', 'invitees.id', '=', 'check_ins.invitee_id')
            ->whereIn('check_ins.status', $successfulStatuses)
            ->selectRaw("COALESCE(NULLIF(invitees.category, ''), 'Uncategorized') as label")
            ->selectRaw('SUM(check_ins.guests_checked_in) as total')
            ->groupBy('invitees.category')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $byTable = $event->invitees()
            ->selectRaw("COALESCE(NULLIF(table_number, ''), 'Unassigned') as label")
            ->selectRaw('SUM(allowed_guests) as expected')
            ->selectRaw('SUM(checked_in_count) as admitted')
            ->groupBy('table_number')
            ->orderBy('table_number')
            ->limit(20)
            ->get();

        $byGateUser = $event->checkIns()
            ->leftJoin('users', 'users.id', '=', 'check_ins.checked_in_by')
            ->whereIn('check_ins.status', $successfulStatuses)
            ->selectRaw("COALESCE(users.name, 'System') as label")
            ->selectRaw('COUNT(check_ins.id) as transactions')
            ->selectRaw('SUM(check_ins.guests_checked_in) as guests')
            ->groupBy('users.name')
            ->orderByDesc('guests')
            ->get();

        return compact(
            'event',
            'totalInvitees',
            'totalAllowedGuests',
            'checkedInInvitees',
            'totalGuestsAdmitted',
            'remainingGuests',
            'checkInRate',
            'partialCheckIns',
            'fullyCheckedIn',
            'failedAttempts',
            'successfulTransactions',
            'recentCheckIns',
            'byCardType',
            'byCategory',
            'byTable',
            'byGateUser',
        );
    }
}
