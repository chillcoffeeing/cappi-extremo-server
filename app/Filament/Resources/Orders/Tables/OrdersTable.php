<?php

namespace App\Filament\Resources\Orders\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('ordered_at', 'desc')
            ->columns([
                TextColumn::make('user.name')->label('Representante')->searchable(),
                TextColumn::make('is_registration')->label('Tipo')->formatStateUsing(fn (bool $state): string => $state ? 'Inscripción' : 'Tienda')->badge(),
                TextColumn::make('total')->label('Total')->money('USD')->sortable(),
                TextColumn::make('paid')->label('Abonado')->money('USD'),
                TextColumn::make('status')->label('Estado')->badge()->color(fn (string $state): string => match ($state) {
                    'PAGADA' => 'success',
                    'CANCELADA' => 'danger',
                    default => 'warning',
                }),
                TextColumn::make('ordered_at')->label('Fecha')->date()->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_registration')->label('Tipo')
                    ->trueLabel('Inscripción')->falseLabel('Tienda'),
                SelectFilter::make('status')->label('Estado')->options([
                    'PENDIENTE_PAGO' => 'Pendiente de pago',
                    'PAGADA' => 'Pagada',
                    'CANCELADA' => 'Cancelada',
                ]),
            ])
            ->recordActions([
                ViewAction::make()->label('Ver detalle'),
            ]);
    }
}
