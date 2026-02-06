<?php

namespace App\Jobs\Inventory;

use App\Services\Inventory\TransferService;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TransferJob implements ShouldQueue
{
    use Queueable;

    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * @throws Exception
     */
    public function handle(): void
    {
        app(TransferService::class)->transfer($this->data);
    }
}
