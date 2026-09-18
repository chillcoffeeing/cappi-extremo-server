<?php

namespace App\Filament\Resources\Participants\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ParticipantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable(),
                TextColumn::make('user.name')->label('Representante')->searchable(),
                TextColumn::make('birth_date')->label('Nacimiento')->date()->sortable(),
                TextColumn::make('gender')->label('Género'),
                IconColumn::make('data_completed')->label('Ficha completa')->boolean(),
                IconColumn::make('reviewed_at')->label('Revisado')->boolean()->getStateUsing(fn ($record): bool => $record->reviewed_at !== null),
            ])
            ->filters([
                TernaryFilter::make('data_completed')->label('Ficha completa'),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
