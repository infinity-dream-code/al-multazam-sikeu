<?php

namespace App\Support;

use App\Models\CyberKey;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;

class PersistentLogin
{
    public const COOKIE = 'al_multazam';

    /** Cookie lama (template) — tetap dibaca agar user tidak ter-logout sekali. */
    private const LEGACY_COOKIE = 'muallimat_keu';

    public static function minutes(): int
    {
        return max(1, (int) config('session.lifetime', 5256000));
    }

    public static function hasCookie(): bool
    {
        return self::cookieValue() !== null;
    }

    public static function set(?Authenticatable $user = null): void
    {
        $user = $user ?: Auth::user();
        if (!$user) {
            return;
        }

        Cookie::queue(cookie(
            self::COOKIE,
            (string) $user->getAuthIdentifier(),
            self::minutes(),
            config('session.path', '/'),
            config('session.domain'),
            (bool) config('session.secure', false),
            true,
            false,
            config('session.same_site', 'lax') ?: 'lax'
        ));
    }

    public static function clear(): void
    {
        $path = config('session.path', '/');
        $domain = config('session.domain');

        Cookie::queue(Cookie::forget(self::COOKIE, $path, $domain));
        Cookie::queue(Cookie::forget(self::LEGACY_COOKIE, $path, $domain));
    }

    /**
     * Restore Auth from cookie when session is missing/broken.
     * Never clears the cookie on transient DB failures (that would force logout).
     */
    public static function attemptRestore(): bool
    {
        if (Auth::check()) {
            return true;
        }

        if (!self::hasCookie()) {
            return false;
        }

        $raw = self::cookieValue();
        $id = is_numeric($raw) ? (int) $raw : 0;
        if ($id <= 0) {
            // cookie corrupt — only then remove
            self::clear();
            return false;
        }

        try {
            $user = CyberKey::query()->whereKey($id)->first();
        } catch (\Throwable $e) {
            // DB blip: keep cookie, do not logout
            Log::warning('PersistentLogin restore deferred (DB)', [
                'message' => $e->getMessage(),
            ]);
            return false;
        }

        if (!$user) {
            self::clear();
            return false;
        }

        try {
            Auth::login($user, false);
            self::set($user);
            return true;
        } catch (\Throwable $e) {
            Log::warning('PersistentLogin Auth::login failed', [
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private static function cookieValue(): ?string
    {
        foreach ([self::COOKIE, self::LEGACY_COOKIE] as $name) {
            $raw = request()->cookie($name);
            if ($raw !== null && $raw !== '') {
                return (string) $raw;
            }
        }

        return null;
    }
}
