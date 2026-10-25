<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

/**
 * Tests de la gestión de usuarios (solo admin).
 *
 * Nombre de todos los tests de la clase: UserManagementTest:
 * - admin_create_a_user: el admin crea un usuario.
 * - admin_view_a_user: el admin ve un usuario.
 * - admin_update_a_user: el admin actualiza un usuario.
 * - admin_delete_a_user: el admin elimina un usuario.
 * - admin_view_missing_user: usuario inexistente (404).
 * - customer_cannot_view_a_user: un cliente no puede ver usuarios (403).
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;
    use CreatesUsers;

    private function userPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Nuevo',
            'first_surname' => 'Usuario',
            'second_surname' => 'Prueba',
            'dni' => '11223344C',
            'postal_code' => '29002',
            'city' => 'Málaga',
            'password' => 'Customer1234!',
            'role' => 'customer',
            'is_active' => true,
        ], $overrides);
    }

    #[Test]
    public function admin_create_a_user(): void
    {
        Sanctum::actingAs($this->adminUser());

        $this->postJson('/api/users', $this->userPayload(['email' => 'nuevo.usuario@example.com']))
            ->assertStatus(200)
            ->assertJson(['error' => false, 'message' => 'User created successfully']);

        $this->assertDatabaseHas('users', [
            'email' => 'nuevo.usuario@example.com',
            'dni' => '11223344C',
        ]);
    }

    #[Test]
    public function admin_view_a_user(): void
    {
        Sanctum::actingAs($this->adminUser());
        $user = User::factory()->create();

        $this->getJson("/api/users/{$user->id}")
            ->assertStatus(200)
            ->assertJsonFragment(['email' => $user->email]);
    }

    #[Test]
    public function admin_update_a_user(): void
    {
        Sanctum::actingAs($this->adminUser());
        $user = User::factory()->create();

        // No se envía email: la regla unique:users fallaría con el email actual.
        $this->putJson("/api/users/{$user->id}", $this->userPayload([
            'name' => 'Nombre actualizado',
            'dni' => '55667788D',
        ]))->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Nombre actualizado',
            'dni' => '55667788D',
        ]);
    }

    #[Test]
    public function admin_delete_a_user(): void
    {
        Sanctum::actingAs($this->adminUser());
        $user = User::factory()->create();

        $this->deleteJson("/api/users/{$user->id}")->assertStatus(200);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    #[Test]
    public function admin_view_missing_user(): void
    {
        Sanctum::actingAs($this->adminUser());

        $this->getJson('/api/users/999999')
            ->assertStatus(404)
            ->assertJson(['error' => true]);
    }

    #[Test]
    public function customer_cannot_view_a_user(): void
    {
        Sanctum::actingAs($this->customerUser());
        $user = User::factory()->create();

        $this->getJson("/api/users/{$user->id}")->assertStatus(403);
    }
}
