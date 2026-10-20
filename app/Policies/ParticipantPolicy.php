<?php

namespace App\Policies;

use App\Models\AdminUser;
use App\Models\Participant;

class ParticipantPolicy
{
    public function viewAny(AdminUser $adminUser): bool
    {
        return $adminUser->can('participants.view');
    }

    public function view(AdminUser $adminUser, Participant $participant): bool
    {
        return $adminUser->can('participants.view');
    }

    public function update(AdminUser $adminUser, Participant $participant): bool
    {
        return $adminUser->can('participants.edit');
    }

    /** F-053: solo SUPER_ADMIN. Ver App\Actions\Admin\DeleteParticipant. */
    public function delete(AdminUser $adminUser, Participant $participant): bool
    {
        return $adminUser->can('records.delete');
    }
}
