<?php

namespace App\Filament\Resources\Plans\Pages;

use App\Actions\Plans\CancelPlan;
use App\Actions\Plans\FinishPlan;
use App\Actions\Plans\PausePlan;
use App\Actions\Plans\PublishPlan;
use App\Actions\Plans\ResumePlan;
use App\Actions\Plans\StartPlan;
use App\Actions\Plans\UpdatePlanProgress;
use App\Exceptions\PlanTransitionException;
use App\Filament\Resources\Plans\PlanResource;
use App\Models\Plan;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Auth;

class EditPlan extends EditRecord
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->transitionAction('publish', 'Publicar', PublishPlan::class, fn (Plan $plan): bool => ! in_array($plan->status, Plan::LOCKED_STATUSES, true)),
            $this->transitionAction('start', 'Iniciar', StartPlan::class, fn (Plan $plan): bool => $plan->status === 'PUBLICADO'),
            $this->reasonAction('pause', 'Pausar', PausePlan::class, fn (Plan $plan): bool => in_array($plan->status, ['PUBLICADO', 'EN_CURSO'], true)),
            $this->transitionAction('resume', 'Reanudar', ResumePlan::class, fn (Plan $plan): bool => $plan->status === 'PAUSADO'),
            $this->reasonAction('finish', 'Finalizar', FinishPlan::class, fn (Plan $plan): bool => $plan->status === 'EN_CURSO'),
            $this->reasonAction('cancel', 'Cancelar', CancelPlan::class, fn (Plan $plan): bool => ! in_array($plan->status, ['FINALIZADO', 'CANCELADO'], true), 'danger'),
            $this->updateProgressAction(),
            DeleteAction::make(),
        ];
    }

    private function canManage(): bool
    {
        $admin = Auth::guard('admin')->user();

        return $admin !== null && $admin->can('update', $this->getRecord());
    }

    private function transitionAction(string $name, string $label, string $actionClass, Closure $visibleWhen): Action
    {
        return Action::make($name)
            ->label($label)
            ->requiresConfirmation()
            ->visible(fn (): bool => $this->canManage() && $visibleWhen($this->getRecord()))
            ->action(function () use ($actionClass): void {
                $this->runTransition(fn () => app($actionClass)->handle($this->getRecord()));
            });
    }

    private function reasonAction(string $name, string $label, string $actionClass, Closure $visibleWhen, string $color = 'gray'): Action
    {
        return Action::make($name)
            ->label($label)
            ->color($color)
            ->schema([
                Textarea::make('reason')->label('Motivo')->required(),
            ])
            ->visible(fn (): bool => $this->canManage() && $visibleWhen($this->getRecord()))
            ->action(function (array $data) use ($actionClass): void {
                $this->runTransition(fn () => app($actionClass)->handle($this->getRecord(), $data['reason']));
            });
    }

    private function updateProgressAction(): Action
    {
        return Action::make('updateProgress')
            ->label('Actualizar avance')
            ->color('gray')
            ->schema([
                Select::make('progress_mode')->label('Modo')->options([
                    'AUTO' => 'Automático (según días completados)',
                    'MANUAL' => 'Manual',
                ])->default('AUTO')->live()->required(),
                TextInput::make('progress_percent')->label('Porcentaje')->numeric()->minValue(0)->maxValue(100)
                    ->visible(fn (Get $get): bool => $get('progress_mode') === 'MANUAL'),
                TextInput::make('progress_label')->label('Etiqueta')
                    ->visible(fn (Get $get): bool => $get('progress_mode') === 'MANUAL'),
                Textarea::make('reason')->label('Motivo del cambio manual')
                    ->visible(fn (Get $get): bool => $get('progress_mode') === 'MANUAL')
                    ->required(fn (Get $get): bool => $get('progress_mode') === 'MANUAL'),
            ])
            ->visible(fn (): bool => $this->canManage())
            ->action(function (array $data): void {
                $this->runTransition(fn () => app(UpdatePlanProgress::class)->handle($this->getRecord(), $data, Auth::guard('admin')->user()));
            });
    }

    private function runTransition(Closure $callback): void
    {
        try {
            $callback();

            Notification::make()->title('Listo')->success()->send();

            $this->fillForm();
        } catch (PlanTransitionException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
        }
    }
}
