<?php

namespace App\Services\Inventory;

use App\Events\Inventory\LowStockDetected;
use App\Jobs\Inventory\TransferJob;
use App\Models\StockTransfer;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferService
{
    protected StockService $stockService;
    private const BULK_THRESHOLD = 50;

    public function __construct(StockService $stockService) {
        $this->stockService = $stockService;
    }

    public function callJobToTransfer(array $data): void
    {
        /*
         * Dispatch to queue for async processing with batching support
         * Uses low-latency processing for better throughput
        */
        TransferJob::dispatch($data)
            ->onQueue('transfers');
    }

    private function normalizeLines($lines): array
    {
        $normalized = [];
        foreach ($lines as $line) {
            $itemId = $line['item_id'];
            $normalized[$itemId] = $line['quantity'];
        }
        ksort($normalized);
        return $normalized;
    }

    public function storeTransferBulk(int $fromWarehouse, int $toWarehouse, array $transferLines, int $createdBy, int $chunkSize = 500): StockTransfer
    {
        $transfer = StockTransfer::query()->create([
            'from_warehouse_id' => $fromWarehouse,
            'to_warehouse_id'   => $toWarehouse,
            'created_by'        => $createdBy,
        ]);

        $now = now();
        foreach ($transferLines as &$line) {
            $line['stock_transfer_id'] = $transfer->id;
            $line['created_at'] = $now;
            $line['updated_at'] = $now;
        }

        foreach (array_chunk($transferLines, $chunkSize) as $chunk) {
            DB::table('stock_transfer_lines')->insert($chunk);
        }

        return $transfer->load('fromWarehouse', 'toWarehouse', 'lines.item');
    }

    /**
     * @throws Exception
     */
    public function transfer(array $data): StockTransfer
    {
        DB::beginTransaction();

        try {
            $neededItems = $this->normalizeLines($data['lines']);
            $toWarehouse = $data['to_warehouse_id'];
            $createdBy = $data['created_by'];
            $fromWarehouse = $data['from_warehouse_id'];

            $warehousesToLock = [$fromWarehouse, $toWarehouse];
            sort($warehousesToLock);

            $stocks = [];
            foreach ($warehousesToLock as $warehouseId) {
                $stocks[$warehouseId] = $this->stockService->getStockItems($warehouseId, array_keys($neededItems));
            }

            $fromStockItems = $stocks[$fromWarehouse];
            $toStockItems = $stocks[$toWarehouse];

            $itemsToUpdateOrCreate = [];
            $lowStockDetected = [];
            $transferLines = [];

            foreach ($neededItems as $itemId => $neededQuantity) {
                $availableQuantity = $fromStockItems[$itemId] ?? null;
                $toItemQuantity = $toStockItems[$itemId] ?? 0;

                if (is_null($availableQuantity)) {
                    throw ValidationException::withMessages([
                        'lines' => ["Item ID {$itemId} not available in source warehouse {$fromWarehouse}."],
                    ]);
                }

                if ($neededQuantity > $availableQuantity) {
                    throw ValidationException::withMessages([
                        'lines' => ["Item {$itemId}: insufficient stock. Available: {$availableQuantity}, Needed: {$neededQuantity}."],
                    ]);
                }

                $now = now();
                $newFromQuantity = $availableQuantity - $neededQuantity;
                $newToQuantity = $toItemQuantity + $neededQuantity;

                // Prepare updates for both warehouses
                $itemsToUpdateOrCreate[] = [
                    'warehouse_id' => $toWarehouse,
                    'inventory_item_id' => $itemId,
                    'quantity' => $newToQuantity,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $itemsToUpdateOrCreate[] = [
                    'warehouse_id' => $fromWarehouse,
                    'inventory_item_id' => $itemId,
                    'quantity' => $newFromQuantity,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if ($newFromQuantity < self::BULK_THRESHOLD) {
                    $lowStockDetected[] = [
                        'item_id' => $itemId,
                        'warehouse_id' => $fromWarehouse,
                        'available_quantity' => $newFromQuantity
                    ];
                }

                $transferLines[] = [
                    'inventory_item_id' => $itemId,
                    'quantity' => $neededQuantity,
                ];
            }

            $this->stockService->updateOrCreateStockBatch($itemsToUpdateOrCreate);
            $transfer = $this->storeTransferBulk($fromWarehouse, $toWarehouse, $transferLines, $createdBy);

            DB::commit();

            $this->stockService->flushWarehouses([$toWarehouse, $fromWarehouse]);
            if (!empty($lowStockDetected)){
                event(new LowStockDetected($lowStockDetected));
            }

            return $transfer;
        } catch (Exception $e) {
            DB::rollBack();
            if ($e instanceof ValidationException) {
                throw $e;
            }
            throw new Exception("Transfer failed: " . $e->getMessage(), 500, $e);
        }
    }
}
