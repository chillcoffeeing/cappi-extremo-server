<?php

namespace App\Filament\Resources\OnboardingDrafts\Pages;

use App\Actions\Onboarding\MarkOnboardingAbandoned;
use App\Actions\Onboarding\ReactivateOnboardingFollowUp;
use App\Exceptions\OnboardingAdminActionException;
use App\Filament\Resources\OnboardingDrafts\OnboardingDraftResource;
use App\Models\OnboardingDraft;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

/**
 * Solo lectura: los datos del draft no se editan desde el admin. Las 3
 * Actions de estado se mantienen (decision confirmada): Contactar,
 * Marcar abandonado y Reactivar seguimiento.
 */
class ViewOnboardingDraft extends ViewRecord
{
    protected static string $resource = OnboardingDraftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('contact')
                ->label('Contactar representante')
                ->icon('heroicon-o-envelope')
                ->url(fn (OnboardingDraft $record): string => 'mailto:'.$record->user?->email)
                ->visible(fn (): bool => $this->canManage()),
            Action::make('markAbandoned')
                ->label('Marcar abandonado')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (OnboardingDraft $record): bool => $this->canManage() && $record->status !== 'ABANDONADO')
                ->action(function (): void {
                    $this->runAction(fn () => app(MarkOnboardingAbandoned::class)->handle($this->getRecord()));
                }),
            Action::make('reactivate')
                ->label('Reactivar seguimiento')
                ->color('success')
                ->visible(fn (OnboardingDraft $record): bool => $this->canManage() && $record->status === 'ABANDONADO')
                ->action(function (): void {
                    $this->runAction(fn () => app(ReactivateOnboardingFollowUp::class)->handle($this->getRecord()));
                }),
        ];
    }

    private function canManage(): bool
    {
        $admin = Auth::guard('admin')->user();

        return $admin !== null && $admin->can('update', $this->getRecord());
    }

    private function runAction(\Closure $callback): void
    {
        try {
            $callback();
            Notification::make()->title('Listo')->success()->send();
            $this->fillForm();
        } catch (OnboardingAdminActionException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
        }
    }
}
