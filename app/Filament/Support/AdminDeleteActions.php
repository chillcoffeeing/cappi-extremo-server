<?php

namespace App\Filament\Support;

use App\Actions\Admin\DeleteEnrollment;
use App\Actions\Admin\DeleteParticipant;
use App\Actions\Admin\DeleteRepresentative;
use App\Filament\Resources\Enrollments\EnrollmentResource;
use App\Filament\Resources\Participants\ParticipantResource;
use App\Filament\Resources\PaymentMethods\PaymentMethodResource;
use App\Filament\Resources\Plans\PlanResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Enrollment;
use App\Models\OnboardingDraft;
use App\Models\Participant;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\PlanAnnouncement;
use App\Models\PlanDay;
use App\Models\Product;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

/**
 * F-054 — botones "Eliminar" del backoffice (solo SUPER_ADMIN, permiso
 * `records.delete` via la policy `delete` de cada modelo).
 *
 * Son Actions propias (no el DeleteAction de Filament) porque:
 * - el borrado lo hacen las Actions de dominio de F-053
 *   (App\Actions\Admin\Delete*): un `$record->delete()` plano se saltaria el
 *   snapshot del representante, la limpieza de fotos y el recalculo del
 *   descuento hermanos;
 * - los RelationManagers del expediente del representante son de solo
 *   lectura en la pagina View, y Filament deniega ahi cualquier DeleteAction.
 *
 * Todas se llaman `delete`, sirven igual en fila de tabla, en
 * RelationManager y en el header de la ficha (`$redirectToIndex = true`).
 * Siempre en dos pasos: boton "Eliminar" + modal que explica que se borra y
 * que se conserva. Planes/Productos/Metodos de pago/Dias/Avisos usan sus
 * permisos de gestion (policy `delete`), no `records.delete`.
 * Ordenes y Pagos nunca tienen Eliminar.
 */
