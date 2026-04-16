<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OrderCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_create_order_with_identity_fields(): void
    {
        Mail::fake();

        $product = Product::create([
            'name' => 'Jean noir',
            'description' => 'Produit test',
            'price' => 600,
            'stock' => 4,
            'category' => 'Jean',
            'size' => 'M',
            'color' => 'Noir',
            'is_featured' => true,
        ]);

        $response = $this->postJson('/api/orders', [
            'customer_user_id' => 77,
            'customer_name' => 'Hamza Test',
            'customer_email' => 'hamza@example.com',
            'customer_phone' => '0612345678',
            'customer_city' => 'Casablanca',
            'customer_address' => '123 boulevard test',
            'customer_note' => 'Appeler avant livraison',
            'items' => [[
                'id' => $product->id,
                'name' => $product->name,
                'price' => 600,
                'quantity' => 1,
                'size' => 'M',
                'color' => 'Noir',
            ]],
            'subtotal' => 600,
            'delivery_fee' => 0,
            'total' => 600,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('order.customer_user_id', 77)
            ->assertJsonPath('order.customer_email', 'hamza@example.com')
            ->assertJsonPath('order.status', 'pending');

        $this->assertDatabaseHas('orders', [
            'customer_user_id' => 77,
            'customer_email' => 'hamza@example.com',
            'customer_phone' => '0612345678',
            'status' => 'pending',
        ]);
    }

    public function test_order_creation_fails_when_stock_is_insufficient(): void
    {
        Mail::fake();

        $product = Product::create([
            'name' => 'Veste test',
            'description' => 'Produit test',
            'price' => 400,
            'stock' => 1,
            'category' => 'Veste',
            'size' => 'L',
            'color' => 'Beige',
            'is_featured' => false,
        ]);

        $response = $this->postJson('/api/orders', [
            'customer_name' => 'Client Stock',
            'customer_email' => 'stock@example.com',
            'customer_phone' => '0611111111',
            'customer_city' => 'Rabat',
            'customer_address' => '1 rue test',
            'items' => [[
                'id' => $product->id,
                'name' => $product->name,
                'price' => 400,
                'quantity' => 3,
                'size' => 'L',
                'color' => 'Beige',
            ]],
            'subtotal' => 1200,
            'delivery_fee' => 0,
            'total' => 1200,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }
}
