<?php

namespace App\Events\Inventory;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LowStockDetected
{
    use Dispatchable, SerializesModels;

    public array $lowStockItems;
    public function __construct(array $lowStockItems) {
        $this->lowStockItems = $lowStockItems;
    }
}
