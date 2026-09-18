<?php

namespace App\Filament\Resources\Enrollments\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * `enrollments` todavia no tiene relacion explicita con `orders`
 * (02-datos-y-migraciones.md lo deja como pendiente); por eso no se muestra
 * aqui "orden y pagos relacionados" pese a que 03-panel-y-recursos.md lo
 * pide para este Resource.
 */
class EnrollmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Placeholder::make('participant')
                    ->label('Participante')
                    ->content(fn ($record): string => $record?->participant?->name ?? '—'),
                Placeholder::make('representative')
                    ->label('Representante')
                    ->content(fn ($record): string => $record?->participant?->user?->name ?? '—'),
                TextInput::make('plan_name')->label('Plan')->required(),
                TextInput::make('session_name')->label('Sesión')->required(),
                Select::make('plan_type')->label('Modalidad')->options([
                    'INDIVIDUAL' => 'Individual',
                    'HERMANOS' => 'Hermanos',
                ])->required(),
                Select::make('status')->label('Estado')->options([
                    'PENDIENTE_PAGO' => 'Pendiente de pago',
                    'PAGADA' => 'Pagada',
                    'CANCELADA' => 'Cancelada',
                ])->required(),
                TextInput::make('total_amount')->label('Total')->numeric()->prefix('$')->required(),
                TextInput::make('sibling_discount')->label('Descuento hermanos')->numeric()->prefix('$'),
                DatePicker::make('starts_at')->label('Inicio')->required(),
                DatePicker::make('ends_at')->label('Fin')->required(),
            ]);
    }
}
