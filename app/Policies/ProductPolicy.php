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

    /** F-054 (C-5): habilita la accion masiva "Eliminar" del listado. */
    public function deleteAny(AdminUser $adminUser): bool
    {
        return $adminUser->can('products.manage');
    }

    public function delete(AdminUser $adminUser, Product $product): bool
    {
        return $adminUser->can('products.manage');
    }
}
