<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Support\AdminTokenStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_delete_removes_product_images_from_storage(): void
    {
        config()->set('auth.admin_token_store', 'array');

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin-delete@example.com',
            'password' => 'secret123',
            'is_admin' => true,
        ]);

        $product = Product::create([
            'name' => 'Produit image',
            'description' => 'Test',
            'price' => 150,
            'stock' => 3,
            'category' => 'Test',
            'size' => 'M',
            'color' => 'Noir',
            'is_featured' => false,
        ]);

        $storedPath = 'products/test-product.jpg';
        Storage::shouldReceive('disk')->with('public')->andReturnSelf();
        Storage::shouldReceive('delete')->once()->with($storedPath)->andReturn(true);

        ProductImage::create([
            'product_id' => $product->id,
            'image_path' => $storedPath,
            'is_main' => true,
        ]);

        $token = 'admin-delete-token';
        AdminTokenStore::put($token, $admin->id, now()->addHour());

        $response = $this
            ->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/products/{$product->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('product_images', ['product_id' => $product->id]);
    }
}
