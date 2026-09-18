<?php

namespace App\Filament\Resources\Plans\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * "El orden administrativo debe ser el orden mostrado en el portal"
 * (04-plan-activo-y-contenido.md): se ordena por `sort_order`.
 *
 * Las actividades de cada dia se editan en linea con un Repeater ligado a la
 * relacion `activities`, en vez de un RelationManager anidado (Filament no
 * soporta relation managers dentro de relation managers).
 */
class PlanDaysRelationManager extends RelationManager
{
    protected static string $relationship = 'planDays';

    protected static ?string $title = 'Días';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('day_number')->label('# Día')->numeric()->required(),
                DatePicker::make('date')->label('Fecha')->required(),
                TextInput::make('title')->label('Título')->required()->columnSpanFull(),
                Textarea::make('description')->label('Descripción')->columnSpanFull(),
                TextInput::make('location')->label('Ubicación'),
                Select::make('status')->label('Estado')->options([
                    'PLANIFICADO' => 'Planificado',
                    'EN_CURSO' => 'En curso',
                    'COMPLETADO' => 'Completado',
                    'CANCELADO' => 'Cancelado',
                ])->default('PLANIFICADO')->required(),
                TextInput::make('sort_order')->label('Orden')->numeric()->default(0),
                Toggle::make('is_visible')->label('Visible en el portal')->default(true),
                Repeater::make('activities')
                    ->relationship()
                    ->label('Actividades')
                    ->columnSpanFull()
                    ->orderColumn('sort_order')
                    ->schema([
                        TextInput::make('title')->label('Título')->required(),
                        TimePicker::make('starts_at')->label('Inicio'),
                        TimePicker::make('ends_at')->label('Fin'),
                        TextInput::make('location')->label('Ubicación'),
                        Select::make('status')->label('Estado')->options([
                            'PLANIFICADA' => 'Planificada',
                            'EN_CURSO' => 'En curso',
                            'COMPLETADA' => 'Completada',
                            'CANCELADA' => 'Cancelada',
                        ])->default('PLANIFICADA')->required(),
                        Textarea::make('description')->label('Descripción')->columnSpanFull(),
                        Textarea::make('requirements')->label('Requisitos')->columnSpanFull(),
                    ])
                    ->columns(4),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('day_number')->label('#'),
                TextColumn::make('date')->label('Fecha')->date(),
                TextColumn::make('title')->label('Título')->searchable(),
                TextColumn::make('status')->label('Estado')->badge(),
                TextColumn::make('activities_count')->label('Actividades')->counts('activities'),
                IconColumn::make('is_visible')->label('Visible')->boolean(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
