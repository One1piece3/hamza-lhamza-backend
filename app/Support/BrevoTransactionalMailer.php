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
        $subject = 'Votre lien de reinitialisation - Hamza Lhamza - ' . date('d/m/Y H:i');
        $safeName = e($name !== '' ? $name : $recipient);
        $safeUrl = e($resetUrl);

        $html = new HtmlString(<<<HTML
            <div style="margin:0;padding:28px 0;background:#fff3ed;font-family:Georgia,'Times New Roman',serif;color:#1f2937;">
                <div style="max-width:620px;margin:0 auto;background:#ffffff;border:1px solid #f2c9ba;border-radius:22px;overflow:hidden;">
                    <div style="padding:28px 32px;background:linear-gradient(135deg,#fff3ec 0%,#ffd9ca 100%);border-bottom:1px solid #f2c9ba;">
                        <p style="margin:0 0 8px;font-family:Arial,sans-serif;font-size:12px;letter-spacing:1.8px;text-transform:uppercase;color:#e65b4f;">
                            Hamza Lhamza
                        </p>
                        <h1 style="margin:0;font-size:30px;line-height:1.2;color:#2b1b18 !important;-webkit-text-fill-color:#2b1b18;">
                            Reinitialisation de votre mot de passe
                        </h1>
                    </div>

                    <div style="padding:30px 32px;font-family:Arial,sans-serif;font-size:16px;line-height:1.65;color:#334155;">
                        <p style="margin:0 0 18px;">Bonjour {$safeName},</p>
                        <p style="margin:0 0 18px;">
                            Nous avons recu une demande de reinitialisation du mot de passe associe a votre compte Hamza Lhamza.
                        </p>
                        <p style="margin:0 0 14px;">
                            Pour choisir un nouveau mot de passe, ouvrez ce lien :
                        </p>
                        <p style="margin:0 0 22px;padding:16px 18px;background:#fff7f2;border:1px solid #ffd3c5;border-radius:14px;word-break:break-all;">
                            <a href="{$safeUrl}" style="color:#d94b43;text-decoration:none;">{$safeUrl}</a>
                        </p>
                        <p style="margin:0 0 18px;">
                            Si vous n'etes pas a l'origine de cette demande, vous pouvez simplement ignorer cet email.
                        </p>
                        <p style="margin:24px 0 0;color:#64748b;">
                            L'equipe Hamza Lhamza
                        </p>
                    </div>
                </div>
            </div>
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
