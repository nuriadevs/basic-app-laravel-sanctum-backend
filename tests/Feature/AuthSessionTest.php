<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

/**
 * Tests de sesión y registro que completan UserAuthenticationTest.
 *
 * Nombre de todos los tests de la clase: AuthSessionTest:
 * - user_login_with_wrong_password: contraseña incorrecta (401).
 * - user_login_with_unknown_email: email inexistente (404).
 * - user_login_without_fields: login sin campos (422).
 * - register_sets_customer_role_and_unverified_email: el registro deja email_verified_at en NULL.
 * - register_without_required_fields: registro sin campos obligatorios (422).
 * - authenticated_user_view_own_profile: GET /api/user devuelve el usuario del token.
 * - user_logout_revokes_token: logout elimina los tokens de personal_access_tokens.
 */
class AuthSessionTest extends TestCase
{
    use RefreshDatabase;
    use CreatesUsers;

    private function validUserData(): array
    {
        return [
            'name' => 'Nuevo',
            'first_surname' => 'Usuario',
            'second_surname' => 'Prueba',
            'email' => 'nuevo@example.com',
            'dni' => '87654321B',
            'postal_code' => '29001',
            'city' => 'Málaga',
            'password' => 'Customer1234!',
            'role' => 'customer',
            'is_active' => true,
        ];
    }

    #[Test]
    public function user_login_with_wrong_password(): void
    {
        $this->customerUser([
            'email' => 'carlos@example.com',
            'password' => 'Customer1234!',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'carlos@example.com',
            'password' => 'incorrecta',
        ])
            ->assertStatus(401)
            ->assertJson(['error' => true, 'message' => 'Incorrect credentials']);
    }

    #[Test]
    public function user_login_with_unknown_email(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'nadie@example.com',
            'password' => 'Customer1234!',
        ])
            ->assertStatus(404)
            ->assertJson(['error' => true, 'message' => 'This user does not exist']);
    }

    #[Test]
    public function user_login_without_fields(): void
    {
        $this->postJson('/api/auth/login', [])
            ->assertStatus(422)
            ->assertJson(['error' => true, 'statusCode' => 422]);
    }

    #[Test]
    public function register_sets_customer_role_and_unverified_email(): void
    {
        $this->postJson('/api/auth/register', $this->validUserData())
            ->assertStatus(201);

        // La verificación de email aún no está implementada: queda en NULL.
        $this->assertDatabaseHas('users', [
            'email' => 'nuevo@example.com',
            'role' => 'customer',
            'is_active' => true,
            'email_verified_at' => null,
        ]);
    }

    #[Test]
    public function register_without_required_fields(): void
    {
        $this->postJson('/api/auth/register', [])
            ->assertStatus(422)
            ->assertJson(['error' => true, 'statusCode' => 422]);

        $this->assertDatabaseCount('users', 0);
    }

    #[Test]
    public function authenticated_user_view_own_profile(): void
    {
        $user = $this->customerUser();
        Sanctum::actingAs($user);

        $this->getJson('/api/user')
            ->assertStatus(200)
            ->assertJsonFragment(['email' => $user->email])
            ->assertJsonMissingPath('password')
            ->assertJsonMissingPath('remember_token');
    }

    #[Test]
    public function user_logout_revokes_token(): void
    {
        $user = $this->customerUser();
        $token = $user->createToken('test')->plainTextToken;

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertStatus(200);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
