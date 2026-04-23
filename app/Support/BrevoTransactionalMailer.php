<?php

namespace App\Support;

use Illuminate\Mail\Mailable;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BrevoTransactionalMailer
{
    public function send(string $recipient, Mailable $mailable): void
    {
        $apiKey = (string) config('mail.brevo.api_key');

        if ($apiKey === '') {
            throw new RuntimeException('Brevo API key is missing.');
        }

        $subject = $mailable->envelope()->subject ?? config('app.name');
        $html = $mailable->render();
        $payload = $this->sanitizePayloadForJson([
            'sender' => [
                'name' => (string) config('mail.from.name'),
                'email' => (string) config('mail.from.address'),
            ],
            'to' => [
                ['email' => $recipient],
            ],
            'subject' => $subject,
            'htmlContent' => $html,
        ]);

        $response = Http::timeout((int) config('mail.brevo.timeout', 10))
            ->acceptJson()
            ->withHeaders([
                'api-key' => $apiKey,
            ])
            ->post(rtrim((string) config('mail.brevo.base_url'), '/') . '/smtp/email', $payload);

        if ($response->failed()) {
            throw new RuntimeException(
                'Brevo API email failed: ' . $response->status() . ' ' . $response->body()
            );
        }
    }

    public function sendPasswordResetLink(string $recipient, string $name, string $resetUrl): void
    {
        $subject = 'Reinitialisation de votre mot de passe';
        $safeName = e($name !== '' ? $name : $recipient);
        $safeUrl = e($resetUrl);

        $html = new HtmlString(<<<HTML
            <p>Bonjour {$safeName},</p>
            <p>Vous avez demande la reinitialisation de votre mot de passe Hamza Lhamza.</p>
            <p>
                <a href="{$safeUrl}" style="display:inline-block;padding:12px 18px;background:#ff6363;color:#ffffff;text-decoration:none;border-radius:8px;">
                    Reinitialiser mon mot de passe
                </a>
            </p>
            <p>Si vous n'avez pas fait cette demande, vous pouvez ignorer cet email.</p>
        HTML);

        $this->sendPayload($recipient, $subject, (string) $html);
    }

    protected function sendPayload(string $recipient, string $subject, string $html): void
    {
        $apiKey = (string) config('mail.brevo.api_key');

        if ($apiKey === '') {
            throw new RuntimeException('Brevo API key is missing.');
        }

        $payload = $this->sanitizePayloadForJson([
            'sender' => [
                'name' => (string) config('mail.from.name'),
                'email' => (string) config('mail.from.address'),
            ],
            'to' => [
                ['email' => $recipient],
            ],
            'subject' => $subject,
            'htmlContent' => $html,
        ]);

        $response = Http::timeout((int) config('mail.brevo.timeout', 10))
            ->acceptJson()
            ->withHeaders([
                'api-key' => $apiKey,
            ])
            ->post(rtrim((string) config('mail.brevo.base_url'), '/') . '/smtp/email', $payload);

        if ($response->failed()) {
            throw new RuntimeException(
                'Brevo API email failed: ' . $response->status() . ' ' . $response->body()
            );
        }
    }

    protected function sanitizePayloadForJson(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->sanitizePayloadForJson($item), $value);
        }

        if (!is_string($value)) {
            return $value;
        }

        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? '';

        if (preg_match('//u', $value) === 1) {
            return $value;
        }

        $cleanValue = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

        return $cleanValue !== false ? $cleanValue : '';
    }
}
