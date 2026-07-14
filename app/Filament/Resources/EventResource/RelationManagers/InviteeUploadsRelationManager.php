<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Models\InviteeUpload;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class InviteeUploadsRelationManager extends RelationManager
{
    protected static string $relationship = 'inviteeUploads';

    protected static ?string $title = 'Wishes & Photos';

    protected static ?string $modelLabel = 'Wish / Photo';

    protected static ?string $pluralModelLabel = 'Wishes & Photos';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return static::canAccessOwnerRecord($ownerRecord);
    }

    protected static function canAccessOwnerRecord(Model $ownerRecord): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isEventAdmin()) {
            return (int) ($ownerRecord->user_id ?? 0) === (int) $user->id;
        }

        return false;
    }

    protected function canManageSubmissions(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (! static::canAccessOwnerRecord($this->getOwnerRecord())) {
            return false;
        }

        return $user->isSuperAdmin()
            || $user->isEventAdmin()
            || ($user->canManageInvitees() ?? false);
    }

    public function isReadOnly(): bool
    {
        return ! $this->canManageSubmissions();
    }

    protected function canCreate(): bool
    {
        return $this->canManageSubmissions();
    }

    protected function canEdit($record): bool
    {
        return $this->canManageSubmissions();
    }

    protected function canDelete($record): bool
    {
        return $this->canManageSubmissions();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Submission Details')
                    ->schema([
                        Forms\Components\Select::make('invitee_id')
                            ->label('Invitee')
                            ->relationship(
                                name: 'invitee',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query): Builder => $query
                                    ->where('event_id', $this->getOwnerRecord()->id)
                            )
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('type')
                            ->label('Type')
                            ->options(InviteeUpload::types())
                            ->default(InviteeUpload::TYPE_WISH)
                            ->required()
                            ->native(false),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(InviteeUpload::statuses())
                            ->default(InviteeUpload::STATUS_PENDING)
                            ->required()
                            ->native(false),
                    ])
                    ->columns(3),

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
                            ->directory(fn (): string => 'events/' . $this->getOwnerRecord()->id . '/invitee-uploads')
                            ->visibility('public')
                            ->imageEditor()
                            ->maxSize(5120)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Approval')
                    ->schema([
                        Forms\Components\Select::make('approved_by')
                            ->label('Reviewed By')
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
                    ->columns(3)
                    ->collapsible(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Invitee Wishes & Photos')
            ->description('Approve or reject wishes and uploaded photos for this event.')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['invitee', 'approvedBy'])
                ->where('event_id', $this->getOwnerRecord()->id)
            )
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

                Tables\Columns\TextColumn::make('invitee.name')
                    ->label('Invitee')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::SemiBold)
                    ->limit(28),

                Tables\Columns\TextColumn::make('invitee.phone')
                    ->label('Phone')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('message')
                    ->label('Wish / Caption')
                    ->searchable()
                    ->limit(80)
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

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),
            ])
            ->filters([
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
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Wish / Photo')
                    ->visible(fn (): bool => $this->canManageSubmissions())
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['event_id'] = $this->getOwnerRecord()->id;

                        return $data;
                    }),
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
                    ->visible(fn (InviteeUpload $record): bool => $record->type === InviteeUpload::TYPE_PHOTO && filled($record->file_path)),

                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Submission')
                    ->modalDescription('This wish or photo will become visible on the invitee page.')
                    ->modalSubmitActionLabel('Approve')
                    ->visible(fn (InviteeUpload $record): bool => $this->canManageSubmissions() && $record->status !== InviteeUpload::STATUS_APPROVED)
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
                    ->visible(fn (InviteeUpload $record): bool => $this->canManageSubmissions() && $record->status !== InviteeUpload::STATUS_REJECTED)
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
                    ->visible(fn (InviteeUpload $record): bool => $this->canManageSubmissions() && $record->status !== InviteeUpload::STATUS_PENDING)
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
                    ->visible(fn (): bool => $this->canManageSubmissions()),

                Tables\Actions\DeleteAction::make()
                    ->label('Delete')
                    ->visible(fn (): bool => $this->canManageSubmissions()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('approve_selected')
                        ->label('Approve Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (): bool => $this->canManageSubmissions())
                        ->action(function ($records): void {
                            $records->each(fn (InviteeUpload $record) => $record->approve(Auth::id()));

                            Notification::make()
                                ->title('Selected submissions approved')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('reject_selected')
                        ->label('Reject Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (): bool => $this->canManageSubmissions())
                        ->action(function ($records): void {
                            $records->each(fn (InviteeUpload $record) => $record->reject(Auth::id()));

                            Notification::make()
                                ->title('Selected submissions rejected')
                                ->danger()
                                ->send();
                        }),

                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => $this->canManageSubmissions()),
                ]),
            ]);
    }
}
