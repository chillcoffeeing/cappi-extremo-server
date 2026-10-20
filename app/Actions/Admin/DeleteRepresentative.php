<?php

namespace App\Actions\Admin;

use App\Actions\Admin\Concerns\DeletesPublicPhotosAfterCommit;
use App\Models\Enrollment;
use App\Models\OnboardingDraft;
use App\Models\Order;
use App\Models\Participant;
use App\Models\ParticipantCorrectionRequest;
use App\Models\Payment;
use App\Models\PortalAuthToken;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * F-053 — borrado fisico de un representante (solo SUPER_ADMIN,
 * `records.delete`; ver UserPolicy::delete).
 *
 * Regla de retencion (decision del usuario 2026-10-19, ver
 * api/docs/backoffice/06-flujos-y-auditoria.md): se conserva la evidencia
 * financiera y todo lo demas se borra.
 *
 * - Se CONSERVAN: ordenes y pagos (quedan con user_uuid NULL, cada pago sigue
 *   unido a su orden por order_uuid y guardan `representative_snapshot`),
 *   comprobantes (disco local) y activity_log.
 * - Se BORRAN: participantes, sus inscripciones y solicitudes de correccion,
 *   borradores de onboarding, refresh tokens, portal auth tokens, tokens
 *   Sanctum y el usuario. Las fotos (perfil y participantes, disco public) se
 *   borran despues del commit.
 *
 * El borrado es explicito hijos -> padre; las FKs cascadeOnDelete de la
 * migracion 2026_10_19_000000 son solo la red de seguridad.
 */
class DeleteRepresentative
{
    use DeletesPublicPhotosAfterCommit;

    /**
     * Conteos de lo que se borraria y de lo que se conservaria, sin tocar nada.
     * Pensado para el modal de confirmacion del backoffice (F-054).
     *
     * @return array{deleted: array<string, int>, kept: array<string, int>}
     */
    public function impact(User $user): array
    {
        $participants = Participant::where('user_uuid', $user->uuid)->get(['uuid', 'photo_url']);
        $participantUuids = $participants->pluck('uuid');
        $orders = Order::where('user_uuid', $user->uuid)->get();
        $payments = Payment::where('user_uuid', $user->uuid)->get();

        return [
            'deleted' => [
                'participants' => $participantUuids->count(),
                'enrollments' => Enrollment::whereIn('participant_uuid', $participantUuids)->count(),
                'correctionRequests' => ParticipantCorrectionRequest::whereIn('participant_uuid', $participantUuids)->count(),
                'onboardingDrafts' => OnboardingDraft::where('user_uuid', $user->uuid)->count(),
                'refreshTokens' => RefreshToken::where('user_uuid', $user->uuid)->count(),
                'portalAuthTokens' => PortalAuthToken::where('user_uuid', $user->uuid)->count(),
                'accessTokens' => $user->tokens()->count(),
                'photos' => count($this->photoPaths([$user->photo_url, ...$participants->pluck('photo_url')->all()])),
            ],
            'kept' => $this->keptCounts($orders, $payments),
        ];
    }

    /**
     * @return array{deleted: array<string, int>, kept: array<string, int>}
     */
    public function handle(User $user): array
    {
        return DB::transaction(function () use ($user): array {
            $user = User::whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $uuid = $user->uuid;

            $participants = Participant::where('user_uuid', $uuid)->lockForUpdate()->get();
            $participantUuids = $participants->pluck('uuid');
            $orders = Order::where('user_uuid', $uuid)->lockForUpdate()->get();
            $payments = Payment::where('user_uuid', $uuid)->lockForUpdate()->get();

            $kept = $this->keptCounts($orders, $payments);

            // 1. Evidencia financiera: snapshot + desvincular. Query builder a
            //    proposito: no dispara eventos de modelo, asi que no agrega
            //    entradas al activity_log (que se conserva tal cual).
            //    payments.order_uuid no se toca.
            $snapshot = json_encode($this->snapshot($user), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            Order::where('user_uuid', $uuid)->update(['representative_snapshot' => $snapshot, 'user_uuid' => null]);
            Payment::where('user_uuid', $uuid)->update(['representative_snapshot' => $snapshot, 'user_uuid' => null]);

            // 2. Hijos de los participantes -> participantes.
            $deleted = [
                'enrollments' => Enrollment::whereIn('participant_uuid', $participantUuids)->delete(),
                'correctionRequests' => ParticipantCorrectionRequest::whereIn('participant_uuid', $participantUuids)->delete(),
                'participants' => Participant::where('user_uuid', $uuid)->delete(),
            ];

            // 3. Resto de lo que cuelga del usuario.
            $deleted['onboardingDrafts'] = OnboardingDraft::where('user_uuid', $uuid)->delete();
            $deleted['refreshTokens'] = RefreshToken::where('user_uuid', $uuid)->delete();
            $deleted['portalAuthTokens'] = PortalAuthToken::where('user_uuid', $uuid)->delete();
            $deleted['accessTokens'] = $user->tokens()->delete();

            // 4. Fotos (despues del commit) y el usuario.
            $photos = $this->deletePublicPhotosAfterCommit([
                $user->photo_url,
                ...$participants->pluck('photo_url')->all(),
            ]);
            $deleted['photos'] = count($photos);

            $user->delete();

            return ['deleted' => $deleted, 'kept' => $kept];
        });
    }

    /**
     * @return array{name: string|null, lastName: string|null, email: string|null, identification: string|null, deletedAt: string}
     */
    private function snapshot(User $user): array
    {
        return [
            'name' => $user->name,
            'lastName' => $user->last_name,
            'email' => $user->email,
            'identification' => $user->identification,
            'deletedAt' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @param  Collection<int, Payment>  $payments
     * @return array{orders: int, payments: int, pendingVerificationPayments: int, ordersWithBalance: int}
     */
    private function keptCounts(Collection $orders, Collection $payments): array
    {
        return [
            'orders' => $orders->count(),
            'payments' => $payments->count(),
            'pendingVerificationPayments' => $payments->where('status', 'PENDIENTE_VERIFICACION')->count(),
            'ordersWithBalance' => $orders
                ->filter(fn (Order $order): bool => $order->status !== 'CANCELADA' && $order->balance() > 0)
                ->count(),
        ];
    }
}
