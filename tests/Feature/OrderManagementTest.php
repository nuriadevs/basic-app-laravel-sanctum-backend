<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

/**
 * Tests para la gestión de pedidos.
 * El cliente solo envía user_id y order_details [{product_id, quantity}]:
 * el servidor calcula unit_price y los totales (total = unit_price * quantity).
 *
 * Nombre de todos los tests de la clase: OrderManagementTest:
 * - user_view_all_orders: listar pedidos.
 * - customer_create_an_order: crear un pedido.
 * - order_totals_are_calculated_by_the_server: el servidor ignora precios y totales del cliente.
 * - order_keeps_unit_price_after_product_price_change: unit_price guarda el precio de la compra.
 * - create_order_missing: crear un pedido con datos inválidos (422).
 * - create_order_without_stock: crear un pedido con un producto sin stock (422).
 * - user_view_a_order: visualizar un pedido.
 * - user_update_a_order: actualizar un pedido.
 * - update_order_removes_missing_details: al actualizar se borran las líneas que no se envían.
 * - user_delete_a_order: eliminar un pedido y sus líneas.
 * - user_update_missing_order / user_delete_missing_order: pedido inexistente (404).
 */
class OrderManagementTest extends TestCase
{
    use RefreshDatabase;
    use CreatesUsers;

    /**
     * Crea un pedido a través de la API como lo haría el cliente
     * y devuelve el pedido guardado en base de datos.
     */
    private function createOrderFor(User $user, array $items): Order
    {
        Sanctum::actingAs($user);

        $this->postJson('/api/orders', [
            'user_id' => $user->id,
            'order_details' => $items,
        ])->assertStatus(200);

        return Order::where('user_id', $user->id)->latest('id')->firstOrFail();
    }

