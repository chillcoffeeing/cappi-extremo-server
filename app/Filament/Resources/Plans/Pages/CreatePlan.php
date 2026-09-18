<?php

namespace App\Filament\Resources\Plans\Pages;

use App\Filament\Resources\Plans\PlanResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePlan extends CreateRecord
{
    protected static string $resource = PlanResource::class;

    /**
     * El formulario no expone `status`: todo plan nuevo nace BORRADOR y solo
     * pasa a PUBLICADO a traves de la Action `PublishPlan` (unica que
     * respeta la regla de "un solo plan operativo"). El default de la
     * columna es 'PUBLICADO' por una migracion anterior a Fase 2, asi que
     * hay que forzarlo aqui explicitamente.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = 'BORRADOR';

        return $data;
    }
}
