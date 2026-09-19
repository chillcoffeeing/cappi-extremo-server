<?php

namespace App\Filament\Resources\PaymentMethods\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * F-023: `type` deja de ser un catalogo cerrado de proveedores
 * (ZELLE/EFECTIVO/TRANSFERENCIA_BS/OTRO) y pasa a describir COMPORTAMIENTO:
 * DIRECTO (se reporta el pago en la app, como hoy) o COORDINADO_REMOTO (el
 * primer pago se coordina fuera de la app, ej. WhatsApp). El proveedor real
 * sigue viviendo en `code`/`name`, sin catalogo cerrado.
 */
class PaymentMethodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('code')->label('Código')->required()->unique(ignoreRecord: true)
                    ->helperText('Identificador público del método: el portal y el onboarding lo usan como "metodoId" para elegirlo y vincular los pagos reportados. Cambiarlo en un método ya usado puede romper referencias guardadas.'),
                TextInput::make('name')->label('Nombre')->required(),
                Select::make('type')->label('Tipo')->options([
                    'DIRECTO' => 'Directo (se reporta en la app)',
                    'COORDINADO_REMOTO' => 'Coordinado remoto (fuera de la app, ej. WhatsApp)',
                ])->required()->live()
                    ->helperText('"Directo" exige comprobante y referencia dentro de la app (ver "Instrucciones"/"Datos bancarios" abajo). "Coordinado remoto" omite eso y en su lugar muestra el aviso de WhatsApp (ver campos "WhatsApp" abajo) para coordinar el primer pago fuera de la app.'),
                Toggle::make('active')->label('Activo')->default(true),
                Textarea::make('description')->label('Descripción')->required()->columnSpanFull(),
                Textarea::make('data.instrucciones')
                    ->label('Instrucciones')
                    ->helperText('Se muestra junto a los datos bancarios en el paso "Pago" del onboarding y en /portal/pagos.')
                    ->columnSpanFull()
                    ->visible(fn (Get $get): bool => $get('type') === 'DIRECTO'),
                Repeater::make('data.detalle')
                    ->label('Datos bancarios')
                    ->helperText('Cada fila (etiqueta/valor) se muestra como una lista de datos bancarios en el paso "Pago" del onboarding y en /portal/pagos.')
                    ->schema([
                        TextInput::make('etiqueta')->label('Etiqueta')->required(),
                        TextInput::make('valor')->label('Valor')->required(),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->visible(fn (Get $get): bool => $get('type') === 'DIRECTO'),
                Textarea::make('data.whatsapp.mensajeOnboarding')
                    ->label('Mensaje del banner en el onboarding')
                    ->helperText('Se muestra en el paso "Pago" del wizard cuando se elige este método.')
                    ->columnSpanFull()
                    ->visible(fn (Get $get): bool => $get('type') === 'COORDINADO_REMOTO'),
                Textarea::make('data.whatsapp.mensajeDashboard')
                    ->label('Mensaje del aviso en el dashboard "Inicio"')
                    ->helperText('Se muestra mientras el primer pago siga pendiente de coordinar.')
                    ->columnSpanFull()
                    ->visible(fn (Get $get): bool => $get('type') === 'COORDINADO_REMOTO'),
                TextInput::make('data.whatsapp.linkTexto')
                    ->label('Texto del link')
                    ->helperText('Texto visible del botón de WhatsApp, en el mismo banner que "Mensaje del banner en el onboarding". Si se deja vacío, se usa "Escríbenos por WhatsApp".')
                    ->placeholder('Ej. Escríbenos por WhatsApp')
                    ->visible(fn (Get $get): bool => $get('type') === 'COORDINADO_REMOTO'),
                TextInput::make('data.whatsapp.link')
                    ->label('Link de WhatsApp (opcional)')
                    ->helperText('Si se deja vacío, se arma con el WhatsApp operativo del plan activo.')
                    ->url()
                    ->placeholder('https://wa.me/584121234567')
                    ->visible(fn (Get $get): bool => $get('type') === 'COORDINADO_REMOTO'),
            ]);
    }
}
