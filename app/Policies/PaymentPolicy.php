<?php

namespace App\Policies;

use App\Models\AdminUser;
use App\Models\Payment;

class PaymentPolicy
{
    public function viewAny(AdminUser $adminUser): bool
    {
        return $adminUser->can('payments.view');
    }

    public function view(AdminUser $adminUser, Payment $payment): bool
    {
        return $adminUser->can('payments.view');
    }

    /**
     * Usada por la Action `ApprovePayment` (Fase 4).
     */
    public function approve(AdminUser $adminUser, Payment $payment): bool
    {
        return $adminUser->can('payments.approve');
    }

    /**
     * Usada por la Action `RejectPayment` (Fase 4).
     */
    public function reject(AdminUser $adminUser, Payment $payment): bool
    {
        return $adminUser->can('payments.reject');
    }
}
