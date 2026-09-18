<?php

namespace App\Filament\Resources\Plans\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

/**
 * El estado operativo (status, paused_from_status, status_reason,
 * progress_*) NO se edita aqui: cambia solo a traves de las Actions de
 * ciclo de vida (PublishPlan, PausePlan, etc.) expuestas como header
 * actions en EditPlan, para que la regla de "un solo plan operativo" y los
 * motivos obligatorios no se puedan saltar escribiendo directo en el campo.
 */
class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Plan')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('General')
                            ->schema([
                                TextInput::make('name')->required()->columnSpanFull(),
                                TextInput::make('venue')->label('Sede')->required(),
                                TextInput::make('season')->label('Temporada')->required(),
                                TextInput::make('date_label')->label('Etiqueta de fechas'),
                                TextInput::make('duration_label')->label('Duración'),
                                DatePicker::make('starts_at')->label('Inicio')->required(),
                                DatePicker::make('ends_at')->label('Fin')->required(),
                                TextInput::make('schedule')->label('Horario'),
                                TextInput::make('age_min')->label('Edad mínima')->numeric()->required(),
                                TextInput::make('age_max')->label('Edad máxima')->numeric()->required(),
                                TextInput::make('whatsapp')->label('WhatsApp operativo'),
                                FileUpload::make('cover_url')->label('Portada')->image(),
                                Textarea::make('description')->label('Descripción')->columnSpanFull(),
                            ])
                            ->columns(2),
                        Tab::make('Precio y cupos')
                            ->schema([
                                TextInput::make('price')->label('Precio')->numeric()->prefix('$')->required(),
                                TextInput::make('currency')->label('Moneda')->required()->default('USD'),
                                TextInput::make('capacity')->label('Capacidad')->numeric()->required(),
                                TextInput::make('available_slots')->label('Cupos disponibles')->numeric()->required(),
                                Toggle::make('sibling_discount_enabled')
                                    ->label('Descuento por hermanos activo')
                                    ->default(true)
                                    ->columnSpanFull(),
                                TextInput::make('sibling_discount_min_participants')
                                    ->label('Mínimo de participantes de la familia')
                                    ->numeric()
                                    ->minValue(2)
                                    ->required()
                                    ->default(2),
                                TextInput::make('sibling_discount_amount')
                                    ->label('Descuento por participante')
                                    ->numeric()
                                    ->prefix('$')
                                    ->required()
                                    ->default(20),
                            ])
                            ->columns(2),
                        Tab::make('Mini market')
                            ->schema([
                                Repeater::make('mini_market')
                                    ->label('Ítems del mini market')
                                    ->schema([
                                        TextInput::make('nombre')->required(),
                                        TextInput::make('precio')->numeric()->prefix('$'),
                                    ])
                                    ->columns(2)
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Estado y avance')
                            ->schema([
                                Placeholder::make('status')
                                    ->label('Estado actual')
                                    ->content(fn ($record): string => $record?->status ?? 'BORRADOR (sin guardar)'),
                                Placeholder::make('status_reason')
                                    ->label('Motivo del último cambio')
                                    ->content(fn ($record): string => $record?->status_reason ?? '—'),
                                Placeholder::make('progress_percent')
                                    ->label('Avance')
                                    ->content(fn ($record): string => $record ? "{$record->progress_mode} · {$record->progress_percent}%" : '—'),
                                Placeholder::make('progress_updated_at')
                                    ->label('Último cambio de avance')
                                    ->content(fn ($record): string => $record?->progress_updated_at?->format('Y-m-d H:i') ?? '—'),
                            ])
                            ->columns(2),
                    ]),
            ]);
    }
}
