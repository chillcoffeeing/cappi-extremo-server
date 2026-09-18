<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Participant;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

/**
 * Cada stat se muestra solo si el admin tiene el permiso de vista del
 * dominio correspondiente (un SOPORTE no ve totales financieros que no
 * puede abrir, por ejemplo).
 */
class KpiOverview extends StatsOverviewWidget
{
    /**
     * Consultas livianas (counts/sums); se renderiza de inmediato en vez de
     * esperar el round-trip lazy por defecto de Filament.
     */
    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $admin = Auth::guard('admin')->user();
        $stats = [];

        if ($admin?->can('payments.view')) {
            $pending = Payment::where('status', 'PENDIENTE_VERIFICACION');
            $pendingCount = (clone $pending)->count();
            $pendingAmount = (clone $pending)->sum('amount');

            $stats[] = Stat::make('Pagos pendientes', (string) $pendingCount)
                ->description('$'.number_format((float) $pendingAmount, 2).' por verificar')
                ->color('warning');
        }

        if ($admin?->can('orders.view')) {
            $stats[] = Stat::make('Pedidos de Tienda', (string) Order::where('is_registration', false)->count());
        }

        if ($admin?->can('users.view')) {
            $stats[] = Stat::make('Representantes', (string) User::count())
                ->description(User::where('onboarding_status', 'COMPLETADO')->count().' con onboarding completo');
        }

        if ($admin?->can('participants.view')) {
            $incomplete = Participant::where('data_completed', false)->count();
            $stats[] = Stat::make('Fichas de participantes incompletas', (string) $incomplete)
                ->color($incomplete > 0 ? 'warning' : 'success');
        }

        if ($admin?->can('plans.view')) {
            $plan = Plan::operative()->latest('starts_at')->first();
            $stats[] = Stat::make('Cupos disponibles', $plan ? (string) ($plan->available_slots ?? $plan->capacity) : '—')
                ->description($plan ? $plan->name : 'Sin plan operativo');
            $stats[] = Stat::make('Avance del plan', $plan ? "{$plan->progress_percent}%" : '—')
                ->description($plan?->status ?? '');
        }

        return $stats;
    }
}
