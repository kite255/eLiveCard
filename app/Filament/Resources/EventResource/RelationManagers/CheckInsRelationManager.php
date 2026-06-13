<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class CheckInsRelationManager extends RelationManager
{
    protected static string $relationship = 'checkIns';

    protected static ?string $title = 'Check-ins';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invitee.name')->label('Invitee')->searchable(),
                Tables\Columns\TextColumn::make('guests_checked_in')->label('Guests'),
                Tables\Columns\TextColumn::make('remaining_guests')->label('Remaining'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('checked_in_at')->dateTime(),
            ]);
    }
}