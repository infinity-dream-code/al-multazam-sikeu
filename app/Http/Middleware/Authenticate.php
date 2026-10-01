<?php

namespace App\Http\Middleware;

use App\Support\PersistentLogin;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Authenticate extends Middleware
{
    public function handle($request, Closure $next, ...$guards)
    {
        try {
            PersistentLogin::attemptRestore();
        } catch (\Throwable $e) {
            report($e);
        }

        // Cookie ada tapi restore gagal (DB blip): retry GET sekali, jangan buang ke login
        if (!Auth::check() && PersistentLogin::hasCookie() && $request->isMethod('GET') && !$request->cookies->get('_auth_retry')) {
            return redirect($request->fullUrl())
                ->withCookie(cookie('_auth_retry', '1', 1, null, null, false, true, false, 'lax'));
        }

        return parent::handle($request, $next, ...$guards);
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        return $request->expectsJson() ? null : route('login');
    }

    protected function unauthenticated($request, array $guards)
    {
        try {
            PersistentLogin::attemptRestore();
        } catch (\Throwable $e) {
            report($e);
        }

        if (Auth::check()) {
            // restore berhasil di detik terakhir — jangan redirect login
            throw new HttpResponseException(
                redirect($request->fullUrl() ?: url('/admin'))
            );
        }

        // Cookie masih ada: jangan force login (terasa logout tiba-tiba)
        if (PersistentLogin::hasCookie()) {
            if ($request->expectsJson() || $request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                throw new HttpResponseException(response()->json([
                    'message' => 'Memulihkan sesi',
                    'retry' => true,
                    'csrf' => $request->hasSession() ? csrf_token() : null,
                ], 503));
            }

            if ($request->isMethod('GET') && !$request->cookies->get('_auth_retry')) {
                throw new HttpResponseException(
                    redirect($request->fullUrl() ?: url('/admin'))
                        ->withCookie(cookie('_auth_retry', '1', 1, null, null, false, true, false, 'lax'))
                );
            }

            // Soft hold — auto reload, tanpa ke login dan tanpa loop redirect
            throw new HttpResponseException(response(
                '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8">'
                . '<meta http-equiv="refresh" content="2">'
                . '<title>Memulihkan sesi</title></head><body style="font-family:system-ui;display:flex;'
                . 'align-items:center;justify-content:center;min-height:100vh;margin:0;color:#6b7280;">'
                . '<div style="text-align:center"><p>Memulihkan sesi…</p>'
                . '<p style="font-size:.875rem">Halaman akan dimuat ulang otomatis.</p></div>'
                . '<script>setTimeout(function(){location.reload()},2000)</script></body></html>',
                503,
                ['Content-Type' => 'text/html; charset=UTF-8', 'Cache-Control' => 'no-store']
            ));
        }

        throw new AuthenticationException(
            'Unauthenticated.',
            $guards,
            $this->redirectTo($request)
        );
    }
}
