<?php

namespace App\Filament\Resources\Enrollments\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EnrollmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('participant.name')->label('Participante')->searchable(),
                TextColumn::make('participant.user.name')->label('Representante')->searchable(),
                TextColumn::make('plan_name')->label('Plan')->searchable(),
                TextColumn::make('session_name')->label('Sesión'),
                TextColumn::make('plan_type')->label('Modalidad'),
                TextColumn::make('status')->label('Estado')->badge()->color(fn (string $state): string => match ($state) {
                    'PAGADA' => 'success',
                    'CANCELADA' => 'danger',
                    default => 'warning',
                }),
                TextColumn::make('total_amount')->label('Total')->money('USD')->sortable(),
                TextColumn::make('sibling_discount')->label('Descuento')->money('USD'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options([
                    'PENDIENTE_PAGO' => 'Pendiente de pago',
                    'PAGADA' => 'Pagada',
                    'CANCELADA' => 'Cancelada',
                ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
