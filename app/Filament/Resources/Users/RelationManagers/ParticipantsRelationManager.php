<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Solo lectura desde el expediente del representante: la ficha se edita en
 * ParticipantResource, no aqui.
 */
class ParticipantsRelationManager extends RelationManager
{
    protected static string $relationship = 'participants';

    protected static ?string $title = 'Participantes';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->label('Nombre'),
                TextColumn::make('birth_date')->label('Nacimiento')->date(),
                IconColumn::make('data_completed')->label('Ficha completa')->boolean(),
                TextColumn::make('reviewed_at')->label('Revisado')->dateTime()->placeholder('No'),
            ])
            ->headerActions([])
            ->recordActions([
                ViewAction::make()->url(fn ($record): string => route('filament.admin.resources.participants.view', $record)),
            ]);
    }
}
