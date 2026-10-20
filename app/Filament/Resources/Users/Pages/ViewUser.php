<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Filament\Support\AdminDeleteActions;
use Filament\Resources\Pages\ViewRecord;

/**
 * Solo lectura: el expediente del representante se ve completo (formulario
 * deshabilitado + RelationManagers), no se edita desde el admin.
 *
 * F-054: "Eliminar" (solo SUPER_ADMIN) usa DeleteRepresentative: conserva
 * ordenes y pagos sin representante y vuelve al listado.
 */
class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AdminDeleteActions::representative(redirectToIndex: true),
        ];
    }
}
