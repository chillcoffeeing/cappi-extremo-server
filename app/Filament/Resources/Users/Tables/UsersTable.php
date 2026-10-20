<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Support\AdminDeleteActions;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable(['name', 'last_name']),
                TextColumn::make('email')->label('Email')->searchable(),
                TextColumn::make('phone')->label('Teléfono')->searchable(),
                TextColumn::make('identification')->label('Identificación')->searchable(),
                TextColumn::make('onboarding_status')->label('Onboarding')->badge(),
                TextColumn::make('last_login_at')->label('Último acceso')->dateTime()->sortable()->placeholder('Nunca'),
                TextColumn::make('participants_count')->label('Participantes')->counts('participants'),
                TextColumn::make('orders_count')->label('Órdenes')->counts('orders'),
                TextColumn::make('payments_count')->label('Pagos')->counts('payments'),
            ])
            ->filters([
                SelectFilter::make('onboarding_status')->label('Onboarding')->options([
                    'INCOMPLETO' => 'Incompleto',
                    'LISTO_PARA_CONFIRMAR' => 'Listo para confirmar',
                    'COMPLETADO' => 'Completado',
                ]),
            ])
            ->recordActions([
                ViewAction::make(),
                // F-054: solo SUPER_ADMIN (records.delete).
                AdminDeleteActions::representative(),
            ]);
    }
}
