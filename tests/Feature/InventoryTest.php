<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_inventory_pagination()
    {
        $user = User::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $items = InventoryItem::factory()->count(20)->create();

        foreach ($items as $item) {
            Stock::create([
                'warehouse_id' => $warehouse->id,
                'inventory_item_id' => $item->id,
                'quantity' => rand(10, 100),
            ]);
        }

        $response = $this->actingAs($user)->getJson("/api/inventory/stocks?warehouse_id={$warehouse->id}&per_page=15");

        $response->assertOk();
        $response->assertJsonCount(15, 'data');
        $response->assertJsonPath('meta.total', 20);
    }

    public function test_list_inventory_filtering()
    {
        $user = User::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $item1 = InventoryItem::factory()->create(['name' => 'Apple']);
        $item2 = InventoryItem::factory()->create(['name' => 'Banana']);

        Stock::create(['warehouse_id' => $warehouse->id, 'inventory_item_id' => $item1->id, 'quantity' => 50]);
        Stock::create(['warehouse_id' => $warehouse->id, 'inventory_item_id' => $item2->id, 'quantity' => 50]);

        $response = $this->actingAs($user)->getJson("/api/inventory/stocks?warehouse_id={$warehouse->id}&search=Apple&search_by=item_name");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.item.name', 'Apple');
    }

    public function test_inventory_caching()
    {
        $user = User::factory()->create();
        $warehouse = Warehouse::factory()->create();

        // Mock the Cache facade
        $taggedCache = \Mockery::mock('Illuminate\Cache\TaggedCache');
        $taggedCache->shouldReceive('remember')
            ->once()
            ->andReturn(new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15));

        Cache::shouldReceive('tags')
            ->with(['inventory', 'stocks', "warehouse:{$warehouse->id}"])
            ->once()
            ->andReturn($taggedCache);

        $this->actingAs($user)->getJson("/api/inventory/stocks?warehouse_id={$warehouse->id}");
    }
}
