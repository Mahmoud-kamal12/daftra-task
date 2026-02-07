<?php

namespace App\Services\Inventory;

use App\Models\Stock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StockService
{
    private const CACHE_TTL = 3600; // 1 hour for production data
    private const BATCH_SIZE = 500; // Optimized for bulk operations

    public function pagination(array $filters)
    {
        $key = $this->generateCacheKey($filters);

        return Cache::tags([
            'inventory',
            'stocks',
            "warehouse:{$filters['warehouse_id']}",
        ])->remember($key, self::CACHE_TTL, function () use ($filters) {
            return $this->fetchStockList($filters);
        });
    }

    public function generateCacheKey(array $filters): string
    {
        return 'inventory:stocks:' . sha1(json_encode([
            $filters['warehouse_id'],
            $filters['search'] ?? null,
            $filters['search_by'] ?? null,
            $filters['sort_by'] ?? null,
            $filters['sort_dir'] ?? null,
            $filters['page'] ?? 1,
            $filters['per_page'] ?? 15,
        ]));
    }

    public function fetchStockList(array $filters): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = Stock::query()
            ->where('warehouse_id', $filters['warehouse_id'])
            ->select(['id', 'warehouse_id', 'inventory_item_id', 'quantity', 'created_at', 'updated_at'])
            ->with(['warehouse:id,name,code', 'item:id,name,sku']);

        $query = $this->buildFilters($filters, $query);

        return $query->paginate($filters['per_page'] ?? 15);
    }

    private function buildFilters(array $filters, $query)
    {
        if (!empty($filters['search']) && !empty($filters['search_by'])) {
            switch ($filters['search_by']) {
                case 'warehouse_name':
                    $query->whereHas('warehouse', function ($q) use ($filters) {
                        $q->where('name', 'like', '%' . $filters['search'] . '%');
                    });
                    break;
                case 'warehouse_code':
                    $query->whereHas('warehouse', function ($q) use ($filters) {
                        $q->where('code', $filters['search']);
                    });
                    break;
                case 'item_name':
                    $query->whereHas('item', function ($q) use ($filters) {
                        $q->where('name', 'like', '%' . $filters['search'] . '%');
                    });
                    break;
                case 'item_sku':
                    $query->whereHas('item', function ($q) use ($filters) {
                        $q->where('sku',$filters['search']);
                    });
                    break;
            }
        }

        $sortableColumns = ['created_at', 'updated_at', 'quantity'];
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortBy = in_array($sortBy, $sortableColumns) ? $sortBy : 'created_at';

        $query->orderBy($sortBy, $filters['sort_dir'] ?? 'desc');

        return $query;
    }

    public function flushWarehouses(array $warehouseIds): void
    {
        foreach ($warehouseIds as $warehouseId) {
            Cache::tags([
                'inventory',
                'stocks',
                "warehouse:{$warehouseId}",
            ])->flush();
        }
    }

    public function getStockItems(int $warehouseId, array $itemIds): array
    {
        return Stock::query()
            ->select('inventory_item_id', 'quantity')
            ->where('warehouse_id', $warehouseId)
            ->whereIn('inventory_item_id', $itemIds)
            ->lockForUpdate()
            ->orderBy('inventory_item_id')
            ->pluck('quantity', 'inventory_item_id')
            ->toArray();
    }

    public function updateOrCreateStockBatch(array $items, int $chunkSize = 100): void
    {
        foreach (array_chunk($items, $chunkSize) as $chunk) {
            DB::table('stocks')->upsert(
                $chunk,
                ['warehouse_id', 'inventory_item_id'],
                ['quantity', 'updated_at'],
            );
        }
    }
}
