<?php

namespace App\Filament\Resources\OnboardingDrafts\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OnboardingDraftsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('Representante')->searchable(),
                TextColumn::make('user.email')->label('Email')->searchable(),
                TextColumn::make('status')->label('Estado')->badge()->color(fn (string $state): string => match ($state) {
                    'COMPLETADO' => 'success',
                    'ABANDONADO' => 'danger',
                    'LISTO_PARA_CONFIRMAR' => 'warning',
                    default => 'gray',
                }),
                TextColumn::make('updated_at')->label('Última actividad')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options([
                    'INCOMPLETO' => 'Incompleto',
                    'LISTO_PARA_CONFIRMAR' => 'Listo para confirmar',
                    'COMPLETADO' => 'Completado',
                    'ABANDONADO' => 'Abandonado',
                ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
