<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Widgets\PaymentsWidget;
use Filament\Resources\Pages\ListRecords;

/**
 * "Pagos" fusionado en "Ordenes" (F-013): la tabla de ordenes va primero
 * (contenido normal de la pagina) y la de pagos abajo, como widget de pie.
 */
class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getFooterWidgets(): array
    {
        return [
            PaymentsWidget::class,
        ];
    }
}
