<?php

namespace App\Policies;

use App\Models\AdminUser;
use App\Models\Product;

class ProductPolicy
{
    public function viewAny(AdminUser $adminUser): bool
    {
        return $adminUser->can('products.manage');
    }

    public function view(AdminUser $adminUser, Product $product): bool
    {
        return $adminUser->can('products.manage');
    }

    public function create(AdminUser $adminUser): bool
    {
        return $adminUser->can('products.manage');
    }

    public function update(AdminUser $adminUser, Product $product): bool
    {
        return $adminUser->can('products.manage');
    }

    public function delete(AdminUser $adminUser, Product $product): bool
    {
        return $adminUser->can('products.manage');
    }
}
