<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\QueuedResetPassword;
use App\Support\BrevoTransactionalMailer;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    public function sendPasswordResetNotification($token): void
    {
        if (config('mail.brevo.api_key')) {
            try {
                app(BrevoTransactionalMailer::class)->sendPasswordResetLink(
                    $this->email,
                    $this->name ?? '',
                    $this->passwordResetUrl($token)
                );

                return;
            } catch (\Throwable $exception) {
                Log::warning('Password reset Brevo notification failed', [
                    'user_id' => $this->id,
                    'email' => $this->email,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $this->notify(new QueuedResetPassword($token));
    }

    protected function passwordResetUrl(string $token): string
    {
        $frontendUrl = rtrim((string) env('FRONTEND_URL', config('app.url')), '/');

        return $frontendUrl . '/reset-password?token=' . $token . '&email=' . urlencode($this->email);
    }
}
