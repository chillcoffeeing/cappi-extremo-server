<?php

namespace App\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurePortalRateLimiting();

        Event::listen(function (Login $event): void {
            if ($event->guard !== 'admin') {
                return;
            }

            activity('admin.auth')
                ->causedBy($event->user)
                ->withProperties(['ip' => request()->ip(), 'user_agent' => request()->userAgent()])
                ->log('login');
        });

        Event::listen(function (Logout $event): void {
            if ($event->guard !== 'admin' || ! $event->user) {
                return;
            }

            activity('admin.auth')
                ->causedBy($event->user)
                ->withProperties(['ip' => request()->ip(), 'user_agent' => request()->userAgent()])
                ->log('logout');
        });
    }

    /**
     * F-056 (api-A-8): rate limiting de las rutas públicas de autenticación del
     * portal. Varias familias pueden compartir IP (colegio, CGNAT de la operadora
     * móvil), así que el límite estricto es por IP+email y el de solo IP es un
     * techo holgado contra abuso masivo. 429 con `{message}` en español.
     */
    private function configurePortalRateLimiting(): void
    {
        $tooMany = fn (Request $request, array $headers) => response()->json(
            ['message' => 'Demasiados intentos. Espera un minuto e inténtalo de nuevo.'],
            429,
            $headers,
        );
        // El limiter corre antes de la validación: un `email`/`token` que no sea
        // string (p. ej. un array) no debe dar 500 por "Array to string
        // conversion"; se trata como vacío y la validación responde 422.
        $input = fn (Request $request, string $key): string => is_string($value = $request->input($key)) ? $value : '';
        $email = fn (Request $request): string => mb_strtolower(trim($input($request, 'email')));

        RateLimiter::for('portal-login', fn (Request $request) => [
            Limit::perMinute(5)->by('login:'.$request->ip().'|'.$email($request))->response($tooMany),
            Limit::perMinute(60)->by('login-ip:'.$request->ip())->response($tooMany),
        ]);

        RateLimiter::for('portal-register', fn (Request $request) => [
            Limit::perMinute(20)->by('register-ip:'.$request->ip())->response($tooMany),
        ]);

        // forgot-password (email), reset-password y verify-email (token).
        RateLimiter::for('portal-password', fn (Request $request) => [
            Limit::perMinute(3)->by('password:'.$request->ip().'|'.$email($request).'|'.hash('sha256', $input($request, 'token')))->response($tooMany),
            Limit::perMinute(20)->by('password-ip:'.$request->ip())->response($tooMany),
        ]);

        // El refresh token es aleatorio (no se puede adivinar); el portal lo
        // llama al abrir cada pestaña y al vencer el access token: techo alto.
        RateLimiter::for('portal-refresh', fn (Request $request) => [
            Limit::perMinute(120)->by('refresh-ip:'.$request->ip())->response($tooMany),
        ]);
    }
}
