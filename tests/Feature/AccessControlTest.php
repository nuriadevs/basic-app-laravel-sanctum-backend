<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

/**
 * Tests de control de acceso y recursos inexistentes.
 *
 * Nombre de todos los tests de la clase: AccessControlTest:
 * - guest_cannot_access_protected_routes: sin token, las rutas protegidas dan 401.
 * - customer_cannot_list_users: un cliente no puede listar usuarios (403).
 * - admin_list_users_without_passwords: el admin lista usuarios sin exponer secretos.
 * - user_view_missing_order: pedido inexistente (404).
 * - user_view_missing_product: producto inexistente (404).
 */
class AccessControlTest extends TestCase
{
    use RefreshDatabase;
    use CreatesUsers;

    public static function protectedRoutes(): array
    {
        return [
            'listar pedidos' => ['GET', '/api/orders'],
            'crear pedido' => ['POST', '/api/orders'],
            'crear producto' => ['POST', '/api/products'],
            'listar usuarios' => ['GET', '/api/users'],
            'usuario autenticado' => ['GET', '/api/user'],
            'logout' => ['POST', '/api/auth/logout'],
        ];
    }

    #[Test]
    #[DataProvider('protectedRoutes')]
    public function guest_cannot_access_protected_routes(string $method, string $uri): void
    {
        $this->json($method, $uri)->assertStatus(401);
    }

    #[Test]
    public function customer_cannot_list_users(): void
    {
        Sanctum::actingAs($this->customerUser());

        $this->getJson('/api/users')
            ->assertStatus(403)
            ->assertJson(['message' => 'Forbidden', 'statusCode' => 403, 'error' => true]);
    }

    #[Test]
    public function admin_list_users_without_passwords(): void
    {
        Sanctum::actingAs($this->adminUser());
        User::factory()->count(2)->create();

        $this->getJson('/api/users')
            ->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonMissingPath('data.0.password')
            ->assertJsonMissingPath('data.0.remember_token');
    }

    #[Test]
    public function user_view_missing_order(): void
    {
        Sanctum::actingAs($this->customerUser());

        $this->getJson('/api/orders/999999')
            ->assertStatus(404)
            ->assertJson(['message' => 'Order not found', 'error' => true]);
    }

    #[Test]
    public function user_view_missing_product(): void
    {
        Sanctum::actingAs($this->customerUser());

        $this->getJson('/api/products/999999')
            ->assertStatus(404)
            ->assertJson(['error' => true]);
    }
}
