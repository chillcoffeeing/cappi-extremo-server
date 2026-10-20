<?php

namespace App\Filament\Resources\PaymentMethods\Pages;

use App\Filament\Resources\PaymentMethods\PaymentMethodResource;
use App\Filament\Support\AdminDeleteActions;
use Filament\Resources\Pages\EditRecord;

class EditPaymentMethod extends EditRecord
{
    protected static string $resource = PaymentMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AdminDeleteActions::paymentMethod(redirectToIndex: true),
        ];
    }
}
