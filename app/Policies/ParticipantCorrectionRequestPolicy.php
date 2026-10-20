<?php

namespace App\Policies;

use App\Models\AdminUser;
use App\Models\ParticipantCorrectionRequest;

class ParticipantCorrectionRequestPolicy
{
    public function viewAny(AdminUser $adminUser): bool
    {
        return $adminUser->can('participants.view');
    }

    public function view(AdminUser $adminUser, ParticipantCorrectionRequest $request): bool
    {
        return $adminUser->can('participants.view');
    }

    /**
     * F-062 (api-A-6): solicitar y resolver una correccion exige
     * `participants.edit` (OPERACIONES) o `corrections.manage` (SOPORTE,
     * que gestiona correcciones sin editar participantes). LECTURA no tiene
     * ninguno de los dos.
     */
    public function create(AdminUser $adminUser): bool
    {
        return self::canManage($adminUser);
    }

    /** F-062 (api-A-6): usada por la accion "Resolver". */
    public function update(AdminUser $adminUser, ParticipantCorrectionRequest $request): bool
    {
        return self::canManage($adminUser);
    }

    private static function canManage(AdminUser $adminUser): bool
    {
        return $adminUser->can('participants.edit') || $adminUser->can('corrections.manage');
    }

    /** F-053: solo SUPER_ADMIN. */
    public function delete(AdminUser $adminUser, ParticipantCorrectionRequest $request): bool
    {
        return $adminUser->can('records.delete');
    }
}
