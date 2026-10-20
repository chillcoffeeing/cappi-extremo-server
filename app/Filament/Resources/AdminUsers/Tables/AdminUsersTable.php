<?php

namespace App\Filament\Resources\AdminUsers\Tables;

use App\Actions\Admin\AdminUsers\AdminUserRules;
use App\Actions\Admin\AdminUsers\ChangeAdminPassword;
use App\Actions\Admin\AdminUsers\UpdateAdminUser;
use App\Exceptions\AdminUserActionException;
use App\Filament\Support\AdminDeleteActions;
use App\Models\AdminUser;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class AdminUsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('roles')->addSelect([
                // Ultimo login del panel: lo registra AppServiceProvider en activity_log.
                'last_login_at' => DB::table('activity_log')
                    ->selectRaw('MAX(created_at)')
                    ->where('log_name', 'admin.auth')
                    ->where('description', 'login')
                    ->where('causer_type', (new AdminUser)->getMorphClass())
                    ->whereColumn('causer_id', 'admin_users.id'),
            ]))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable()->sortable()
                    ->description(fn (AdminUser $record): ?string => Auth::guard('admin')->user()?->is($record) ? 'Tu cuenta' : null),
                TextColumn::make('email')->label('Email')->searchable()->copyable(),
                TextColumn::make('role')->label('Rol')->badge()
                    ->getStateUsing(fn (AdminUser $record): ?string => $record->roles->first()?->name)
                    ->color(fn (?string $state): string => $state === 'SUPER_ADMIN' ? 'danger' : 'gray'),
                IconColumn::make('is_active')->label('Activa')->boolean(),
                TextColumn::make('last_login_at')->label('Último acceso')->dateTime('d/m/Y H:i')->placeholder('Nunca')->sortable(),
                TextColumn::make('created_at')->label('Creada')->date('d/m/Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')->label('Rol')->options(array_combine(array_keys(AdminUserRules::ROLES), array_keys(AdminUserRules::ROLES)))
                    ->query(fn (Builder $query, array $data): Builder => $data['value']
                        ? $query->whereHas('roles', fn (Builder $roles) => $roles->where('name', $data['value']))
                        : $query),
                TernaryFilter::make('is_active')->label('Activa'),
            ])
            ->recordActions([
                EditAction::make(),
                static::toggleActiveAction(),
                static::changePasswordAction(),
                AdminDeleteActions::adminUser(),
            ]);
    }

    public static function toggleActiveAction(): Action
    {
        return Action::make('toggleActive')
            ->label(fn (AdminUser $record): string => $record->is_active ? 'Desactivar' : 'Activar')
            ->icon(fn (AdminUser $record) => $record->is_active ? Heroicon::OutlinedNoSymbol : Heroicon::OutlinedCheckCircle)
            ->color(fn (AdminUser $record): string => $record->is_active ? 'warning' : 'success')
            ->visible(fn (AdminUser $record): bool => static::canManage($record) && ! Auth::guard('admin')->user()?->is($record))
            ->requiresConfirmation()
            ->modalHeading(fn (AdminUser $record): string => ($record->is_active ? 'Desactivar' : 'Activar').": {$record->email}")
            ->modalDescription(fn (AdminUser $record): string => $record->is_active
                ? 'La cuenta no podrá entrar al panel y, si tiene una sesión abierta, se cierra. No se borra nada y se puede volver a activar.'
                : 'La cuenta vuelve a poder entrar al panel con su rol y su contraseña actuales.')
            ->action(function (AdminUser $record): void {
                abort_unless(static::canManage($record), 403);

                try {
                    app(UpdateAdminUser::class)->handle(Auth::guard('admin')->user(), $record, [
                        'name' => $record->name,
                        'email' => $record->email,
                        'role' => (string) $record->getRoleNames()->first(),
                        'is_active' => ! $record->is_active,
                    ]);
                    Notification::make()->title($record->refresh()->is_active ? 'Cuenta activada' : 'Cuenta desactivada')->success()->send();
                } catch (AdminUserActionException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                }
            });
    }

    public static function changePasswordAction(): Action
    {
        return Action::make('changePassword')
            ->label('Cambiar contraseña')
            ->icon(Heroicon::OutlinedKey)
            ->color('gray')
            ->visible(fn (AdminUser $record): bool => static::canManage($record))
            ->modalHeading(fn (AdminUser $record): string => "Cambiar contraseña: {$record->email}")
            ->modalDescription('Las demás sesiones abiertas de esta cuenta se cierran. Comunica la contraseña por un canal seguro.')
            ->modalSubmitActionLabel('Guardar contraseña')
            ->schema([
                TextInput::make('password')->label('Nueva contraseña')->password()->revealable()
                    ->required()->confirmed()->rule(Password::min(10)->letters()->numbers())
                    ->helperText('Mínimo 10 caracteres, con letras y números.'),
                TextInput::make('password_confirmation')->label('Repite la contraseña')->password()->revealable()->required(),
            ])
            ->action(function (AdminUser $record, array $data): void {
                abort_unless(static::canManage($record), 403);

                $actor = Auth::guard('admin')->user();
                app(ChangeAdminPassword::class)->handle($actor, $record, $data['password']);

                // La propia sesion sigue viva: AuthenticateSession compara este hash.
                if ($actor->is($record)) {
                    session()->put('password_hash_'.Auth::getDefaultDriver(), $record->refresh()->getAuthPassword());
                }

                Notification::make()->title('Contraseña actualizada')->success()->send();
            });
    }

    private static function canManage(AdminUser $record): bool
    {
        return (bool) Auth::guard('admin')->user()?->can('update', $record);
    }
}
