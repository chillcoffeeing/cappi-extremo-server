<?php

namespace App\Policies;

use App\Models\AdminUser;
use App\Models\PaymentMethod;

class PaymentMethodPolicy
{
    public function viewAny(AdminUser $adminUser): bool
    {
        return $adminUser->can('payment_methods.manage');
    }

    public function view(AdminUser $adminUser, PaymentMethod $paymentMethod): bool
    {
        return $adminUser->can('payment_methods.manage');
    }

    public function create(AdminUser $adminUser): bool
    {
        return $adminUser->can('payment_methods.manage');
    }

    public function update(AdminUser $adminUser, PaymentMethod $paymentMethod): bool
    {
        return $adminUser->can('payment_methods.manage');
    }

    public function delete(AdminUser $adminUser, PaymentMethod $paymentMethod): bool
    {
        return $adminUser->can('payment_methods.manage');
    }
}
