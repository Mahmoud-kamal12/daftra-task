<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Stock;
use App\Events\Inventory\LowStockDetected;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class StockTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_transfer()
    {
        $user = User::factory()->create();
        $sourceWarehouse = Warehouse::factory()->create();
        $destWarehouse = Warehouse::factory()->create();
        $item = InventoryItem::factory()->create();

        // Initial Stock
        Stock::create([
            'warehouse_id' => $sourceWarehouse->id,
            'inventory_item_id' => $item->id,
            'quantity' => 100,
        ]);

        $response = $this->actingAs($user)->postJson('/api/inventory/transfers', [
            'from_warehouse_id' => $sourceWarehouse->id,
            'to_warehouse_id' => $destWarehouse->id,
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 10],
            ],
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('stocks', [
            'warehouse_id' => $sourceWarehouse->id,
            'inventory_item_id' => $item->id,
            'quantity' => 90,
        ]);

        $this->assertDatabaseHas('stocks', [
            'warehouse_id' => $destWarehouse->id,
            'inventory_item_id' => $item->id,
            'quantity' => 10,
        ]);
    }

    public function test_transfer_insufficient_stock()
    {
        $user = User::factory()->create();
        $sourceWarehouse = Warehouse::factory()->create();
        $destWarehouse = Warehouse::factory()->create();
        $item = InventoryItem::factory()->create();

        Stock::create([
            'warehouse_id' => $sourceWarehouse->id,
            'inventory_item_id' => $item->id,
            'quantity' => 5,
        ]);

        $response = $this->actingAs($user)->postJson('/api/inventory/transfers', [
            'from_warehouse_id' => $sourceWarehouse->id,
            'to_warehouse_id' => $destWarehouse->id,
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 10],
            ],
        ]);

        $response->assertUnprocessable(); // 422
    }

    public function test_transfer_with_multiple_items()
    {
        $user = User::factory()->create();
        $w1 = Warehouse::factory()->create();
        $w2 = Warehouse::factory()->create();
        $item1 = InventoryItem::factory()->create();
        $item2 = InventoryItem::factory()->create();

        Stock::create(['warehouse_id' => $w1->id, 'inventory_item_id' => $item1->id, 'quantity' => 50]);
        Stock::create(['warehouse_id' => $w1->id, 'inventory_item_id' => $item2->id, 'quantity' => 50]);

        $response = $this->actingAs($user)->postJson('/api/inventory/transfers', [
            'from_warehouse_id' => $w1->id,
            'to_warehouse_id' => $w2->id,
            'lines' => [
                ['item_id' => $item1->id, 'quantity' => 10],
                ['item_id' => $item2->id, 'quantity' => 10],
            ],
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('stocks', ['warehouse_id' => $w1->id, 'inventory_item_id' => $item1->id, 'quantity' => 40]);
        $this->assertDatabaseHas('stocks', ['warehouse_id' => $w2->id, 'inventory_item_id' => $item1->id, 'quantity' => 10]);
    }

    public function test_low_stock_event_fired()
    {
        Event::fake();

        $user = User::factory()->create();
        $sourceWarehouse = Warehouse::factory()->create();
        $destWarehouse = Warehouse::factory()->create();
        $item = InventoryItem::factory()->create();

        // Initial Stock: 400. Transfer 200. Remainder 200 (< 300).
        Stock::create([
            'warehouse_id' => $sourceWarehouse->id,
            'inventory_item_id' => $item->id,
            'quantity' => 400,
        ]);

        $this->actingAs($user)->postJson('/api/inventory/transfers', [
            'from_warehouse_id' => $sourceWarehouse->id,
            'to_warehouse_id' => $destWarehouse->id,
            'lines' => [
                ['item_id' => $item->id, 'quantity' => 200],
            ],
        ]);

        Event::assertDispatched(LowStockDetected::class, function ($event) use ($item) {
            return $event->lowStockItems[0]['item_id'] === $item->id
                && $event->lowStockItems[0]['available_quantity'] === 200;
        });
    }
}
