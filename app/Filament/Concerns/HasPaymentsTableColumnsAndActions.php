<?php

namespace App\Filament\Concerns;

use App\Actions\Payments\ApprovePayment;
use App\Actions\Payments\RejectPayment;
use App\Exceptions\PaymentActionException;
use App\Models\Payment;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Columnas y Actions (aprobar/rechazar/comprobante) compartidas por las 3
 * tablas de tipo "Pagos" del admin (F-018): el widget de /admin/orders
 * (F-013), el RelationManager de pagos de una orden especifica y el
 * RelationManager de pagos del expediente del representante. Una sola
 * fuente de verdad para no triplicar la logica de autorizacion de
 * aprobar/rechazar en 3 archivos.
 */
trait HasPaymentsTableColumnsAndActions
{
    /** @return array<TextColumn> */
    protected static function paymentTableColumns(): array
    {
        return [
            TextColumn::make('reference')->label('Referencia')->searchable(),
            TextColumn::make('amount')->label('Monto')->money('USD')->sortable(),
            TextColumn::make('method_name')->label('Método'),
            TextColumn::make('user.name')->label('Representante')->searchable(),
            TextColumn::make('order.uuid')->label('Orden')->limit(8)->placeholder('Sin orden'),
            TextColumn::make('status')->label('Estado')->badge()->color(fn (string $state): string => match ($state) {
                'APROBADO' => 'success',
                'RECHAZADO' => 'danger',
                default => 'warning',
            }),
            TextColumn::make('paid_at')->label('Fecha')->date()->sortable(),
            TextColumn::make('reviewedBy.name')->label('Revisor')->placeholder('—'),
        ];
    }

    /** @return array<Action> */
    protected static function paymentTableActions(): array
    {
        return [
            Action::make('receipt')
                ->label('Comprobante')
                ->icon('heroicon-o-paper-clip')
                ->visible(fn (Payment $record): bool => filled($record->receipt_path))
                ->action(fn (Payment $record) => response()->streamDownload(
                    function () use ($record): void {
                        echo Storage::disk('local')->get($record->receipt_path);
                    },
                    $record->receipt_name,
                )),
            Action::make('approve')
                ->label('Aprobar')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (Payment $record): bool => static::paymentCanReview($record, 'approve'))
                ->action(fn (Payment $record) => static::paymentRunAction(fn () => app(ApprovePayment::class)->handle($record, Auth::guard('admin')->user()))),
            Action::make('reject')
                ->label('Rechazar')
                ->color('danger')
                ->schema([
                    Textarea::make('reason')->label('Motivo')->required(),
                ])
                ->visible(fn (Payment $record): bool => static::paymentCanReview($record, 'reject'))
                ->action(fn (array $data, Payment $record) => static::paymentRunAction(fn () => app(RejectPayment::class)->handle($record, $data['reason'], Auth::guard('admin')->user()))),
        ];
    }

    private static function paymentCanReview(Payment $record, string $ability): bool
    {
        $admin = Auth::guard('admin')->user();

        return $admin !== null && $record->status === 'PENDIENTE_VERIFICACION' && $admin->can($ability, $record);
    }

    private static function paymentRunAction(Closure $callback): void
    {
        try {
            $callback();
            Notification::make()->title('Listo')->success()->send();
        } catch (PaymentActionException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
        }
    }
}
