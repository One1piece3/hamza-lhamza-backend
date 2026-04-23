<?php

namespace App\Support;

use Illuminate\Mail\Mailable;
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

        $response = Http::timeout((int) config('mail.brevo.timeout', 10))
            ->acceptJson()
            ->withHeaders([
                'api-key' => $apiKey,
            ])
            ->post(rtrim((string) config('mail.brevo.base_url'), '/') . '/smtp/email', [
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

        if ($response->failed()) {
            throw new RuntimeException(
                'Brevo API email failed: ' . $response->status() . ' ' . $response->body()
            );
        }
    }
}