    #[Test]
    public function user_view_all_orders(): void
    {
        Sanctum::actingAs($this->customerUser());

        $first = Order::factory()->create();
        Order::factory()->create();
        OrderDetail::factory()->create(['order_id' => $first->id]);

        $this->getJson('/api/orders')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'message',
                'statusCode',
                'error',
                'data' => [
                    '*' => [
                        'id',
                        'user_id',
                        'total_amount',
                        'created_at',
                        'updated_at',
                        'order_details' => [
                            'products' => [
                                '*' => [
                                    'id',
                                    'order_id',
                                    'product_id',
                                    'quantity',
                                    'total_amount',
                                    'unit_price',
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
    }

    #[Test]
    public function customer_create_an_order(): void
    {
        $customer = $this->customerUser();
        $product = Product::factory()->create(['price' => 100.00, 'stock' => 10]);

        Sanctum::actingAs($customer);

        $this->postJson('/api/orders', [
            'user_id' => $customer->id,
            'order_details' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.total_amount', '200.00')
            ->assertJsonStructure([
                'message',
                'statusCode',
                'error',
                'data' => [
                    'id',
                    'user_id',
                    'total_amount',
                    'order_details' => [
                        'products' => [
                            '*' => ['id', 'order_id', 'product_id', 'quantity', 'total_amount', 'unit_price'],
                        ],
                    ],
                ],
            ]);

        $order = Order::where('user_id', $customer->id)->firstOrFail();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'total_amount' => 200.00,
        ]);
        $this->assertDatabaseHas('order_details', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 100.00,
            'total_amount' => 200.00,
        ]);
    }

    #[Test]
    public function order_totals_are_calculated_by_the_server(): void
    {
        $customer = $this->customerUser();
        $a = Product::factory()->create(['price' => 10.00, 'stock' => 10]);
        $b = Product::factory()->create(['price' => 5.50, 'stock' => 10]);

        Sanctum::actingAs($customer);

        // El cliente intenta colar un total y unos precios falsos.
        $this->postJson('/api/orders', [
            'user_id' => $customer->id,
            'total_amount' => 1.00,
            'order_details' => [
                ['product_id' => $a->id, 'quantity' => 2, 'price' => 0.01, 'unit_price' => 0.01, 'total_amount' => 0.01],
                ['product_id' => $b->id, 'quantity' => 1, 'price' => 0.01, 'unit_price' => 0.01, 'total_amount' => 0.01],
            ],
        ])->assertStatus(200);

        $order = Order::where('user_id', $customer->id)->firstOrFail();

        // 10.00 * 2 + 5.50 * 1 = 25.50
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'total_amount' => 25.50]);
        $this->assertDatabaseHas('order_details', [
            'order_id' => $order->id,
            'product_id' => $a->id,
            'unit_price' => 10.00,
            'total_amount' => 20.00,
        ]);
        $this->assertDatabaseHas('order_details', [
            'order_id' => $order->id,
            'product_id' => $b->id,
            'unit_price' => 5.50,
            'total_amount' => 5.50,
        ]);
    }

    #[Test]
    public function order_keeps_unit_price_after_product_price_change(): void
    {
        $customer = $this->customerUser();
        $product = Product::factory()->create(['price' => 20.00, 'stock' => 10]);

        $order = $this->createOrderFor($customer, [
            ['product_id' => $product->id, 'quantity' => 3],
        ]);

        // El precio del producto sube después de la compra.
        $product->forceFill(['price' => 999.99])->save();

        $this->assertDatabaseHas('order_details', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'unit_price' => 20.00,
            'total_amount' => 60.00,
        ]);
    }

    #[Test]
    public function create_order_missing(): void
    {
        $customer = $this->customerUser();
        $product = Product::factory()->create(['stock' => 10]);

        Sanctum::actingAs($customer);

        $lines = [['product_id' => $product->id, 'quantity' => 1]];

        $invalidPayloads = [
            'sin user_id' => ['order_details' => $lines],
            'usuario inexistente' => ['user_id' => 999999, 'order_details' => $lines],
            'sin líneas' => ['user_id' => $customer->id],
            'producto inexistente' => [
                'user_id' => $customer->id,
                'order_details' => [['product_id' => 999999, 'quantity' => 1]],
            ],
            'cantidad cero' => [
                'user_id' => $customer->id,
                'order_details' => [['product_id' => $product->id, 'quantity' => 0]],
            ],
        ];

        foreach ($invalidPayloads as $case => $payload) {
            $this->postJson('/api/orders', $payload)
                ->assertStatus(422)
                ->assertJson(['error' => true, 'statusCode' => 422]);
        }

        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function create_order_without_stock(): void
    {
        $customer = $this->customerUser();
        $product = Product::factory()->create(['stock' => 0]);

        Sanctum::actingAs($customer);

        $this->postJson('/api/orders', [
            'user_id' => $customer->id,
            'order_details' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])
            ->assertStatus(422)
            ->assertJson(['error' => true, 'message' => 'Validation failed']);

        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function user_view_a_order(): void
    {
        $customer = $this->customerUser();
        $product = Product::factory()->create(['price' => 10.00, 'stock' => 10]);

        $order = $this->createOrderFor($customer, [
            ['product_id' => $product->id, 'quantity' => 1],
        ]);

        Sanctum::actingAs($customer);

        $this->getJson("/api/orders/{$order->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonStructure([
                'data' => [
                    'order_details' => [
                        'products' => [
                            '*' => ['id', 'order_id', 'product_id', 'quantity', 'total_amount', 'unit_price'],
                        ],
                    ],
                ],
            ]);
    }

    #[Test]
    public function user_update_a_order(): void
    {
        $customer = $this->customerUser();
        Sanctum::actingAs($customer);

        $order = Order::factory()->create(['user_id' => $customer->id]);
        $product = Product::factory()->create(['price' => 50.00, 'stock' => 10]);

        $this->putJson("/api/orders/{$order->id}", [
            'user_id' => $customer->id,
            'order_details' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->assertStatus(200);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'total_amount' => 50.00,
        ]);
        $this->assertDatabaseHas('order_details', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'total_amount' => 50.00,
        ]);
    }

    #[Test]
    public function update_order_removes_missing_details(): void
    {
        $customer = $this->customerUser();
        $a = Product::factory()->create(['price' => 10.00, 'stock' => 10]);
        $b = Product::factory()->create(['price' => 5.00, 'stock' => 10]);

        $order = $this->createOrderFor($customer, [
            ['product_id' => $a->id, 'quantity' => 1],
            ['product_id' => $b->id, 'quantity' => 2],
        ]);
        $keep = $order->orderDetails()->where('product_id', $a->id)->firstOrFail();

        Sanctum::actingAs($customer);

        // Solo se envía la línea de A (con su id): la de B debe desaparecer.
        $this->putJson("/api/orders/{$order->id}", [
            'user_id' => $customer->id,
            'order_details' => [
                ['id' => $keep->id, 'product_id' => $a->id, 'quantity' => 4],
            ],
        ])->assertStatus(200);

        $this->assertDatabaseCount('order_details', 1);
        $this->assertDatabaseHas('order_details', [
            'id' => $keep->id,
            'quantity' => 4,
            'total_amount' => 40.00,
        ]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'total_amount' => 40.00]);
    }

    #[Test]
    public function user_delete_a_order(): void
    {
        $customer = $this->customerUser();
        Sanctum::actingAs($customer);

        $order = Order::factory()->create(['user_id' => $customer->id]);
        $detail = OrderDetail::factory()->create(['order_id' => $order->id]);

        $this->deleteJson("/api/orders/{$order->id}")->assertStatus(200);

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
        $this->assertDatabaseMissing('order_details', ['id' => $detail->id]);
    }

    #[Test]
    public function user_update_missing_order(): void
    {
        $customer = $this->customerUser();
        $product = Product::factory()->create(['stock' => 10]);
        Sanctum::actingAs($customer);

        $this->putJson('/api/orders/999999', [
            'user_id' => $customer->id,
            'order_details' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->assertStatus(404);
    }

    #[Test]
    public function user_delete_missing_order(): void
    {
        Sanctum::actingAs($this->customerUser());

        $this->deleteJson('/api/orders/999999')->assertStatus(404);
    }
}
