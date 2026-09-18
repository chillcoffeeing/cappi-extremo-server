<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Concerns\HasPaymentsTableColumnsAndActions;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

/**
 * Pagos del representante, dentro de /admin/users/{id}/details. Mismas
 * columnas y Actions (aprobar/rechazar/comprobante) que el widget de pagos
 * del listado de Ordenes y que el RelationManager de pagos de una orden
 * especifica (F-018) — ver HasPaymentsTableColumnsAndActions.
 */
class PaymentsRelationManager extends RelationManager
{
    use HasPaymentsTableColumnsAndActions;

    protected static string $relationship = 'payments';

    protected static ?string $title = 'Pagos';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reference')
            ->columns(static::paymentTableColumns())
            ->headerActions([])
            ->recordActions(static::paymentTableActions());
    }
}
