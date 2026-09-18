<?php

namespace App\Filament\Resources\Orders\Widgets;

use App\Filament\Concerns\HasPaymentsTableColumnsAndActions;
use App\Models\Payment;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * "Pagos" fusionado dentro de "Ordenes" (F-013): ya no es un Resource de
 * navegacion propio, vive como widget en el footer de ListOrders, debajo de
 * la tabla de ordenes. Aprobar/rechazar/ver comprobante siguen igual que en
 * la version anterior (ex Payments\Tables\PaymentsTable).
 *
 * Columnas y Actions viven en HasPaymentsTableColumnsAndActions (F-018),
 * compartidas con los RelationManagers de pagos de Orders y Users.
 */
class PaymentsWidget extends TableWidget
{
    use HasPaymentsTableColumnsAndActions;

    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    /**
     * `Widget` declara `$columnSpan = 1` por defecto; el footer de ListOrders
     * usa un grid de 2 columnas (`getFooterWidgetsColumns()`), asi que sin
     * esto el widget quedaba a la mitad del ancho del contenedor (F-018).
     */
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) Auth::guard('admin')->user()?->can('payments.view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Pagos')
            ->query(fn (): Builder => Payment::query())
            ->defaultSort('paid_at')
            ->columns(static::paymentTableColumns())
            ->filters([
                SelectFilter::make('status')->label('Estado')->default('PENDIENTE_VERIFICACION')->options([
                    'PENDIENTE_VERIFICACION' => 'Pendiente de verificación',
                    'APROBADO' => 'Aprobado',
                    'RECHAZADO' => 'Rechazado',
                ]),
            ])
            ->recordActions(static::paymentTableActions());
    }
}
