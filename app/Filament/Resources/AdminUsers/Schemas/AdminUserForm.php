<?php

namespace App\Filament\Resources\AdminUsers\Schemas;

use App\Actions\Admin\AdminUsers\AdminUserRules;
use App\Models\AdminUser;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class AdminUserForm
{
    public static function configure(Schema $schema): Schema
    {
        $isSelf = fn (?AdminUser $record): bool => $record !== null && Auth::guard('admin')->user()?->is($record);

        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')->label('Nombre')->required()->maxLength(255),
                TextInput::make('email')->label('Email')->email()->required()->maxLength(255)
                    ->unique(AdminUser::class, 'email', ignoreRecord: true)
                    ->helperText('Es el usuario para entrar al panel.'),
                Select::make('role')->label('Rol')->required()->native(false)
                    ->options(AdminUserRules::ROLES)
                    ->disabled($isSelf)
                    ->dehydrated()
                    ->helperText(fn (?AdminUser $record): ?string => $isSelf($record)
                        ? 'No puedes cambiar tu propio rol.'
                        : 'Un rol por cuenta. Solo Super admin gestiona usuarios admin y puede borrar registros.'),
                Toggle::make('is_active')->label('Activa')->default(true)
                    ->disabled($isSelf)
                    ->dehydrated()
                    ->helperText(fn (?AdminUser $record): string => $isSelf($record)
                        ? 'No puedes desactivar tu propia cuenta.'
                        : 'Una cuenta inactiva no puede entrar, y si tiene una sesión abierta se cierra.'),
                TextInput::make('password')->label('Contraseña')->password()->revealable()
                    ->required()->confirmed()
                    ->rule(Password::min(10)->letters()->numbers())
                    ->helperText('Mínimo 10 caracteres, con letras y números.')
                    ->visibleOn('create'),
                TextInput::make('password_confirmation')->label('Repite la contraseña')->password()->revealable()
                    ->required()->dehydrated(false)
                    ->visibleOn('create'),
            ]);
    }
}
