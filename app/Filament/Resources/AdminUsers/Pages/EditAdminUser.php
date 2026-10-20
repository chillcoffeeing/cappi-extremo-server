<?php

namespace App\Filament\Resources\AdminUsers\Pages;

use App\Actions\Admin\AdminUsers\UpdateAdminUser;
use App\Exceptions\AdminUserActionException;
use App\Filament\Resources\AdminUsers\AdminUserResource;
use App\Filament\Resources\AdminUsers\Tables\AdminUsersTable;
use App\Filament\Support\AdminDeleteActions;
use App\Models\AdminUser;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EditAdminUser extends EditRecord
{
    protected static string $resource = AdminUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AdminUsersTable::changePasswordAction(),
            AdminDeleteActions::adminUser(redirectToIndex: true),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var AdminUser $record */
        $record = $this->getRecord();
        $data['role'] = $record->getRoleNames()->first();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            /** @var AdminUser $record */
            return app(UpdateAdminUser::class)->handle(Auth::guard('admin')->user(), $record, $data);
        } catch (AdminUserActionException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
            $this->halt();
        }
    }

    protected function getRedirectUrl(): ?string
    {
        return static::getResource()::getUrl('index');
    }
}
