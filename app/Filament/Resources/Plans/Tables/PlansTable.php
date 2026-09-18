<?php

namespace App\Filament\Resources\Plans\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable(),
                TextColumn::make('season')->label('Temporada')->searchable(),
                TextColumn::make('status')->label('Estado')->badge()->color(fn (string $state): string => match ($state) {
                    'PUBLICADO', 'EN_CURSO' => 'success',
                    'PAUSADO' => 'warning',
                    'CANCELADO' => 'danger',
                    'FINALIZADO' => 'gray',
                    default => 'info',
                }),
                TextColumn::make('starts_at')->label('Inicio')->date()->sortable(),
                TextColumn::make('ends_at')->label('Fin')->date()->sortable(),
                TextColumn::make('price')->label('Precio')->money(fn ($record) => $record->currency)->sortable(),
                TextColumn::make('progress_percent')->label('Avance')->suffix('%')->sortable(),
                TextColumn::make('updated_at')->label('Actualizado')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options([
                    'BORRADOR' => 'Borrador',
                    'PUBLICADO' => 'Publicado',
                    'EN_CURSO' => 'En curso',
                    'PAUSADO' => 'Pausado',
                    'FINALIZADO' => 'Finalizado',
                    'CANCELADO' => 'Cancelado',
                ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
