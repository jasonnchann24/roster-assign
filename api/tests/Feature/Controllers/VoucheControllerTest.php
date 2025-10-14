<?php

namespace Tests\Feature\Controllers;

use App\Models\Supplier;
use App\Models\Vouche;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucheControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->supplier = Supplier::factory()->create();
    }

    public function test_protected_routes_use_jwt_access_middleware()
    {
        $this->assertRouteUsesMiddleware('api.vouches.index', ['jwt.access']);
        $this->assertRouteUsesMiddleware('api.vouches.store', ['jwt.access']);
        $this->assertRouteUsesMiddleware('api.vouches.destroy', ['jwt.access']);
        $this->assertRouteUsesMiddleware('api.vouches.by-supplier', ['jwt.access']);
    }

    public function test_can_list_vouches()
    {
        Vouche::factory()->count(3)->create();

        $response = $this->authenticatedJson('GET', '/api/vouches', [], [], $this->supplier);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'vouched_by_id', 'vouched_for_id']
                ]
            ]);
    }

    public function test_can_list_vouches_for_specific_supplier()
    {
        $supplier = Supplier::factory()->create();
        Vouche::factory()->create(['vouched_for_id' => $supplier->id]);
        // other supplier vouche
        Vouche::factory()->create();

        $response = $this->authenticatedJson('GET', "/api/vouches/{$supplier->id}", [], [], $this->supplier);

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data')));
    }

    public function test_returns_404_for_nonexistent_supplier()
    {
        $response = $this->authenticatedJson('GET', '/api/vouches/999', [], [], $this->supplier);

        $response->assertStatus(404);
    }

    public function test_can_create_vouche()
    {
        $vouchedFor = Supplier::factory()->create();

        $response = $this->authenticatedJson('POST', '/api/vouches', [
            'vouched_for_id' => $vouchedFor->id,
        ], [], $this->supplier);

        $response->assertStatus(201);
        $this->assertDatabaseHas('vouches', [
            'vouched_by_id' => $this->supplier->id,
            'vouched_for_id' => $vouchedFor->id,
        ]);
    }

    public function test_creating_vouche_increments_supplier_counter()
    {
        $vouchedFor = Supplier::factory()->create(['total_vouches' => 0]);

        $this->authenticatedJson('POST', '/api/vouches', [
            'vouched_for_id' => $vouchedFor->id,
        ], [], $this->supplier);

        $this->assertEquals(1, $vouchedFor->fresh()->total_vouches);
    }

    public function test_can_delete_vouche()
    {
        $vouche = Vouche::factory([
            'vouched_by_id' => $this->supplier->id
        ])->create();

        $response = $this->authenticatedJson('DELETE', "/api/vouches/{$vouche->id}", [], [], $this->supplier);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('vouches', ['id' => $vouche->id]);
    }

    public function test_deleting_vouche_decrements_supplier_counter()
    {
        $supplierFor = Supplier::factory()->create(['total_vouches' => 1]);
        $vouche = Vouche::factory([
            'vouched_by_id' => $this->supplier->id,
            'vouched_for_id' => $supplierFor->id
        ])->create();

        $this->authenticatedJson('DELETE', "/api/vouches/{$vouche->id}", [], [], $this->supplier);

        $this->assertEquals(0, $supplierFor->fresh()->total_vouches);
    }
}
