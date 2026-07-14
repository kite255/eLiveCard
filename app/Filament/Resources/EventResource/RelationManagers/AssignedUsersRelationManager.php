<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AssignedUsersRelationManager extends RelationManager
{
    protected static string $relationship = 'assignedUsers';

    protected static ?string $title = 'Assigned Users';

    protected static ?string $modelLabel = 'Assigned User';

    protected static ?string $pluralModelLabel = 'Assigned Users';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Event Assignment')
                    ->description('Assign users who can manage this event or check in guests at the gate.')
                    ->schema([
                        Forms\Components\Select::make('role')
                            ->label('Event Role')
                            ->options([
                                User::ROLE_EVENT_ADMIN => 'Event Admin',
                                User::ROLE_CHECK_IN_OFFICER => 'Check-in Officer',
                            ])
                            ->default(User::ROLE_EVENT_ADMIN)
                            ->required(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->emptyStateHeading('No users assigned')
            ->emptyStateDescription('Assign event admins or check-in officers to this event.')
            ->emptyStateIcon('heroicon-o-user-group')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('User')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('pivot.role')
                    ->label('Event Role')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        User::ROLE_EVENT_ADMIN => 'Event Admin',
                        User::ROLE_CHECK_IN_OFFICER => 'Check-in Officer',
                        default => str((string) $state)->replace('_', ' ')->title()->toString(),
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        User::ROLE_EVENT_ADMIN => 'primary',
                        User::ROLE_CHECK_IN_OFFICER => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('pivot.is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('pivot.created_at')
                    ->label('Assigned At')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('assignment_role')
                    ->label('Event Role')
                    ->options([
                        User::ROLE_EVENT_ADMIN => 'Event Admin',
                        User::ROLE_CHECK_IN_OFFICER => 'Check-in Officer',
                    ])
                    ->query(function ($query, array $data) {
                        if (! filled($data['value'] ?? null)) {
                            return $query;
                        }

                        return $query->where('event_user.role', $data['value']);
                    }),

                Tables\Filters\TernaryFilter::make('assignment_active')
                    ->label('Active')
                    ->queries(
                        true: fn ($query) => $query->where('event_user.is_active', true),
                        false: fn ($query) => $query->where('event_user.is_active', false),
                        blank: fn ($query) => $query,
                    ),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->label('Assign User')
                    ->modalHeading('Assign User to Event')
                    ->modalSubmitActionLabel('Assign User')
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name', 'email'])
                    ->recordSelectOptionsQuery(fn ($query) => $query
                        ->whereIn('users.role', [
                            User::ROLE_EVENT_ADMIN,
                            User::ROLE_CHECK_IN_OFFICER,
                        ])
                        ->orderBy('users.name'))
                    ->form(fn (Tables\Actions\AttachAction $action): array => [
                        $action->getRecordSelect()
                            ->label('User')
                            ->required(),

                        Forms\Components\Select::make('role')
                            ->label('Event Role')
                            ->options([
                                User::ROLE_EVENT_ADMIN => 'Event Admin',
                                User::ROLE_CHECK_IN_OFFICER => 'Check-in Officer',
                            ])
                            ->default(User::ROLE_EVENT_ADMIN)
                            ->required(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->successNotificationTitle('User assigned successfully'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Edit Role'),

                Tables\Actions\Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Model $record): bool => ! (bool) ($record->pivot->is_active ?? false))
                    ->action(function (Model $record): void {
                        $this->getOwnerRecord()
                            ->assignedUsers()
                            ->updateExistingPivot($record->id, [
                                'is_active' => true,
                            ]);

                        Notification::make()
                            ->title('User activated for this event')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('deactivate')
                    ->label('Deactivate')
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->visible(fn (Model $record): bool => (bool) ($record->pivot->is_active ?? false))
                    ->requiresConfirmation()
                    ->modalHeading('Deactivate assigned user')
                    ->modalDescription('This user will no longer access this event through assignment.')
                    ->action(function (Model $record): void {
                        $this->getOwnerRecord()
                            ->assignedUsers()
                            ->updateExistingPivot($record->id, [
                                'is_active' => false,
                            ]);

                        Notification::make()
                            ->title('User deactivated for this event')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DetachAction::make()
                    ->label('Remove')
                    ->modalHeading('Remove user from this event')
                    ->successNotificationTitle('User removed from event'),
            ])
            ->bulkActions([
                Tables\Actions\DetachBulkAction::make()
                    ->label('Remove Selected'),
            ]);
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isEventAdmin()
            && $user->canManageEvent($ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}
