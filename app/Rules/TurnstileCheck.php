<?php

namespace App\Rules;

use Closure;
use App\Models\SiteSetting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;

class TurnstileCheck implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Cek apakah Turnstile diaktifkan dari database
        $setting = SiteSetting::first();
        $turnstileEnabled = $setting?->turnstile_enabled ?? false;

        // Jika Turnstile tidak diaktifkan, skip validasi
        if (!$turnstileEnabled) {
            return;
        }

        if (empty($value)) {
            $fail('Verifikasi Turnstile gagal. Silakan coba lagi.');
            return;
        }

        // Ambil secret key dari database, fallback ke config
        $secretKey = $setting?->turnstile_secret_key ?: config('turnstile.secret_key');

        $response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'secret' => $secretKey,
            'response' => $value,
            'remoteip' => request()->ip(),
        ]);

        $result = $response->json();

        if (!($result['success'] ?? false)) {
            $fail('Verifikasi Turnstile gagal. Silakan coba lagi.');
        }
    }
}
