<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerOrderStatusNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public string $notificationType
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectForType()
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.orders.customer_status_notification',
            with: [
                'intro_text' => $this->introForType(),
                'status_label' => $this->statusLabel(),
            ]
        );
    }

    public function introForType(): string
    {
        return match ($this->notificationType) {
            'created' => 'Merci pour votre commande chez Hamza Lhamza. Nous avons bien recu votre demande et notre equipe va la preparer avec soin.',
            'confirmed' => 'Bonne nouvelle, votre commande a ete confirmee. Nous avons commence sa preparation.',
            'shipping' => 'Votre commande est maintenant en cours de livraison. Nous esperons qu elle vous plaira.',
            'delivered' => 'Votre commande a ete marquee comme livree. Merci encore pour votre confiance.',
            default => 'Merci pour votre confiance.',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->notificationType) {
            'created' => 'Commande recue',
            'confirmed' => 'Commande confirmee',
            'shipping' => 'En cours de livraison',
            'delivered' => 'Commande livree',
            default => 'Mise a jour de commande',
        };
    }

    protected function subjectForType(): string
    {
        return match ($this->notificationType) {
            'created' => "Merci pour votre commande {$this->order->reference}",
            'confirmed' => "Commande confirmee {$this->order->reference}",
            'shipping' => "Commande en cours de livraison {$this->order->reference}",
            'delivered' => "Commande livree {$this->order->reference}",
            default => "Mise a jour de votre commande {$this->order->reference}",
        };
    }
}
