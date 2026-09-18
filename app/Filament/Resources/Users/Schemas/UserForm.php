<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * El admin ve y corrige datos de contacto del representante, pero no crea
 * ni elimina cuentas (se registran solo desde el portal) ni edita su
 * password. Ver UserPolicy: sin create()/delete().
 */
class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')->label('Nombre')->required(),
                TextInput::make('last_name')->label('Apellido'),
                TextInput::make('email')->label('Email')->email()->required(),
                TextInput::make('phone')->label('Teléfono')->tel(),
                TextInput::make('identification')->label('Identificación'),
                Select::make('onboarding_status')->label('Estado de onboarding')->options([
                    'INCOMPLETO' => 'Incompleto',
                    'LISTO_PARA_CONFIRMAR' => 'Listo para confirmar',
                    'COMPLETADO' => 'Completado',
                ])->disabled(),
                Placeholder::make('last_login_at')
                    ->label('Último acceso')
                    ->content(fn ($record): string => $record?->last_login_at?->format('Y-m-d H:i') ?? 'Nunca'),
            ]);
    }
}
