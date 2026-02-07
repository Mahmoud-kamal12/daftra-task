<?php

namespace App\Jobs\Inventory;

use App\Services\Inventory\TransferService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TransferJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $maxExceptions = 1;
    public int $timeout = 300; // 5 minutes

    public function __construct(private readonly array $data) {}

    /**
     * @throws Exception
     */
    public function handle(TransferService $transferService): void
    {
        $transferService->transfer($this->data);
    }

    public function failed(\Throwable $exception): void
    {
        // Log failure and notify admin
        Log::error('Transfer job failed: ' . $exception->getMessage(), [
            'data' => $this->data,
            'exception' => $exception
        ]);
    }
}
