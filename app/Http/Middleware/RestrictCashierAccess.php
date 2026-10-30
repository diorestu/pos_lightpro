<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictCashierAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->role === 'kasir') {
            $allowedRoutes = [
                'pos.index',
                'pos.store',
                'pos.quick-customer',
                'shifts.start',
                'shifts.close',
                'orders.show',
                'orders.payment',
                'orders.lookup-booking',
                'orders.delivery',
                'orders.confirm-driver-pickup',
                'logout',
            ];

            $routeName = $request->route()?->getName();

            if (! in_array($routeName, $allowedRoutes)) {
                if ($request->expectsJson()) {
                    abort(403, 'Akses dibatasi: Akun kasir hanya dapat mengakses halaman POS.');
                }

                return redirect()->route('pos.index')->with('error', 'Akses dibatasi: Akun kasir hanya dapat mengakses halaman POS.');
            }
        }

        return $next($request);
    }
}
