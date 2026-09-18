<?php

namespace App\Filament\Resources\PaymentMethods\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PaymentMethodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('code')->label('Código')->required()->unique(ignoreRecord: true),
                TextInput::make('name')->label('Nombre')->required(),
                Select::make('type')->label('Tipo')->options([
                    'ZELLE' => 'Zelle',
                    'EFECTIVO' => 'Efectivo',
                    'TRANSFERENCIA_BS' => 'Transferencia Bs',
                    'OTRO' => 'Otro',
                ])->required(),
                Toggle::make('active')->label('Activo')->default(true),
                Textarea::make('description')->label('Descripción')->required()->columnSpanFull(),
                Textarea::make('data.instrucciones')->label('Instrucciones')->columnSpanFull(),
                Repeater::make('data.detalle')
                    ->label('Datos bancarios')
                    ->schema([
                        TextInput::make('etiqueta')->label('Etiqueta')->required(),
                        TextInput::make('valor')->label('Valor')->required(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
