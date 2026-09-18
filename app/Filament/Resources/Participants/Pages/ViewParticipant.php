<?php

namespace App\Filament\Resources\Participants\Pages;

use App\Actions\Participants\MarkParticipantReviewed;
use App\Filament\Resources\Participants\ParticipantResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

/**
 * Solo lectura: la ficha no se edita desde el admin, el mecanismo de cambio
 * es "solicitar correccion" (el representante corrige desde el portal).
 * 'Marcar revisión' sigue siendo una Action de negocio. Sin 'Exportar ficha':
 * se quitaron todas las exportaciones del admin hasta que se pida una
 * especifica nueva.
 */
class ViewParticipant extends ViewRecord
{
    protected static string $resource = ParticipantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markReviewed')
                ->label('Marcar revisión')
                ->color('success')
                ->visible(fn (): bool => $this->canManage())
                ->action(function (): void {
                    app(MarkParticipantReviewed::class)->handle($this->getRecord(), Auth::guard('admin')->user());
                    Notification::make()->title('Ficha marcada como revisada')->success()->send();
                    $this->fillForm();
                }),
        ];
    }

    private function canManage(): bool
    {
        $admin = Auth::guard('admin')->user();

        return $admin !== null && $admin->can('update', $this->getRecord());
    }
}
