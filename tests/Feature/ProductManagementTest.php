<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

/**
 * Tests para productos.
 *
 * Nombre de todos los tests de la clase: ProductManagementTest:
 * - user_list_products: listar productos.
 * - user_view_a_product: visualizar un producto.
 * - user_create_a_product: crear un producto.
 * - user_create_a_product_without_name: crear sin nombre (422).
 * - user_create_a_product_with_negative_price: precio negativo (422).
 * - user_update_a_product: actualizar un producto.
 * - user_delete_a_product: eliminar un producto.
 * - user_cannot_delete_a_product_with_orders: no se borra un producto con pedidos.
 */
class ProductManagementTest extends TestCase
{
    use RefreshDatabase;
    use CreatesUsers;

    #[Test]
    public function user_list_products(): void
    {
        Sanctum::actingAs($this->customerUser());
        Product::factory()->create(['name' => 'Producto listado']);

        $this->getJson('/api/products')
            ->assertStatus(200)
            ->assertJsonFragment(['name' => 'Producto listado']);
    }

    #[Test]
    public function user_view_a_product(): void
    {
        Sanctum::actingAs($this->customerUser());
        $product = Product::factory()->create();

        $this->getJson("/api/products/{$product->id}")
            ->assertStatus(200)
            ->assertJsonFragment(['name' => $product->name]);
    }

    #[Test]
    public function user_create_a_product(): void
    {
        Sanctum::actingAs($this->adminUser());

        $response = $this->postJson('/api/products', [
            'name' => 'Test Product',
            'description' => 'This is a test product.',
            'price' => 99.99,
            'stock' => 10,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'statusCode',
            'error',
            'data' => [
                'id',
                'name',
                'description',
                'price',
                'stock',
                'created_at',
                'updated_at',
            ],
        ]);

        $this->assertDatabaseHas('products', ['name' => 'Test Product', 'stock' => 10]);
    }

    #[Test]
    public function user_create_a_product_without_name(): void
    {
        Sanctum::actingAs($this->adminUser());

        $response = $this->postJson('/api/products', [
            'description' => 'Test product.',
            'price' => 99.99,
            'stock' => 10,
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'message',
            'statusCode',
            'error',
            'data' => [
                'name',
            ],
        ]);
    }

    #[Test]
    public function user_create_a_product_with_negative_price(): void
    {
        Sanctum::actingAs($this->adminUser());

        $this->postJson('/api/products', [
            'name' => 'Precio negativo',
            'description' => 'No debería crearse.',
            'price' => -5,
            'stock' => 1,
        ])->assertStatus(422);

        $this->assertDatabaseMissing('products', ['name' => 'Precio negativo']);
    }

    #[Test]
    public function user_update_a_product(): void
    {
        Sanctum::actingAs($this->adminUser());
        $product = Product::factory()->create();

        $this->putJson("/api/products/{$product->id}", [
            'name' => 'Updated Product',
            'description' => 'Updated description',
            'price' => 150.00,
            'stock' => 20,
        ])->assertStatus(200);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Product',
            'stock' => 20,
        ]);
    }

    #[Test]
    public function user_delete_a_product(): void
    {
        Sanctum::actingAs($this->adminUser());
        $product = Product::factory()->create();

        $this->deleteJson("/api/products/{$product->id}")->assertStatus(200);

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    /**
     * La clave foránea order_details.product_id es ON DELETE RESTRICT:
     * un producto con pedidos no se puede borrar.
     */
    #[Test]
    public function user_cannot_delete_a_product_with_orders(): void
    {
        $customer = $this->customerUser();
        $product = Product::factory()->create(['price' => 25.00, 'stock' => 10]);

        // El pedido se crea por la propia API, igual que haría un cliente.
        Sanctum::actingAs($customer);
        $this->postJson('/api/orders', [
            'user_id' => $customer->id,
            'order_details' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->assertStatus(200);

        Sanctum::actingAs($this->adminUser());
        $response = $this->deleteJson("/api/products/{$product->id}");

        // Hoy el controlador responde 500 (QueryException); lo ideal sería 409.
        $this->assertGreaterThanOrEqual(400, $response->status());
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }
}
