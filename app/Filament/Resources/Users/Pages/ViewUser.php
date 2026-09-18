<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\ViewRecord;

/**
 * Solo lectura: el expediente del representante se ve completo (formulario
 * deshabilitado + RelationManagers), no se edita desde el admin.
 */
class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;
}
