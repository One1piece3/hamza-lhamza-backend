<?php

namespace Tests\Feature;

use App\Mail\CustomerOrderStatusNotification;
use App\Mail\NewOrderAdminNotification;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NewOrderNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_receives_email_when_order_is_created(): void
    {
        Mail::fake();

        config()->set('mail.admin_order_notification_email', 'hamzaferhane81@gmail.com');

        $product = Product::create([
            'name' => 'Produit test',
            'description' => 'Test',
            'price' => 120,
            'stock' => 10,
            'category' => 'test',
            'size' => 'L',
            'color' => 'Noir',
            'is_featured' => false,
        ]);

        $response = $this->postJson('/api/orders', [
            'customer_name' => 'Client Test',
            'customer_email' => 'client@example.com',
            'customer_phone' => '0600000000',
            'customer_city' => 'Paris',
            'customer_address' => '1 rue du test',
            'customer_note' => 'Livrer rapidement',
            'items' => [[
                'id' => $product->id,
                'name' => $product->name,
                'price' => 120,
                'quantity' => 1,
                'size' => 'L',
                'color' => 'Noir',
            ]],
            'subtotal' => 120,
            'delivery_fee' => 0,
            'total' => 120,
        ]);

        $response->assertCreated();

        Mail::assertQueued(NewOrderAdminNotification::class, function ($mail) {
            return $mail->hasTo('hamzaferhane81@gmail.com');
        });

        Mail::assertQueued(CustomerOrderStatusNotification::class, function ($mail) {
            return $mail->hasTo('client@example.com')
                && $mail->notificationType === 'created';
        });
    }
}
