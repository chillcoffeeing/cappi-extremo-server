<?php

namespace App\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;

/**
 * "Actividad reciente" (Fase 5). Solo visible con permiso `audit.view`.
 */
class ActividadReciente extends TableWidget
{
    protected static bool $isLazy = false;

    /**
     * Al final del dashboard: es el widget menos accionable (una bitácora),
     * los KPIs van primero.
     */
    protected static ?int $sort = 100;

    /**
     * `Widget` declara `$columnSpan = 1` por defecto; el Dashboard usa un
     * grid de 2 columnas, asi que sin esto el widget quedaba a la mitad del
     * ancho del contenedor (F-018).
     */
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) Auth::guard('admin')->user()?->can('audit.view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Actividad reciente')
            ->query(fn (): Builder => Activity::query()->latest())
            ->paginated([5, 10, 25])
            ->columns([
                TextColumn::make('created_at')->label('Fecha')->dateTime()->sortable(),
                TextColumn::make('log_name')->label('Módulo')->badge(),
                TextColumn::make('description')->label('Acción'),
                TextColumn::make('causer.name')->label('Actor')->placeholder('Sistema'),
                TextColumn::make('subject_type')->label('Sobre')->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '—'),
            ]);
    }
}
