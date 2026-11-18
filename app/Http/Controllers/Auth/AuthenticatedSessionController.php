<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        $captcha = $this->generateCaptchaData();

        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'initialCaptcha' => $captcha['code'],
            'initialCaptchaKey' => $captcha['key'],
        ]);
    }

    /**
     * Refresh and return a new CAPTCHA code and encrypted key.
     */
    public function refreshCaptcha(): JsonResponse
    {
        $captcha = $this->generateCaptchaData();

        return response()->json([
            'captcha' => $captcha['code'],
            'captcha_key' => $captcha['key'],
        ]);
    }

    /**
     * Generate random CAPTCHA code and cryptographically secured key.
     */
    protected function generateCaptchaData(): array
    {
        $characters = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $charsLength = strlen($characters);
        $code = '';
        for ($i = 0; $i < 5; $i++) {
            $code .= $characters[random_int(0, $charsLength - 1)];
        }

        $timestamp = time();
        $nonce = Str::random(16);
        $key = Crypt::encryptString("{$code}|{$timestamp}|{$nonce}");

        session(['login_captcha' => $code]);

        return [
            'code' => $code,
            'key' => $key,
        ];
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = Auth::user();
        if ($user) {
            ActivityLog::record([
                'user_id' => $user->id,
                'user_name' => $user->nama_lengkap ?? $user->name,
                'user_role' => $user->role,
                'user_seksi' => $user->seksi,
                'action' => 'LOGIN',
                'module' => 'Autentikasi',
                'description' => "Pengguna {$user->nama_lengkap} ({$user->role}) berhasil masuk ke sistem.",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            if ($user->isTemporary()) {
                return redirect()->route('pendaftaran.index');
            }
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if ($user) {
            ActivityLog::record([
                'user_id' => $user->id,
                'user_name' => $user->nama_lengkap ?? $user->name,
                'user_role' => $user->role,
                'user_seksi' => $user->seksi,
                'action' => 'LOGOUT',
                'module' => 'Autentikasi',
                'description' => "Pengguna {$user->nama_lengkap} ({$user->role}) telah keluar dari sistem.",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
