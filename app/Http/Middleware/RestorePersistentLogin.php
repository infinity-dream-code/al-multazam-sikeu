<?php

namespace App\Http\Middleware;

use App\Support\PersistentLogin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RestorePersistentLogin
{
    /**
     * Restore Auth from persistent cookie when session is missing/broken.
     * Must never throw — a failure here must not become HTTP 500.
     * Jika user sudah login, perpanjang cookie (bukan hanya saat restore).
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            if (Auth::check()) {
                PersistentLogin::set(Auth::user());
            } else {
                PersistentLogin::attemptRestore();
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $next($request);
    }
}
