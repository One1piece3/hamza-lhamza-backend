<?php

namespace Tests\Feature;

use App\Mail\CustomerOrderStatusNotification;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\AdminTokenStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OrderStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_confirm_order_even_if_product_was_deleted(): void
    {
        config()->set('auth.admin_token_store', 'array');

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'secret123',
            'is_admin' => true,
        ]);

        $order = Order::create([
            'reference' => 'CMD-TEST-001',
            'customer_name' => 'Client Test',
            'customer_phone' => '0600000000',
            'customer_city' => 'Paris',
            'customer_address' => '1 rue de test',
            'items' => [
                [
                    'id' => 9999,
                    'name' => 'Produit supprime',
                    'price' => 120,
                    'quantity' => 1,
                    'size' => 'L',
                    'color' => 'Noir',
                ],
            ],
            'subtotal' => 120,
            'delivery_fee' => 0,
            'total' => 120,
            'status' => 'pending',
        ]);

        $token = 'admin-test-token';
        AdminTokenStore::put($token, $admin->id, now()->addHour());

        $response = $this
            ->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/orders/{$order->id}/status", [
                'status' => 'confirmed',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('order.status', 'confirmed')
            ->assertJsonPath('missing_products.0', 'Produit supprime');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_shipping_and_delivered_statuses_do_not_reduce_stock_twice(): void
    {
        Mail::fake();
        config()->set('auth.admin_token_store', 'array');

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin2@example.com',
            'password' => 'secret123',
            'is_admin' => true,
        ]);

        $product = Product::create([
            'name' => 'Produit test',
            'description' => 'Test',
            'price' => 100,
            'stock' => 5,
            'category' => 'test',
            'size' => 'L',
            'color' => 'Noir',
            'is_featured' => false,
        ]);

        $order = Order::create([
            'reference' => 'CMD-TEST-002',
            'customer_name' => 'Client Test',
            'customer_email' => 'client-status@example.com',
            'customer_phone' => '0600000001',
            'customer_city' => 'Paris',
            'customer_address' => '2 rue de test',
            'items' => [[
                'id' => $product->id,
                'name' => $product->name,
                'price' => 100,
                'quantity' => 2,
                'size' => 'L',
                'color' => 'Noir',
            ]],
            'subtotal' => 200,
            'delivery_fee' => 0,
            'total' => 200,
            'status' => 'pending',
        ]);

        $token = 'admin-test-token-2';
        AdminTokenStore::put($token, $admin->id, now()->addHour());

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/orders/{$order->id}/status", ['status' => 'confirmed'])
            ->assertOk();

        $product->refresh();
        $this->assertSame(3, $product->stock);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/orders/{$order->id}/status", ['status' => 'shipping'])
            ->assertOk()
            ->assertJsonPath('order.status', 'shipping');

        $product->refresh();
        $this->assertSame(3, $product->stock);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/orders/{$order->id}/status", ['status' => 'delivered'])
            ->assertOk()
            ->assertJsonPath('order.status', 'delivered');

        $product->refresh();
        $this->assertSame(3, $product->stock);

        Mail::assertQueued(CustomerOrderStatusNotification::class, function ($mail) {
            return $mail->hasTo('client-status@example.com')
                && $mail->notificationType === 'confirmed';
        });

        Mail::assertQueued(CustomerOrderStatusNotification::class, function ($mail) {
            return $mail->hasTo('client-status@example.com')
                && $mail->notificationType === 'shipping';
        });

        Mail::assertQueued(CustomerOrderStatusNotification::class, function ($mail) {
            return $mail->hasTo('client-status@example.com')
                && $mail->notificationType === 'delivered';
        });
    }

    public function test_cancelling_confirmed_order_restores_stock(): void
    {
        config()->set('auth.admin_token_store', 'array');

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin3@example.com',
            'password' => 'secret123',
            'is_admin' => true,
        ]);

        $product = Product::create([
            'name' => 'Produit annulation',
            'description' => 'Test',
            'price' => 80,
            'stock' => 6,
            'category' => 'test',
            'size' => 'M',
            'color' => 'Bleu',
            'is_featured' => false,
        ]);

        $order = Order::create([
            'reference' => 'CMD-TEST-003',
            'customer_name' => 'Client Cancel',
            'customer_phone' => '0600000002',
            'customer_city' => 'Paris',
            'customer_address' => '3 rue de test',
            'items' => [[
                'id' => $product->id,
                'name' => $product->name,
                'price' => 80,
                'quantity' => 2,
                'size' => 'M',
                'color' => 'Bleu',
            ]],
            'subtotal' => 160,
            'delivery_fee' => 0,
            'total' => 160,
            'status' => 'pending',
        ]);

        $token = 'admin-test-token-3';
        AdminTokenStore::put($token, $admin->id, now()->addHour());

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/orders/{$order->id}/status", ['status' => 'confirmed'])
            ->assertOk();

        $product->refresh();
        $this->assertSame(4, $product->stock);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/orders/{$order->id}/status", ['status' => 'cancelled'])
            ->assertOk()
            ->assertJsonPath('order.status', 'cancelled');

        $product->refresh();
        $this->assertSame(6, $product->stock);
    }
}
