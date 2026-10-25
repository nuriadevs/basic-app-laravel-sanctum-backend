<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_expected_tables_exist(): void
    {
        foreach (['users', 'products', 'orders', 'order_details', 'personal_access_tokens'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }
    }

    public function test_users_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('users', [
            'name',
            'first_surname',
            'second_surname',
            'email',
            'password',
            'role',
            'is_active',
            'dni',
            'postal_code',
            'city',
        ]));
    }

    public function test_orders_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('orders', ['user_id', 'total_amount']));
    }

    public function test_order_details_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('order_details', [
            'order_id',
            'product_id',
            'quantity',
            'unit_price',
            'total_amount',
        ]));
    }

    public function test_products_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('products', ['name', 'description', 'price', 'stock']));
    }

    public function test_personal_access_tokens_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('personal_access_tokens', [
            'tokenable_type',
            'tokenable_id',
            'name',
            'token',
            'abilities',
            'expires_at',
        ]));
    }
}
