<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Revoca la sesion admin en curso (no solo el proximo login) apenas un
 * SUPER_ADMIN desactiva la cuenta (`admin_users.is_active = false`).
 */
class EnsureAdminIsActive
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('admin')->user();

        if ($admin && ! $admin->is_active) {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();

            abort(403, 'Esta cuenta administrativa fue desactivada.');
        }

        return $next($request);
    }
}
