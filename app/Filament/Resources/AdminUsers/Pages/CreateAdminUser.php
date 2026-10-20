<?php

namespace App\Filament\Resources\AdminUsers\Pages;

use App\Actions\Admin\AdminUsers\CreateAdminUser as CreateAdminUserAction;
use App\Exceptions\AdminUserActionException;
use App\Filament\Resources\AdminUsers\AdminUserResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateAdminUser extends CreateRecord
{
    protected static string $resource = AdminUserResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(CreateAdminUserAction::class)->handle(Auth::guard('admin')->user(), $data);
        } catch (AdminUserActionException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
            $this->halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
