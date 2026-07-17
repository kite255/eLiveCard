<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Exports\EventAuditLogsExport;
use App\Models\AuditLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AuditLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'auditLogs';

    protected static ?string $title = 'Activity Log';

    protected static ?string $modelLabel = 'Activity';

    protected static ?string $pluralModelLabel = 'Activity Log';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }

        if (method_exists($user, 'canAccessEvent')) {
            return (bool) $user->canAccessEvent($ownerRecord);
        }

        return (int) ($ownerRecord->user_id ?? 0) === (int) $user->id;
    }

    protected function canExportAuditLogs(): bool
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
            && (int) ($this->getOwnerRecord()->user_id ?? 0) === (int) $user->id
        ) {
            return (bool) ($user->canViewReports() ?? false);
        }

        return false;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Activity Details')
                    ->schema([
                        Forms\Components\TextInput::make('action')
                            ->label('Action')
                            ->disabled(),

                        Forms\Components\TextInput::make('user.name')
                            ->label('Performed By')
                            ->disabled(),

                        Forms\Components\TextInput::make('subject_label')
                            ->label('Record Type')
                            ->disabled(),

                        Forms\Components\TextInput::make('subject_id')
                            ->label('Record ID')
                            ->disabled(),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(4)
                            ->disabled()
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('ip_address')
                            ->label('IP Address')
                            ->disabled(),

                        Forms\Components\TextInput::make('created_at')
                            ->label('Date and Time')
                            ->disabled(),

                        Forms\Components\Textarea::make('user_agent')
                            ->label('Browser / Device')
                            ->rows(3)
                            ->disabled()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Previous Values')
                    ->schema([
                        Forms\Components\KeyValue::make('old_values')
                            ->label('')
                            ->disabled()
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (?AuditLog $record): bool =>
                        filled($record?->old_values)
                    )
                    ->collapsible(),

                Forms\Components\Section::make('New Values')
                    ->schema([
                        Forms\Components\KeyValue::make('new_values')
                            ->label('')
                            ->disabled()
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (?AuditLog $record): bool =>
                        filled($record?->new_values)
                    )
                    ->collapsible(),

                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\KeyValue::make('metadata')
                            ->label('')
                            ->disabled()
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (?AuditLog $record): bool =>
                        filled($record?->metadata)
                    )
                    ->collapsible(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Event Activity Log')
            ->description('Read-only history of administrative, communication, card, RSVP, check-in, export, and system activity for this event.')
            ->recordTitleAttribute('description')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with('user')
                ->where('event_id', $this->getOwnerRecord()->getKey())
            )
            ->defaultSort('created_at', 'desc')
            ->searchPlaceholder('Search action, description, IP address, or user')
            ->searchDebounce('500ms')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateIcon('heroicon-o-shield-check')
            ->emptyStateHeading('No activity recorded yet')
            ->emptyStateDescription('Important event actions will appear here after audit logging is enabled.')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date & Time')
                    ->dateTime('d M Y, h:i A')
                    ->sortable()
                    ->since()
                    ->description(fn (AuditLog $record): string =>
                        $record->created_at?->format('d M Y, h:i:s A') ?? '-'
                    ),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->placeholder('System')
                    ->weight(FontWeight::SemiBold)
                    ->icon('heroicon-o-user-circle')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('action')
                    ->label('Action')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            $this->formatActionLabel($state)
                    )
                    ->color(fn (?string $state): string => $this->actionColor($state))
                    ->icon(fn (?string $state): string => $this->actionIcon($state))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('action_group')
                    ->label('Module')
                    ->badge()
                    ->getStateUsing(
                        fn (AuditLog $record): string =>
                            $this->actionGroup($record->action)
                    )
                    ->color(
                        fn (string $state): string =>
                            $this->groupColor($state)
                    )
                    ->toggleable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->wrap()
                    ->limit(90)
                    ->tooltip(fn (AuditLog $record): ?string =>
                        filled($record->description)
                            ? $record->description
                            : null
                    ),

                Tables\Columns\TextColumn::make('subject_label')
                    ->label('Record')
                    ->badge()
                    ->color('gray')
                    ->description(fn (AuditLog $record): ?string =>
                        $record->subject_id
                            ? 'ID: '.$record->subject_id
                            : null
                    )
                    ->toggleable(),

                Tables\Columns\TextColumn::make('metadata_summary')
                    ->label('Details')
                    ->getStateUsing(
                        fn (AuditLog $record): string =>
                            $this->metadataSummary($record)
                    )
                    ->limit(55)
                    ->placeholder('-')
                    ->tooltip(
                        fn (AuditLog $record): ?string =>
                            filled($this->metadataSummary($record))
                                ? $this->metadataSummary($record)
                                : null
                    )
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->placeholder('-')
                    ->copyable()
                    ->copyMessage('IP address copied')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('user_agent')
                    ->label('Browser / Device')
                    ->limit(45)
                    ->placeholder('-')
                    ->tooltip(fn (AuditLog $record): ?string =>
                        filled($record->user_agent)
                            ? $record->user_agent
                            : null
                    )
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('module')
                    ->label('Module')
                    ->options([
                        'event' => 'Events',
                        'invitee' => 'Invitees',
                        'card_template' => 'Card Templates',
                        'card_generation' => 'Card Generation',
                        'generated_card' => 'Generated Cards',
                        'message_template' => 'Message Templates',
                        'message_log' => 'Message Logs',
                        'sms' => 'SMS',
                        'whatsapp' => 'WhatsApp',
                        'rsvp' => 'RSVP',
                        'check_in' => 'Check-ins',
                        'invitee_upload' => 'Wishes & Photos',
                        'export' => 'Exports',
                        'system' => 'System',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $module = $data['value'] ?? null;

                        if (! $module) {
                            return $query;
                        }

                        return $query->where(
                            'action',
                            'like',
                            $module.'.%'
                        );
                    }),

                Tables\Filters\SelectFilter::make('action')
                    ->label('Action')
                    ->options(fn (): array => AuditLog::query()
                        ->where('event_id', $this->getOwnerRecord()->getKey())
                        ->whereNotNull('action')
                        ->distinct()
                        ->orderBy('action')
                        ->pluck('action', 'action')
                        ->mapWithKeys(fn (string $action): array => [
                            $action => str($action)
                                ->replace(['.', '_', '-'], ' ')
                                ->headline()
                                ->toString(),
                        ])
                        ->all())
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('user_id')
                    ->label('User')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('subject_type')
                    ->label('Record Type')
                    ->options(fn (): array => AuditLog::query()
                        ->where('event_id', $this->getOwnerRecord()->getKey())
                        ->whereNotNull('subject_type')
                        ->distinct()
                        ->orderBy('subject_type')
                        ->pluck('subject_type', 'subject_type')
                        ->mapWithKeys(fn (string $type): array => [
                            $type => class_basename($type),
                        ])
                        ->all())
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('created_at')
                    ->label('Date Range')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From')
                            ->native(false),

                        Forms\Components\DatePicker::make('until')
                            ->label('Until')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $query, $date): Builder =>
                                    $query->whereDate('created_at', '>=', $date)
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $query, $date): Builder =>
                                    $query->whereDate('created_at', '<=', $date)
                            );
                    }),

                Tables\Filters\Filter::make('today')
                    ->label('Today')
                    ->query(fn (Builder $query): Builder =>
                        $query->whereDate('created_at', today())
                    )
                    ->toggle(),

                Tables\Filters\Filter::make('system_actions')
                    ->label('System Actions')
                    ->query(fn (Builder $query): Builder =>
                        $query->whereNull('user_id')
                    )
                    ->toggle(),

                Tables\Filters\Filter::make('successful_actions')
                    ->label('Successful')
                    ->query(
                        fn (Builder $query): Builder =>
                            $query->where(function (Builder $query): void {
                                $query
                                    ->where('action', 'like', '%.created')
                                    ->orWhere('action', 'like', '%.updated')
                                    ->orWhere('action', 'like', '%.approved')
                                    ->orWhere('action', 'like', '%.completed')
                                    ->orWhere('action', 'like', '%.sent')
                                    ->orWhere('action', 'like', '%.delivered')
                                    ->orWhere('action', 'like', '%.exported')
                                    ->orWhere('action', 'like', '%success%');
                            })
                    )
                    ->toggle(),

                Tables\Filters\Filter::make('failed_actions')
                    ->label('Failed')
                    ->query(
                        fn (Builder $query): Builder =>
                            $query->where(function (Builder $query): void {
                                $query
                                    ->where('action', 'like', '%failed%')
                                    ->orWhere('action', 'like', '%rejected%')
                                    ->orWhere('action', 'like', '%invalid%')
                                    ->orWhere('action', 'like', '%missing%')
                                    ->orWhere('action', 'like', '%error%');
                            })
                    )
                    ->toggle(),
            ])
            ->filtersFormColumns(2)
            ->headerActions([
                Tables\Actions\Action::make('export_activity_log')
                    ->label('Export Activity Log')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn (): bool => $this->canExportAuditLogs())
                    ->action(
                        fn (): BinaryFileResponse =>
                            $this->downloadAuditLogs()
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('View Details')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->modalHeading(
                        fn (AuditLog $record): string =>
                            filled($record->action_label ?? null)
                                ? $record->action_label
                                : $this->formatActionLabel($record->action)
                    )
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->bulkActions([])
            ->poll('20s');
    }

    protected function actionColor(?string $action): string
    {
        $action = strtolower((string) $action);

        return match (true) {
            str_contains($action, 'approved'),
            str_contains($action, 'created'),
            str_contains($action, 'completed'),
            str_contains($action, 'delivered'),
            str_contains($action, 'checked_in'),
            str_contains($action, 'success') => 'success',

            str_contains($action, 'sent'),
            str_contains($action, 'exported'),
            str_contains($action, 'viewed'),
            str_contains($action, 'opened'),
            str_contains($action, 'received') => 'info',

            str_contains($action, 'updated'),
            str_contains($action, 'pending'),
            str_contains($action, 'started'),
            str_contains($action, 'queued'),
            str_contains($action, 'attempted'),
            str_contains($action, 'duplicate') => 'warning',

            str_contains($action, 'rejected'),
            str_contains($action, 'deleted'),
            str_contains($action, 'failed'),
            str_contains($action, 'invalid'),
            str_contains($action, 'missing'),
            str_contains($action, 'error') => 'danger',

            default => 'gray',
        };
    }

    protected function actionIcon(?string $action): string
    {
        $action = strtolower((string) $action);

        return match (true) {
            str_contains($action, 'approved') => 'heroicon-o-check-circle',
            str_contains($action, 'created') => 'heroicon-o-plus-circle',
            str_contains($action, 'checked_in') => 'heroicon-o-qr-code',
            str_contains($action, 'sent') => 'heroicon-o-paper-airplane',
            str_contains($action, 'delivered') => 'heroicon-o-check-badge',
            str_contains($action, 'read') => 'heroicon-o-eye',
            str_contains($action, 'exported') => 'heroicon-o-arrow-down-tray',
            str_contains($action, 'viewed'),
            str_contains($action, 'opened') => 'heroicon-o-eye',
            str_contains($action, 'updated') => 'heroicon-o-pencil-square',
            str_contains($action, 'started'),
            str_contains($action, 'queued'),
            str_contains($action, 'attempted') => 'heroicon-o-clock',
            str_contains($action, 'rejected') => 'heroicon-o-x-circle',
            str_contains($action, 'deleted') => 'heroicon-o-trash',
            str_contains($action, 'failed'),
            str_contains($action, 'invalid'),
            str_contains($action, 'missing'),
            str_contains($action, 'error') => 'heroicon-o-exclamation-triangle',
            default => 'heroicon-o-information-circle',
        };
    }

    protected function formatActionLabel(?string $action): string
    {
        return str($action ?? 'activity')
            ->replace(['.', '_', '-'], ' ')
            ->headline()
            ->toString();
    }

    protected function actionGroup(?string $action): string
    {
        $prefix = str((string) $action)
            ->before('.')
            ->replace('_', ' ')
            ->headline()
            ->toString();

        return $prefix !== '' ? $prefix : 'System';
    }

    protected function groupColor(string $group): string
    {
        return match (strtolower($group)) {
            'events', 'event' => 'primary',
            'invitees', 'invitee' => 'info',
            'card templates', 'card template',
            'card generation', 'generated cards', 'generated card' => 'warning',
            'sms' => 'info',
            'whatsapp' => 'success',
            'rsvp' => 'success',
            'check ins', 'check in' => 'success',
            'invitee uploads', 'invitee upload' => 'warning',
            'message templates', 'message template',
            'message logs', 'message log' => 'gray',
            'exports', 'export' => 'info',
            default => 'gray',
        };
    }

    protected function metadataSummary(AuditLog $record): string
    {
        $metadata = collect($record->metadata ?? [])
            ->reject(
                fn ($value, $key): bool =>
                    in_array((string) $key, [
                        'response',
                        'provider_response',
                        'provider_request',
                        'payload',
                        'report',
                        'old_values',
                        'new_values',
                    ], true)
            )
            ->take(4)
            ->map(function ($value, $key): string {
                if (is_bool($value)) {
                    $value = $value ? 'Yes' : 'No';
                } elseif (is_array($value)) {
                    $value = count($value).' item(s)';
                } elseif (is_object($value)) {
                    $value = 'Object';
                }

                return str((string) $key)
                    ->replace('_', ' ')
                    ->headline()
                    ->append(': ', (string) $value)
                    ->toString();
            })
            ->implode(' • ');

        return $metadata;
    }

    protected function downloadAuditLogs(): BinaryFileResponse
    {
        if (! $this->canExportAuditLogs()) {
            abort(403);
        }

        $event = $this->getOwnerRecord();

        $eventName = str(
            $event->title
            ?? $event->name
            ?? 'event'
        )
            ->slug('-')
            ->toString();

        $filename = $eventName
            .'-activity-log-'
            .now()->format('Ymd-His')
            .'.xlsx';

        return Excel::download(
            new EventAuditLogsExport(
                (int) $event->getKey()
            ),
            $filename
        );
    }

}
