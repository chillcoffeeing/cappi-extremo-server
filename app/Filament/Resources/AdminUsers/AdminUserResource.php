<?php

namespace App\Filament\Resources\AdminUsers;

use App\Filament\Resources\AdminUsers\Pages\CreateAdminUser;
use App\Filament\Resources\AdminUsers\Pages\EditAdminUser;
use App\Filament\Resources\AdminUsers\Pages\ListAdminUsers;
use App\Filament\Resources\AdminUsers\Schemas\AdminUserForm;
use App\Filament\Resources\AdminUsers\Tables\AdminUsersTable;
use App\Models\AdminUser;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * F-055 — cuentas del panel admin. Solo SUPER_ADMIN (`admin_users.manage`,
 * AdminUserPolicy): para cualquier otro rol el recurso no aparece en el menu
 * y sus URLs responden 403. Las reglas (no quitarse el acceso a uno mismo,
 * siempre un SUPER_ADMIN activo) viven en App\Actions\Admin\AdminUsers.
 */
class AdminUserResource extends Resource
{
    protected static ?string $model = AdminUser::class;

    protected static ?string $modelLabel = 'usuario admin';

    protected static ?string $pluralModelLabel = 'usuarios admin';

    protected static ?string $navigationLabel = 'Usuarios admin';

    protected static ?int $navigationSort = 90;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    public static function form(Schema $schema): Schema
    {
        return AdminUserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdminUsersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdminUsers::route('/'),
            'create' => CreateAdminUser::route('/create'),
            'edit' => EditAdminUser::route('/{record}/edit'),
        ];
    }
}
