<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InviteeUploadResource\Pages;
use App\Models\Event;
use App\Models\Invitee;
use App\Models\InviteeUpload;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class InviteeUploadResource extends Resource
{
    protected static ?string $model = InviteeUpload::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = 'Event Management';

    protected static ?string $navigationLabel = 'Wishes & Photos';

    protected static ?string $modelLabel = 'Wish / Photo';

    protected static ?string $pluralModelLabel = 'Wishes & Photos';

    protected static ?int $navigationSort = 9;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->canManageEvents() ?? false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->canManageEvents() ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->canManageEvents() ?? false;
    }

    public static function canView($record): bool
    {
        return static::canAccessRecord($record);
    }

    public static function canEdit($record): bool
    {
        return static::canAccessRecord($record);
    }

    public static function canDelete($record): bool
    {
        return static::canAccessRecord($record);
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->canManageEvents() ?? false;
    }

    protected static function canAccessRecord(?InviteeUpload $record): bool
    {
        $user = auth()->user();

        if (! $user || ! $record) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isEventAdmin()) {
            $record->loadMissing('event');

            return (int) ($record->event?->user_id ?? 0) === (int) $user->id;
        }

        return false;
    }

    protected static function visibleEventsQuery(): Builder
    {
        $query = Event::query();

        $user = auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        if ($user->isEventAdmin()) {
            return $query->where('user_id', $user->id);
        }

        return $query->whereRaw('1 = 0');
    }

    protected static function visibleEventOptions(): array
    {
        return static::visibleEventsQuery()
            ->orderBy('title')
            ->pluck('title', 'id')
            ->toArray();
    }

    protected static function visibleInviteeOptions(?int $eventId): array
    {
        if (! $eventId) {
            return [];
        }

        $eventAllowed = static::visibleEventsQuery()
            ->whereKey($eventId)
            ->exists();

        if (! $eventAllowed) {
            return [];
        }

        return Invitee::query()
            ->where('event_id', $eventId)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Submission Details')
                    ->description('Review invitee wishes and uploaded photos before public display.')
                    ->schema([
                        Forms\Components\Select::make('event_id')
                            ->label('Event')
                            ->options(fn (): array => static::visibleEventOptions())
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required()
                            ->afterStateUpdated(fn (callable $set): mixed => $set('invitee_id', null))
                            ->helperText('Super Admin can select any event. Event Admin can select only own events.'),

                        Forms\Components\Select::make('invitee_id')
                            ->label('Invitee')
                            ->options(fn (Forms\Get $get): array => static::visibleInviteeOptions((int) ($get('event_id') ?? 0)))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn (Forms\Get $get): bool => blank($get('event_id')))
                            ->helperText('Invitees are filtered by the selected event.'),

                        Forms\Components\Select::make('type')
                            ->label('Type')
                            ->options(InviteeUpload::types())
                            ->required()
                            ->native(false),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(InviteeUpload::statuses())
                            ->default(InviteeUpload::STATUS_PENDING)
                            ->required()
                            ->native(false),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Content')
                    ->schema([
                        Forms\Components\Textarea::make('message')
                            ->label('Wish / Caption')
                            ->rows(5)
                            ->maxLength(1000)
                            ->columnSpanFull(),

                        Forms\Components\FileUpload::make('file_path')
                            ->label('Photo')
                            ->image()
                            ->disk('public')
                            ->directory(fn (Forms\Get $get): string => 'events/' . ($get('event_id') ?: 'unassigned') . '/invitee-uploads')
                            ->visibility('public')
                            ->imageEditor()
                            ->maxSize(5120)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Approval')
                    ->schema([
                        Forms\Components\Select::make('approved_by')
                            ->label('Approved / Reviewed By')
                            ->relationship('approvedBy', 'name')
                            ->searchable()
                            ->preload()
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('approved_at')
                            ->label('Approved At')
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('rejected_at')
                            ->label('Rejected At')
                            ->disabled(),

                        Forms\Components\Textarea::make('admin_note')
                            ->label('Admin Note')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\ImageColumn::make('file_path')
                    ->label('Photo')
                    ->disk('public')
                    ->height(56)
                    ->width(56)
                    ->square(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => InviteeUpload::types()[$state] ?? ucfirst((string) $state))
                    ->color(fn (?string $state): string => match ($state) {
                        InviteeUpload::TYPE_PHOTO => 'info',
                        InviteeUpload::TYPE_WISH => 'warning',
                        default => 'gray',
                    })
                    ->icon(fn (?string $state): string => match ($state) {
                        InviteeUpload::TYPE_PHOTO => 'heroicon-o-photo',
                        InviteeUpload::TYPE_WISH => 'heroicon-o-chat-bubble-left-ellipsis',
                        default => 'heroicon-o-document',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('event.title')
                    ->label('Event')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::SemiBold)
                    ->limit(28),

                Tables\Columns\TextColumn::make('invitee.name')
                    ->label('Invitee')
                    ->searchable()
                    ->sortable()
                    ->limit(28),

                Tables\Columns\TextColumn::make('invitee.phone')
                    ->label('Phone')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('message')
                    ->label('Wish / Caption')
                    ->searchable()
                    ->limit(70)
                    ->wrap(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => InviteeUpload::statuses()[$state] ?? ucfirst((string) $state))
                    ->color(fn (?string $state): string => match ($state) {
                        InviteeUpload::STATUS_APPROVED => 'success',
                        InviteeUpload::STATUS_REJECTED => 'danger',
                        InviteeUpload::STATUS_PENDING => 'warning',
                        default => 'gray',
                    })
                    ->icon(fn (?string $state): string => match ($state) {
                        InviteeUpload::STATUS_APPROVED => 'heroicon-o-check-circle',
                        InviteeUpload::STATUS_REJECTED => 'heroicon-o-x-circle',
                        InviteeUpload::STATUS_PENDING => 'heroicon-o-clock',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('approvedBy.name')
                    ->label('Reviewed By')
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('approved_at')
                    ->label('Approved At')
                    ->dateTime('d M Y, h:i A')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('rejected_at')
                    ->label('Rejected At')
                    ->dateTime('d M Y, h:i A')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_id')
                    ->label('Event')
                    ->options(fn (): array => static::visibleEventOptions())
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('type')
                    ->label('Type')
                    ->options(InviteeUpload::types()),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(InviteeUpload::statuses()),

                Tables\Filters\Filter::make('pending')
                    ->label('Pending Approval')
                    ->query(fn (Builder $query): Builder => $query->where('status', InviteeUpload::STATUS_PENDING)),

                Tables\Filters\Filter::make('photos')
                    ->label('Photos Only')
                    ->query(fn (Builder $query): Builder => $query->where('type', InviteeUpload::TYPE_PHOTO)),

                Tables\Filters\Filter::make('wishes')
                    ->label('Wishes Only')
                    ->query(fn (Builder $query): Builder => $query->where('type', InviteeUpload::TYPE_WISH)),
            ])
            ->actions([
                Tables\Actions\Action::make('open_photo')
                    ->label('Open Photo')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (InviteeUpload $record): ?string => filled($record->file_path)
                        ? Storage::disk('public')->url($record->file_path)
                        : null)
                    ->openUrlInNewTab()
                    ->visible(fn (InviteeUpload $record): bool => static::canAccessRecord($record) && $record->type === InviteeUpload::TYPE_PHOTO && filled($record->file_path)),

                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Submission')
                    ->modalDescription('This wish or photo will become visible on the public invitee page.')
                    ->modalSubmitActionLabel('Approve')
                    ->visible(fn (InviteeUpload $record): bool => static::canAccessRecord($record) && $record->status !== InviteeUpload::STATUS_APPROVED)
                    ->action(function (InviteeUpload $record): void {
                        $record->approve(Auth::id());

                        Notification::make()
                            ->title('Submission approved')
                            ->body('The item is now approved for public display.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('admin_note')
                            ->label('Reason / Admin Note')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('Optional reason for rejection.'),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Reject Submission')
                    ->modalDescription('This wish or photo will not appear publicly.')
                    ->modalSubmitActionLabel('Reject')
                    ->visible(fn (InviteeUpload $record): bool => static::canAccessRecord($record) && $record->status !== InviteeUpload::STATUS_REJECTED)
                    ->action(function (InviteeUpload $record, array $data): void {
                        $record->reject(Auth::id(), $data['admin_note'] ?? null);

                        Notification::make()
                            ->title('Submission rejected')
                            ->body('The item has been rejected.')
                            ->danger()
                            ->send();
                    }),

                Tables\Actions\Action::make('mark_pending')
                    ->label('Mark Pending')
                    ->icon('heroicon-o-clock')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (InviteeUpload $record): bool => static::canAccessRecord($record) && $record->status !== InviteeUpload::STATUS_PENDING)
                    ->action(function (InviteeUpload $record): void {
                        $record->markPending();

                        Notification::make()
                            ->title('Submission moved to pending')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make()
                    ->label('Edit')
                    ->color('gray')
                    ->visible(fn (InviteeUpload $record): bool => static::canAccessRecord($record)),

                Tables\Actions\DeleteAction::make()
                    ->label('Delete')
                    ->visible(fn (InviteeUpload $record): bool => static::canAccessRecord($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('approve_selected')
                        ->label('Approve Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records): void {
                            $approved = 0;

                            $records->each(function (InviteeUpload $record) use (&$approved): void {
                                if (! static::canAccessRecord($record)) {
                                    return;
                                }

                                $record->approve(Auth::id());
                                $approved++;
                            });

                            Notification::make()
                                ->title('Selected submissions approved')
                                ->body("Approved: {$approved}.")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('reject_selected')
                        ->label('Reject Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($records): void {
                            $rejected = 0;

                            $records->each(function (InviteeUpload $record) use (&$rejected): void {
                                if (! static::canAccessRecord($record)) {
                                    return;
                                }

                                $record->reject(Auth::id());
                                $rejected++;
                            });

                            Notification::make()
                                ->title('Selected submissions rejected')
                                ->body("Rejected: {$rejected}.")
                                ->danger()
                                ->send();
                        }),

                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->canManageEvents() ?? false),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['event', 'invitee', 'approvedBy']);

        $user = auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        if ($user->isEventAdmin()) {
            return $query->whereHas('event', function (Builder $eventQuery) use ($user): void {
                $eventQuery->where('user_id', $user->id);
            });
        }

        return $query->whereRaw('1 = 0');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInviteeUploads::route('/'),
            'create' => Pages\CreateInviteeUpload::route('/create'),
            'edit' => Pages\EditInviteeUpload::route('/{record}/edit'),
        ];
    }
}
