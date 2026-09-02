<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Recaptcha implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            $fail('Por favor, completa la verificación de seguridad (reCAPTCHA).');
            return;
        }

        $secretKey = config('services.recaptcha.secret_key');

        if (empty($secretKey)) {
            // Si no está configurada la clave en el entorno, permitir en modo debug o registrar advertencia
            if (config('app.debug')) {
                Log::warning('reCAPTCHA secret key is missing in config/services.php');
                return;
            }
            $fail('Error en la configuración del servicio reCAPTCHA.');
            return;
        }

        try {
            $response = Http::asForm()->timeout(5)->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret'   => $secretKey,
                'response' => $value,
                'remoteip' => request()->ip(),
            ]);

            if (!$response->successful() || !$response->json('success')) {
                Log::warning('reCAPTCHA validation failed', [
                    'errors' => $response->json('error-codes'),
                    'response' => $response->json()
                ]);
                $fail('La verificación de seguridad no fue válida. Por favor, vuelve a intentarlo.');
            }
        } catch (\Throwable $e) {
            Log::error('reCAPTCHA connection error: ' . $e->getMessage());
            $fail('No se pudo verificar el captcha. Por favor, intenta de nuevo.');
        }
    }
}
