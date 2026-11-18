<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];

        // Validasi CAPTCHA wajib pada non-testing, atau jika testing menyediakan captcha / captcha_key
        $shouldCheckCaptcha = !app()->environment('testing') || $this->has('captcha') || $this->has('captcha_key');

        if ($shouldCheckCaptcha) {
            $rules['captcha_key'] = ['required', 'string'];
            $rules['captcha'] = [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $inputCaptcha = trim($value);
                    $captchaKey = $this->input('captcha_key') ?? request()->input('captcha_key');

                    if (empty($captchaKey)) {
                        $fail('Kode verifikasi (CAPTCHA) tidak valid. Silakan muat ulang.');
                        return;
                    }

                    // Anti-Replay: Cek apakah captcha_key sudah pernah dipakai
                    $cacheKey = 'captcha_used_' . md5($captchaKey);
                    if (Cache::has($cacheKey)) {
                        $fail('Kode verifikasi (CAPTCHA) sudah digunakan. Silakan muat ulang kode.');
                        return;
                    }

                    try {
                        $decrypted = Crypt::decryptString($captchaKey);
                        $parts = explode('|', $decrypted);
                        if (count($parts) < 2) {
                            $fail('Kode verifikasi (CAPTCHA) tidak valid.');
                            return;
                        }

                        [$expectedCaptcha, $timestamp] = $parts;

                        // TTL CAPTCHA: 300 detik (5 menit)
                        if (time() - (int) $timestamp > 300) {
                            $fail('Kode verifikasi (CAPTCHA) telah kedaluwarsa. Silakan klik muat ulang kode.');
                            return;
                        }

                        if (strcasecmp($inputCaptcha, $expectedCaptcha) !== 0) {
                            $fail('Kode verifikasi (CAPTCHA) tidak sesuai dengan yang ditampilkan.');
                            return;
                        }
                    } catch (\Exception $e) {
                        $fail('Kode verifikasi (CAPTCHA) tidak valid. Silakan muat ulang kode.');
                        return;
                    }
                },
            ];
        }

        return $rules;
    }

    /**
     * Custom validation messages in Indonesian.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'NIP / ID Pengguna wajib diisi.',
            'password.required' => 'Kata sandi wajib diisi.',
            'captcha.required' => 'Kode verifikasi (CAPTCHA) wajib diisi.',
            'captcha_key.required' => 'Kunci verifikasi (CAPTCHA) wajib disertakan.',
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        // 1. Validasi ulang CAPTCHA sebelum otentikasi akun
        $inputCaptcha = trim($this->input('captcha', ''));
        $captchaKey = $this->input('captcha_key');
        $validCaptcha = false;

        $shouldCheckCaptcha = !app()->environment('testing') || $this->has('captcha') || $this->has('captcha_key');

        if (! $shouldCheckCaptcha) {
            $validCaptcha = true;
        } else {
            if (! empty($captchaKey)) {
                $cacheKey = 'captcha_used_' . md5($captchaKey);
                if (Cache::has($cacheKey)) {
                    RateLimiter::hit($this->throttleKey());
                    throw ValidationException::withMessages([
                        'captcha' => 'Kode verifikasi (CAPTCHA) sudah digunakan. Silakan muat ulang kode.',
                    ]);
                }

                try {
                    $decrypted = Crypt::decryptString($captchaKey);
                    $parts = explode('|', $decrypted);
                    if (count($parts) >= 2) {
                        [$expectedCaptcha, $timestamp] = $parts;
                        if ((time() - (int) $timestamp <= 300) && strcasecmp($inputCaptcha, $expectedCaptcha) === 0) {
                            $validCaptcha = true;
                        }
                    }
                } catch (\Exception $e) {
                    $validCaptcha = false;
                }
            }

            if (! $validCaptcha) {
                $sessionCaptcha = session('login_captcha');
                if (! empty($sessionCaptcha) && strcasecmp($inputCaptcha, $sessionCaptcha) === 0) {
                    $validCaptcha = true;
                }
            }
        }

        if (! $validCaptcha) {
            if (! empty($captchaKey)) {
                Cache::put('captcha_used_' . md5($captchaKey), true, 360);
            }
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'captcha' => 'Kode verifikasi (CAPTCHA) tidak sesuai dengan yang ditampilkan.',
            ]);
        }

        // Tandai key sebagai sudah dipakai (Single-Use Anti-Replay)
        if (! empty($captchaKey)) {
            Cache::put('captcha_used_' . md5($captchaKey), true, 360);
        }

        session()->forget('login_captcha');

        // 2. Validasi Kredensial Pengguna
        $loginInput = $this->input('email');

        $user = \App\Models\User::where('username', $loginInput)
            ->orWhere('email', $loginInput)
            ->orWhere('nik', $loginInput)
            ->orWhere('nrk', $loginInput)
            ->first();

        if ($user && $user->status_user !== 'Aktif') {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'Akun Anda sedang dinonaktifkan atau ditangguhkan (Status: ' . ($user->status_user ?? 'Nonaktif') . '). Silakan hubungi administrator.',
            ]);
        }

        if ($user && $user->isTemporary()) {
            if ($user->isTemporaryExpired()) {
                $user->delete();
                RateLimiter::hit($this->throttleKey());

                throw ValidationException::withMessages([
                    'email' => 'Akun tidak di temukan',
                ]);
            }
        }

        $authenticated = false;
        if ($user) {
            $authenticated = Auth::attempt(['id' => $user->id, 'password' => $this->string('password')], $this->boolean('remember'));
        } else {
            $field = filter_var($loginInput, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
            $authenticated = Auth::attempt([$field => $loginInput, 'password' => $this->string('password')], $this->boolean('remember'));
            if ($authenticated) {
                $authUser = Auth::user();
                if ($authUser && $authUser->status_user !== 'Aktif') {
                    Auth::logout();
                    RateLimiter::hit($this->throttleKey());
                    throw ValidationException::withMessages([
                        'email' => 'Akun Anda sedang dinonaktifkan atau ditangguhkan. Silakan hubungi administrator.',
                    ]);
                }
                if ($authUser && $authUser->isTemporary() && $authUser->isTemporaryExpired()) {
                    $authUser->delete();
                    Auth::logout();
                    RateLimiter::hit($this->throttleKey());
                    throw ValidationException::withMessages([
                        'email' => 'Akun tidak di temukan',
                    ]);
                }
            }
        }

        if (! $authenticated) {
            // Regenerate captcha on credential failure
            $characters = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
            $newCaptcha = '';
            for ($i = 0; $i < 5; $i++) {
                $newCaptcha .= $characters[random_int(0, strlen($characters) - 1)];
            }
            session(['login_captcha' => $newCaptcha]);

            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'NIP / ID Pengguna atau kata sandi tidak sesuai.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
