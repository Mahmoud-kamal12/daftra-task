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
    public function __construct(StockService $stockService) {
        $this->stockService = $stockService;
    }

    public function callJobToTransfer(array $data): void
    {
        /*
         * I prefer to use a job for this transfer process because it can be time-consuming.
         * We don't want to block the user request, also it will help us to retry the transfer process in case of failure.
         * and we can also monitor the transfer process using the queue system.
        */
        TransferJob::dispatch($data);
    }

    private function normalizeLines($lines): array
    {
        $newNormalizedLines = [];

        foreach ($lines as $line) {
            $newNormalizedLines[$line['item_id']] = $line['quantity'];
        }

        ksort($newNormalizedLines);

        return $newNormalizedLines;
    }

    public function storeTransferBulk(int $fromWarehouse, int $toWarehouse, array $transferLines, int $createdBy, int $chunkSize = 100): StockTransfer
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

            // Lock in consistent order to prevent deadlocks
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
                $toItemQuantity = $toStockItems[$itemId] ?? null;

                if (is_null($availableQuantity)) {
                    throw ValidationException::withMessages([
                        'lines' => ["Item ID {$itemId} is not available in the source warehouse ID {$fromWarehouse}."],
                    ]);
                }

                if ($neededQuantity > $availableQuantity) {
                    throw ValidationException::withMessages([
                        'lines' => ["Item ID {$itemId} does not have enough stock in the source warehouse. Available: {$availableQuantity}, Needed: {$neededQuantity}."],
                    ]);
                }

                $now = now();

                if (is_null($toItemQuantity)) {
                    $itemsToUpdateOrCreate[] = [
                        'warehouse_id' => $toWarehouse,
                        'inventory_item_id' => $itemId,
                        'quantity' => $neededQuantity,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                } else {
                    $itemsToUpdateOrCreate[] = [
                        'warehouse_id' => $toWarehouse,
                        'inventory_item_id' => $itemId,
                        'quantity' => $toItemQuantity + $neededQuantity,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                $itemsToUpdateOrCreate[] = [
                    'warehouse_id' => $fromWarehouse,
                    'inventory_item_id' => $itemId,
                    'quantity' => $availableQuantity - $neededQuantity,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if ($availableQuantity - $neededQuantity < 300) {
                    $lowStockDetected[] = [
                        'item_id' => $itemId,
                        'available_quantity' => $availableQuantity - $neededQuantity
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
