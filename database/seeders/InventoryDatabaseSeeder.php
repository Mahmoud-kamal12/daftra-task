<?php

namespace Database\Seeders;

use App\Models\InventoryItem;
use App\Models\Stock;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class InventoryDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $warehouses = Warehouse::factory()->count(3)->create();
        $items = InventoryItem::factory()->count(20)->create();

        $stocks = [];
        $now = now();

        $warehouses->each(function (Warehouse $warehouse) use ($items, &$stocks, $now) {
            $items->random(10)->each(function (InventoryItem $item) use ($warehouse, &$stocks, $now) {
                $stocks[] = [
                    'warehouse_id' => $warehouse->id,
                    'inventory_item_id' => $item->id,
                    'quantity' => rand(0, 500),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            });
        });

        Stock::insert($stocks);
    }
}
