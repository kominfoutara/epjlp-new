<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureTemporaryUserAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            // Jika akun adalah user sementara
            if ($user->isTemporary()) {
                // Cek kedaluwarsa
                if ($user->isTemporaryExpired()) {
                    $user->delete();
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    return redirect()->route('login')->with('error', 'Akun tidak di temukan');
                }

                // Cek rute yang diperbolehkan untuk user sementara
                $allowedRouteNames = [
                    'pendaftaran.index',
                    'pendaftaran.store',
                    'pendaftaran.sukses',
                    'pendaftaran.status',
                    'logout',
                ];

                $currentRouteName = $request->route()?->getName();

                if (! in_array($currentRouteName, $allowedRouteNames, true)) {
                    return redirect()->route('pendaftaran.index');
                }
            } else {
                // Jika user permanen mencoba mengakses halaman pendaftaran user sementara
                $temporaryOnlyRoutes = [
                    'pendaftaran.index',
                    'pendaftaran.store',
                ];

                $currentRouteName = $request->route()?->getName();

                if (in_array($currentRouteName, $temporaryOnlyRoutes, true)) {
                    return redirect()->route('dashboard');
                }
            }
        }

        return $next($request);
    }
}

