<?php

namespace App\Filament\Resources\PaymentMethods\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentMethodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Código')->searchable(),
                TextColumn::make('name')->label('Nombre')->searchable(),
                TextColumn::make('type')->label('Tipo'),
                IconColumn::make('active')->label('Activo')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
