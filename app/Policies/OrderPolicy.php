<?php

namespace App\Policies;

use App\Models\AdminUser;
use App\Models\Order;

class OrderPolicy
{
    public function viewAny(AdminUser $adminUser): bool
    {
        return $adminUser->can('orders.view');
    }

    public function view(AdminUser $adminUser, Order $order): bool
    {
        return $adminUser->can('orders.view');
    }

    public function update(AdminUser $adminUser, Order $order): bool
    {
        return $adminUser->can('orders.manage');
    }

    // F-053: sin delete(). Las ordenes nunca se borran (registro
    // administrativo); al borrar un representante quedan con user_uuid NULL.
}
