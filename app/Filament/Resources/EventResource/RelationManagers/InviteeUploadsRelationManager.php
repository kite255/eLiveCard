<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Models\InviteeUpload;
use App\Services\AuditLogService;
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

        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }

        if (
            method_exists($user, 'isEventAdmin')
            && $user->isEventAdmin()
            && (int) ($ownerRecord->user_id ?? 0) === (int) $user->id
        ) {
            return true;
        }

        if (method_exists($user, 'canAccessEvent')) {
            return (bool) $user->canAccessEvent($ownerRecord);
        }

        return $user->can('view', $ownerRecord);
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

        return (
            method_exists($user, 'isSuperAdmin')
            && $user->isSuperAdmin()
        ) || (
            method_exists($user, 'isEventAdmin')
            && $user->isEventAdmin()
        ) || (
            method_exists($user, 'canManageInvitees')
            && $user->canManageInvitees()
        );
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
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Use Approve, Reject, or Mark Pending from the Manage menu to change the review status.')
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
            ->description('Review, approve, reject, and manage wishes and photos submitted for this event.')
            ->searchPlaceholder('Search invitee, phone, wish, caption, status, or type')
            ->searchDebounce('500ms')
            ->striped()
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->emptyStateIcon('heroicon-o-photo')
            ->emptyStateHeading('No wishes or photos yet')
            ->emptyStateDescription('Invitee submissions will appear here after they send a wish or upload a photo.')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['invitee', 'approvedBy'])
                ->where('event_id', $this->getOwnerRecord()->id)
            )
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\ViewColumn::make('preview')
                    ->label('Preview')
                    ->view('filament.tables.columns.invitee-upload-preview'),

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
                    ->copyable()
                    ->copyMessage('Phone number copied')
                    ->copyMessageDuration(1500)
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
                Tables\Columns\TextColumn::make('admin_note')
                    ->label('Admin Note')
                    ->limit(50)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('reviewed_at')
                    ->label('Reviewed At')
                    ->state(fn (InviteeUpload $record) => $record->approved_at ?? $record->rejected_at)
                    ->dateTime('d M Y, h:i A')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),


                Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime('d M Y, h:i A')
                    ->sortable()
                    ->toggleable(),
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
                    })
                    ->after(function (InviteeUpload $record): void {
                        AuditLogService::created(
                            subject: $record,
                            eventId: $record->event_id,
                            description: ucfirst($record->type_label).' was added manually.',
                            metadata: [
                                'invitee_id' => $record->invitee_id,
                                'type' => $record->type,
                                'source' => 'admin',
                            ],
                        );
                    }),
            ])
            ->actionsPosition(Tables\Enums\ActionsPosition::AfterColumns)
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('open_photo')
                    ->label('Open Photo')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (InviteeUpload $record): ?string => filled($record->file_path)
                        ? Storage::disk('public')->url($record->file_path)
                        : null)
                    ->openUrlInNewTab()
                    ->visible(fn (InviteeUpload $record): bool =>
                        $record->isPhoto() && $record->hasStoredFile()
                    ),

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
                        $oldValues = $record->only([
                            'status',
                            'approved_by',
                            'approved_at',
                            'rejected_at',
                            'admin_note',
                        ]);

                        $record->approve(Auth::id());

                        AuditLogService::approved(
                            subject: $record,
                            eventId: $record->event_id,
                            description: ucfirst($record->type_label).' submission was approved.',
                            oldValues: $oldValues,
                            newValues: $record->only([
                                'status',
                                'approved_by',
                                'approved_at',
                                'rejected_at',
                                'admin_note',
                            ]),
                            metadata: [
                                'invitee_id' => $record->invitee_id,
                                'type' => $record->type,
                            ],
                        );

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
                        $oldValues = $record->only([
                            'status',
                            'approved_by',
                            'approved_at',
                            'rejected_at',
                            'admin_note',
                        ]);

                        $record->reject(Auth::id(), $data['admin_note'] ?? null);

                        AuditLogService::rejected(
                            subject: $record,
                            eventId: $record->event_id,
                            description: ucfirst($record->type_label).' submission was rejected.',
                            oldValues: $oldValues,
                            newValues: $record->only([
                                'status',
                                'approved_by',
                                'approved_at',
                                'rejected_at',
                                'admin_note',
                            ]),
                            metadata: [
                                'invitee_id' => $record->invitee_id,
                                'type' => $record->type,
                                'reason' => $data['admin_note'] ?? null,
                            ],
                        );

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
                        $oldValues = $record->only([
                            'status',
                            'approved_by',
                            'approved_at',
                            'rejected_at',
                            'admin_note',
                        ]);

                        $record->markPending();

                        AuditLogService::updated(
                            subject: $record,
                            eventId: $record->event_id,
                            description: ucfirst($record->type_label).' submission was returned to pending.',
                            oldValues: $oldValues,
                            newValues: $record->only([
                                'status',
                                'approved_by',
                                'approved_at',
                                'rejected_at',
                                'admin_note',
                            ]),
                            metadata: [
                                'invitee_id' => $record->invitee_id,
                                'type' => $record->type,
                                'transition' => 'pending',
                            ],
                        );

                        Notification::make()
                            ->title('Submission moved to pending')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make()
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Edit Submission')
                    ->modalDescription(
                        'Approved public content will automatically return to Pending when the wish, caption, photo, type, or invitee is changed.'
                    )
                    ->modalSubmitActionLabel('Save Changes')
                    ->visible(fn (): bool => $this->canManageSubmissions())
                    ->using(function (InviteeUpload $record, array $data): InviteeUpload {
                        $oldValues = $record->only([
                            'invitee_id',
                            'type',
                            'message',
                            'file_path',
                            'status',
                            'approved_by',
                            'approved_at',
                            'rejected_at',
                            'admin_note',
                        ]);

                        $wasApproved = $record->status === InviteeUpload::STATUS_APPROVED;

                        $contentFields = [
                            'invitee_id',
                            'type',
                            'message',
                            'file_path',
                        ];

                        $contentChanged = collect($contentFields)
                            ->contains(function (string $field) use ($record, $data): bool {
                                if (! array_key_exists($field, $data)) {
                                    return false;
                                }

                                $currentValue = $record->getAttribute($field);
                                $newValue = $data[$field];

                                if (is_array($currentValue) || is_array($newValue)) {
                                    return json_encode($currentValue) !== json_encode($newValue);
                                }

                                return (string) $currentValue !== (string) $newValue;
                            });

                        // Review status must only change through the dedicated,
                        // audited Approve, Reject, and Mark Pending actions.
                        unset($data['status']);

                        if ($wasApproved && $contentChanged) {
                            $data['status'] = InviteeUpload::STATUS_PENDING;
                            $data['approved_by'] = null;
                            $data['approved_at'] = null;
                            $data['rejected_at'] = null;
                        }

                        $record->update($data);
                        $record->refresh();

                        $returnedToPending = $wasApproved
                            && $contentChanged
                            && $record->status === InviteeUpload::STATUS_PENDING;

                        AuditLogService::updated(
                            subject: $record,
                            eventId: $record->event_id,
                            description: $returnedToPending
                                ? ucfirst($record->type_label).' submission was edited and returned to pending approval.'
                                : ucfirst($record->type_label).' submission was edited.',
                            oldValues: $oldValues,
                            newValues: $record->only([
                                'invitee_id',
                                'type',
                                'message',
                                'file_path',
                                'status',
                                'approved_by',
                                'approved_at',
                                'rejected_at',
                                'admin_note',
                            ]),
                            metadata: [
                                'invitee_id' => $record->invitee_id,
                                'type' => $record->type,
                                'content_changed' => $contentChanged,
                                'was_approved' => $wasApproved,
                                'returned_to_pending' => $returnedToPending,
                                'edited_by' => Auth::id(),
                            ],
                        );

                        Notification::make()
                            ->title(
                                $returnedToPending
                                    ? 'Changes saved — approval required'
                                    : 'Submission updated'
                            )
                            ->body(
                                $returnedToPending
                                    ? 'The approved content changed and has returned to Pending.'
                                    : 'The submission changes were saved successfully.'
                            )
                            ->color($returnedToPending ? 'warning' : 'success')
                            ->icon(
                                $returnedToPending
                                    ? 'heroicon-o-clock'
                                    : 'heroicon-o-check-circle'
                            )
                            ->send();

                        return $record;
                    }),

                    Tables\Actions\DeleteAction::make()
                        ->label('Delete')
                        ->visible(fn (): bool => $this->canManageSubmissions())
                        ->before(function (InviteeUpload $record): void {
                            AuditLogService::deleted(
                                subject: $record,
                                eventId: $record->event_id,
                                description: ucfirst($record->type_label).' submission was deleted.',
                                metadata: [
                                    'invitee_id' => $record->invitee_id,
                                    'type' => $record->type,
                                    'file_path' => $record->file_path,
                                ],
                            );

                            if ($record->isPhoto()) {
                                $record->deleteStoredFile();
                            }
                        }),
                ])
                    ->label('Manage')
                    ->icon('heroicon-m-ellipsis-horizontal')
                    ->color('primary')
                    ->iconButton()
                    ->tooltip('Manage submission'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('approve_selected')
                        ->label('Approve Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (): bool => $this->canManageSubmissions())
                        ->deselectRecordsAfterCompletion()
                        ->action(function ($records): void {
                            $records->each(function (InviteeUpload $record): void {
                                $oldValues = $record->only([
                                    'status',
                                    'approved_by',
                                    'approved_at',
                                    'rejected_at',
                                    'admin_note',
                                ]);

                                $record->approve(Auth::id());

                                AuditLogService::approved(
                                    subject: $record,
                                    eventId: $record->event_id,
                                    description: ucfirst($record->type_label).' submission was approved in bulk.',
                                    oldValues: $oldValues,
                                    newValues: $record->only([
                                        'status',
                                        'approved_by',
                                        'approved_at',
                                        'rejected_at',
                                        'admin_note',
                                    ]),
                                    metadata: [
                                        'invitee_id' => $record->invitee_id,
                                        'type' => $record->type,
                                        'bulk_action' => true,
                                    ],
                                );
                            });

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
                        ->deselectRecordsAfterCompletion()
                        ->action(function ($records): void {
                            $records->each(function (InviteeUpload $record): void {
                                $oldValues = $record->only([
                                    'status',
                                    'approved_by',
                                    'approved_at',
                                    'rejected_at',
                                    'admin_note',
                                ]);

                                $record->reject(Auth::id());

                                AuditLogService::rejected(
                                    subject: $record,
                                    eventId: $record->event_id,
                                    description: ucfirst($record->type_label).' submission was rejected in bulk.',
                                    oldValues: $oldValues,
                                    newValues: $record->only([
                                        'status',
                                        'approved_by',
                                        'approved_at',
                                        'rejected_at',
                                        'admin_note',
                                    ]),
                                    metadata: [
                                        'invitee_id' => $record->invitee_id,
                                        'type' => $record->type,
                                        'bulk_action' => true,
                                    ],
                                );
                            });

                            Notification::make()
                                ->title('Selected submissions rejected')
                                ->danger()
                                ->send();
                        }),

                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => $this->canManageSubmissions())
                        ->before(function ($records): void {
                            $records->each(function (InviteeUpload $record): void {
                                AuditLogService::deleted(
                                    subject: $record,
                                    eventId: $record->event_id,
                                    description: ucfirst($record->type_label).' submission was deleted in bulk.',
                                    metadata: [
                                        'invitee_id' => $record->invitee_id,
                                        'type' => $record->type,
                                        'file_path' => $record->file_path,
                                        'bulk_action' => true,
                                    ],
                                );

                                if ($record->isPhoto()) {
                                    $record->deleteStoredFile();
                                }
                            });
                        }),
                ]),
            ]);
    }
}
