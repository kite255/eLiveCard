<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GeneratedCardResource\Pages;
use App\Models\GeneratedCard;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GeneratedCardResource extends Resource
{
    protected static ?string $model = GeneratedCard::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'Card Management';

    protected static ?string $navigationLabel = 'Generated Cards';

    protected static ?string $modelLabel = 'Generated Card';

    protected static ?string $pluralModelLabel = 'Generated Cards';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->heading('Generated Cards')
            ->description('View personalized cards generated for invitees.')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['event', 'invitee', 'cardTemplate'])
                ->latest()
            )
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->columns([
                Tables\Columns\TextColumn::make('invitee.name')
                    ->label('Invitee')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::SemiBold)
                    ->description(fn (GeneratedCard $record): string => $record->invitee?->phone ?? 'No phone'),

                Tables\Columns\TextColumn::make('event.title')
                    ->label('Event')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('cardTemplate.name')
                    ->label('Template')
                    ->limit(30)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => GeneratedCard::statuses()[$state] ?? ucfirst($state ?? 'Generated'))
                    ->color(fn (?string $state): string => match ($state) {
                        GeneratedCard::STATUS_SENT => 'success',
                        GeneratedCard::STATUS_FAILED => 'danger',
                        GeneratedCard::STATUS_GENERATED => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('file_path')
                    ->label('Card File')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? 'Available' : 'Missing')
                    ->badge()
                    ->color(fn (?string $state): string => filled($state) ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('generated_at')
                    ->label('Generated At')
                    ->dateTime('M d, Y h:i A')
                    ->sortable(),

                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Sent At')
                    ->dateTime('M d, Y h:i A')
                    ->placeholder('Not sent')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_id')
                    ->label('Event')
                    ->relationship('event', 'title')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(GeneratedCard::statuses()),
            ])
            ->actions([
                Tables\Actions\Action::make('view_card')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn (GeneratedCard $record): ?string => $record->file_url)
                    ->openUrlInNewTab()
                    ->visible(fn (GeneratedCard $record): bool => filled($record->file_path)),

                Tables\Actions\Action::make('download_card')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn (GeneratedCard $record): ?string => $record->file_url)
                    ->openUrlInNewTab()
                    ->visible(fn (GeneratedCard $record): bool => filled($record->file_path)),

                Tables\Actions\DeleteAction::make()
                    ->label('Delete'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-identification')
            ->emptyStateHeading('No generated cards yet')
            ->emptyStateDescription('Generate cards from an active card template first.');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGeneratedCards::route('/'),
        ];
    }
}