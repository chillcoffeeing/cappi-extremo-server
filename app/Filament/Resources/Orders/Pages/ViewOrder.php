<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Actions\Orders\CancelOrder;
use App\Exceptions\PaymentActionException;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

/**
 * Solo lectura: "el admin no debe editar" en el detalle de orden. Cancelar
 * sigue siendo una Action de negocio (CancelOrder), no una edicion de
 * campos. Sin "Exportar" -- se quitaron todas las exportaciones del admin
 * hasta que se pida una especifica nueva.
 */
class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('cancel')
                ->label('Cancelar orden')
                ->color('danger')
                ->schema([
                    Textarea::make('reason')->label('Motivo')->required(),
                ])
                ->visible(fn (Order $record): bool => $this->canManage() && ! in_array($record->status, ['PAGADA', 'CANCELADA'], true))
                ->action(function (array $data): void {
                    try {
                        app(CancelOrder::class)->handle($this->getRecord(), $data['reason'], Auth::guard('admin')->user());
                        Notification::make()->title('Orden cancelada')->success()->send();
                        $this->fillForm();
                    } catch (PaymentActionException $exception) {
                        Notification::make()->title($exception->getMessage())->danger()->send();
                    }
                }),
        ];
    }

    private function canManage(): bool
    {
        $admin = Auth::guard('admin')->user();

        return $admin !== null && $admin->can('update', $this->getRecord());
    }
}
