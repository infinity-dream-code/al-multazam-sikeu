<?php

namespace App\Http\Middleware;

use App\Support\PersistentLogin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckUserRoles
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$params)
    {
        try {
            PersistentLogin::attemptRestore();
        } catch (\Throwable $e) {
            report($e);
        }

        if (!Auth::check()) {
            if (PersistentLogin::hasCookie()) {
                if ($request->expectsJson() || $request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                    return response()->json([
                        'message' => 'Memulihkan sesi',
                        'retry' => true,
                        'csrf' => $request->hasSession() ? csrf_token() : null,
                    ], 503);
                }

                if ($request->isMethod('GET') && !$request->cookies->get('_auth_retry')) {
                    return redirect($request->fullUrl())
                        ->withCookie(cookie('_auth_retry', '1', 1, null, null, false, true, false, 'lax'));
                }

                return response(
                    '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8">'
                    . '<meta http-equiv="refresh" content="2">'
                    . '<title>Memulihkan sesi</title></head><body style="font-family:system-ui;display:flex;'
                    . 'align-items:center;justify-content:center;min-height:100vh;margin:0;color:#6b7280;">'
                    . '<div style="text-align:center"><p>Memulihkan sesi…</p>'
                    . '<p style="font-size:.875rem">Halaman akan dimuat ulang otomatis.</p></div>'
                    . '<script>setTimeout(function(){location.reload()},2000)</script></body></html>',
                    503,
                    ['Content-Type' => 'text/html; charset=UTF-8', 'Cache-Control' => 'no-store']
                );
            }

            return redirect()->route('login');
        }

        $user = Auth::user();

        if ($user->hasRole('super-admin')) {
            return $next($request);
        }

        foreach ($params as $param) {
            if (method_exists($user, 'hasRole') && $user->hasRole($param)) {
                return $next($request);
            }
        }

        abort(404, 'Halaman Tidak Ditemukan!');
    }
}