class AdminDeleteActions
{
    public static function representative(bool $redirectToIndex = false): Action
    {
        return static::base()
            ->visible(fn (User $record): bool => static::can($record))
            ->modalHeading(fn (User $record): string => 'Eliminar representante: '.trim($record->name.' '.($record->last_name ?? '')))
            ->modalDescription(fn (User $record): Htmlable => static::representativeImpact($record))
            ->schema(fn (User $record): array => [
                TextInput::make('confirmation')
                    ->label('Para confirmar, escribe el email del representante: '.static::representativeConfirmationText($record))
                    ->required()
                    ->autocomplete(false)
                    ->rules([
                        fn (): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                            if (mb_strtolower(trim((string) $value)) !== mb_strtolower(static::representativeConfirmationText($record))) {
                                $fail('El texto no coincide con el email del representante.');
                            }
                        },
                    ]),
            ])
            ->action(function (User $record, Action $action) use ($redirectToIndex): void {
                abort_unless(static::can($record), 403);

                $result = app(DeleteRepresentative::class)->handle($record);
                $deleted = $result['deleted'];
                $kept = $result['kept'];

                Notification::make()
                    ->title('Representante eliminado')
                    ->body("Se borraron {$deleted['participants']} participante(s), {$deleted['enrollments']} inscripción(es) y {$deleted['onboardingDrafts']} borrador(es). "
                        ."Se conservaron {$kept['orders']} orden(es) y {$kept['payments']} pago(s) sin representante.")
                    ->success()
                    ->send();

                if ($redirectToIndex) {
                    $action->redirect(UserResource::getUrl('index'));
                }
            });
    }

    public static function participant(bool $redirectToIndex = false): Action
    {
        return static::base()
            ->visible(fn (Participant $record): bool => static::can($record))
            ->modalHeading(fn (Participant $record): string => "Eliminar participante: {$record->name}")
            ->modalDescription(fn (Participant $record): Htmlable => static::participantImpact($record))
            ->action(function (Participant $record, Action $action) use ($redirectToIndex): void {
                abort_unless(static::can($record), 403);

                app(DeleteParticipant::class)->handle($record);

                Notification::make()->title('Participante eliminado')->success()->send();

                if ($redirectToIndex) {
                    $action->redirect(ParticipantResource::getUrl('index'));
                }
            });
    }

    public static function enrollment(bool $redirectToIndex = false): Action
    {
        return static::base()
            ->visible(fn (Enrollment $record): bool => static::can($record))
            ->modalHeading('Eliminar inscripción')
            ->modalDescription(fn (Enrollment $record): Htmlable => static::html([
                'Se borrará la inscripción de <strong>'.e($record->participant?->name ?? '—').'</strong> al plan <strong>'.e($record->plan_name).'</strong>. El participante se conserva.',
                'La orden de inscripción <strong>no cambia</strong>: sigue con el mismo total y las mismas plazas. Si corresponde, cancélala o ajústala a mano desde Órdenes.',
                static::irreversible(),
            ]))
            ->action(function (Enrollment $record, Action $action) use ($redirectToIndex): void {
                abort_unless(static::can($record), 403);

                app(DeleteEnrollment::class)->handle($record);

                Notification::make()->title('Inscripción eliminada')->success()->send();

                if ($redirectToIndex) {
                    $action->redirect(EnrollmentResource::getUrl('index'));
                }
            });
    }

    /**
     * Borrador de onboarding desde el RelationManager (solo lectura) del
     * expediente. Borrado de una fila sin efectos laterales: no hay Action de
     * dominio (ver impl_F-053.md), alcanza con la policy.
     */
    public static function onboardingDraft(): Action
    {
        return static::base()
            ->visible(fn (OnboardingDraft $record): bool => static::can($record))
            ->modalHeading('Eliminar borrador de onboarding')
            ->modalDescription(static::html([
                'Se borrará este borrador de onboarding. El representante y sus datos no se tocan.',
                static::irreversible(),
            ]))
            ->action(function (OnboardingDraft $record): void {
                abort_unless(static::can($record), 403);

                $record->delete();

                Notification::make()->title('Borrador eliminado')->success()->send();
            });
    }

    /**
     * Plan (fila de la tabla y header de EditPlan). PlanPolicy::delete bloquea
     * planes con inscripciones: ahi el boton se ve deshabilitado con el motivo,
     * para que el admin sepa por que no puede (y que use Cancelar/Finalizar).
     */
    public static function plan(bool $redirectToIndex = false): Action
    {
        return static::base()
            ->visible(fn (Plan $record): bool => (bool) Auth::guard('admin')->user()?->can('plans.manage'))
            ->disabled(fn (Plan $record): bool => ! static::can($record))
            ->tooltip(fn (Plan $record): ?string => static::can($record)
                ? null
                : 'Tiene inscripciones: no se puede eliminar. Usa Cancelar o Finalizar.')
            ->modalHeading(fn (Plan $record): string => "Eliminar plan: {$record->name}")
            ->modalDescription(function (Plan $record): Htmlable {
                $days = $record->planDays()->count();
                $activities = $record->planDays()->withCount('activities')->get()->sum('activities_count');
                $announcements = $record->announcements()->count();

                return static::html([
                    "<strong>Se borrarán:</strong> el plan, sus {$days} día(s) con {$activities} actividad(es) y {$announcements} aviso(s).",
                    'El plan no tiene inscripciones. Si estaba publicado, el portal deja de mostrarlo.',
                    static::irreversible(),
                ]);
            })
            ->action(function (Plan $record, Action $action) use ($redirectToIndex): void {
                abort_unless(static::can($record), 403);

                $record->delete();

                Notification::make()->title('Plan eliminado')->success()->send();

                if ($redirectToIndex) {
                    $action->redirect(PlanResource::getUrl('index'));
                }
            });
    }

    /** Producto: las ordenes guardan su propia copia (items), no se tocan. */
    public static function product(bool $redirectToIndex = false): Action
    {
        return static::base()
            ->visible(fn (Product $record): bool => static::can($record))
            ->modalHeading(fn (Product $record): string => "Eliminar producto: {$record->name}")
            ->modalDescription(static::html([
                'Se borrará el producto y dejará de aparecer en la tienda del portal.',
                'Las órdenes que ya lo incluyen <strong>no cambian</strong>: guardan su propia copia del nombre y el precio.',
                'Si solo está agotado por un tiempo, edítalo y desmarca "Disponible" en lugar de borrarlo.',
                static::irreversible(),
            ]))
            ->action(function (Product $record, Action $action) use ($redirectToIndex): void {
                abort_unless(static::can($record), 403);

                $record->delete();

                Notification::make()->title('Producto eliminado')->success()->send();

                if ($redirectToIndex) {
                    $action->redirect(ProductResource::getUrl('index'));
                }
            });
    }

    /** Metodo de pago: los pagos guardan method_code/method_name propios. */
    public static function paymentMethod(bool $redirectToIndex = false): Action
    {
        return static::base()
            ->visible(fn (PaymentMethod $record): bool => static::can($record))
            ->modalHeading(fn (PaymentMethod $record): string => "Eliminar método de pago: {$record->name}")
            ->modalDescription(static::html([
                'Se borrará el método y los representantes ya no podrán elegirlo en el portal (onboarding ni pagos de órdenes).',
                'Los pagos ya registrados con este método <strong>no cambian</strong>: conservan el nombre del método.',
                'Si solo quieres dejar de ofrecerlo por un tiempo, edítalo y desactívalo en lugar de borrarlo.',
                static::irreversible(),
            ]))
            ->action(function (PaymentMethod $record, Action $action) use ($redirectToIndex): void {
                abort_unless(static::can($record), 403);

                $record->delete();

                Notification::make()->title('Método de pago eliminado')->success()->send();

                if ($redirectToIndex) {
                    $action->redirect(PaymentMethodResource::getUrl('index'));
                }
            });
    }

    /** Dia del plan (RelationManager de EditPlan). */
    public static function planDay(): Action
    {
        return static::base()
            ->visible(fn (PlanDay $record): bool => static::canManagePlans())
            ->modalHeading(fn (PlanDay $record): string => 'Eliminar día: '.($record->title ?: $record->date?->format('d/m/Y') ?: '—'))
            ->modalDescription(fn (PlanDay $record): Htmlable => static::html([
                '<strong>Se borrarán:</strong> el día y sus '.$record->activities()->count().' actividad(es). El portal deja de mostrarlo en el calendario del plan.',
                'Si es el día actual del avance del plan, el plan queda sin día actual.',
                static::irreversible(),
            ]))
            ->action(function (PlanDay $record): void {
                abort_unless(static::canManagePlans(), 403);

                $record->delete();

                Notification::make()->title('Día eliminado')->success()->send();
            });
    }

    /** Aviso del plan (RelationManager de EditPlan). */
    public static function announcement(): Action
    {
        return static::base()
            ->visible(fn (PlanAnnouncement $record): bool => static::canManagePlans())
            ->modalHeading(fn (PlanAnnouncement $record): string => 'Eliminar aviso: '.($record->title ?: '—'))
            ->modalDescription(static::html([
                'Se borrará el aviso y el portal dejará de mostrarlo a los representantes.',
                'Si solo quieres ocultarlo, edítalo y desmarca "Visible" en lugar de borrarlo.',
                static::irreversible(),
            ]))
            ->action(function (PlanAnnouncement $record): void {
                abort_unless(static::canManagePlans(), 403);

                $record->delete();

                Notification::make()->title('Aviso eliminado')->success()->send();
            });
    }

    private static function canManagePlans(): bool
    {
        return (bool) Auth::guard('admin')->user()?->can('plans.manage');
    }

    private static function base(): Action
    {
        return Action::make('delete')
            ->label('Eliminar')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedTrash)
            ->modalSubmitActionLabel('Eliminar definitivamente');
    }

    private static function can(Model $record): bool
    {
        return (bool) Auth::guard('admin')->user()?->can('delete', $record);
    }

    private static function representativeConfirmationText(User $record): string
    {
        return (string) ($record->email ?: 'ELIMINAR');
    }

    private static function representativeImpact(User $record): Htmlable
    {
        $impact = app(DeleteRepresentative::class)->impact($record);
        $deleted = $impact['deleted'];
        $kept = $impact['kept'];

        $pending = [];
        if ($kept['pendingVerificationPayments'] > 0) {
            $pending[] = "{$kept['pendingVerificationPayments']} pago(s) en revisión";
        }
        if ($kept['ordersWithBalance'] > 0) {
            $pending[] = "{$kept['ordersWithBalance']} orden(es) con saldo";
        }

        return static::html([
            "<strong>Se borrarán:</strong> {$deleted['participants']} participante(s), {$deleted['enrollments']} inscripción(es), "
                ."{$deleted['correctionRequests']} solicitud(es) de corrección, {$deleted['onboardingDrafts']} borrador(es) de onboarding, "
                .'sus sesiones del portal y sus fotos.',
            "<strong>Se conservarán:</strong> {$kept['orders']} orden(es) y {$kept['payments']} pago(s), sin representante pero con una copia de su nombre, email e identificación; también los comprobantes y la auditoría.",
            $pending !== []
                ? '<strong>Pendientes:</strong> '.implode(' y ', $pending).'. No cambian de estado: el pago se puede aprobar o rechazar después y la orden se cancela a mano si corresponde.'
                : null,
            static::irreversible(),
        ]);
    }

    private static function participantImpact(Participant $record): Htmlable
    {
        $enrollment = $record->enrollment;
        $corrections = $record->correctionRequests()->count();

        return static::html([
            'Se borrará la ficha de <strong>'.e($record->name).'</strong>'
                .($enrollment ? ', su inscripción al plan <strong>'.e($enrollment->plan_name).'</strong>' : '')
                .", {$corrections} solicitud(es) de corrección y su foto. El representante se conserva.",
            'La orden de inscripción <strong>no cambia de plazas</strong>: sigue cubriendo los mismos cupos. Si con este borrado la familia queda por debajo del mínimo del descuento por hermanos, se quita el descuento y <strong>el total de la orden de inscripción sube</strong>.',
            static::irreversible(),
        ]);
    }

    private static function irreversible(): string
    {
        return '<strong>Esta acción es irreversible.</strong>';
    }

    /** @param  array<int, string|null>  $paragraphs  HTML ya escapado */
    private static function html(array $paragraphs): HtmlString
    {
        $paragraphs = array_filter($paragraphs, fn (?string $paragraph): bool => filled($paragraph));

        return new HtmlString(implode('', array_map(
            fn (string $paragraph): string => '<p style="margin-top:0.5rem">'.$paragraph.'</p>',
            $paragraphs,
        )));
    }
}
