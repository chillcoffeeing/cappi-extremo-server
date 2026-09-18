<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

/**
 * "Ver detalle" (03-panel-y-recursos.md): de solo lectura. Cancelar es una
 * Action separada (CancelOrder), no una edicion de campos. Los items se
 * muestran con RepeatableEntry (Infolist), no Repeater (Forms): un Repeater
 * deshabilitado igual conserva el aspecto de tarjetas editables con
 * controles de agregar/quitar; RepeatableEntry es la version de solo
 * lectura pensada para esto.
 */
class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Placeholder::make('representative')
                    ->label('Representante')
                    ->content(fn ($record): string => $record?->user?->name ?? '—'),
                Placeholder::make('type')
                    ->label('Tipo')
                    ->content(fn ($record): string => $record?->is_registration ? 'Inscripción' : 'Tienda'),
                Placeholder::make('status')
                    ->label('Estado')
                    ->content(fn ($record): string => $record?->status ?? '—'),
                Placeholder::make('cancellation_reason')
                    ->label('Motivo de cancelación')
                    ->content(fn ($record): string => $record?->cancellation_reason ?? '—'),
                Placeholder::make('total')
                    ->label('Total')
                    ->content(fn ($record): string => '$'.number_format((float) ($record?->total ?? 0), 2)),
                Placeholder::make('paid')
                    ->label('Abonado')
                    ->content(fn ($record): string => '$'.number_format((float) ($record?->paid ?? 0), 2)),
                Placeholder::make('balance')
                    ->label('Saldo')
                    ->content(fn ($record): string => '$'.number_format(max((float) ($record?->total ?? 0) - (float) ($record?->paid ?? 0), 0), 2)),
                RepeatableEntry::make('items')
                    ->label('Ítems')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('nombre')->label('Nombre'),
                        TextEntry::make('variante')->label('Variante')->placeholder('—'),
                        TextEntry::make('qty')->label('Cantidad'),
                        TextEntry::make('precio')->label('Precio')->money('USD'),
                    ])
                    ->columns(4),
            ]);
    }
}
