<?php

namespace Tests\Concerns;

use App\Models\User;

/**
 * Helpers para crear usuarios con rol en los tests.
 */
trait CreatesUsers
{
    protected function adminUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'admin',
            'is_active' => true,
        ], $attributes));
    }

    protected function customerUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'customer',
            'is_active' => true,
        ], $attributes));
    }
}
