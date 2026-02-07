<?php

namespace App\Listeners\Inventory;

use App\Events\Inventory\LowStockDetected;
use App\Notifications\Inventory\LowStockAlert;
use Illuminate\Support\Facades\Notification;

class SendLowStockNotification
{
    public function handle(LowStockDetected $event): void
    {
        Notification::route('mail', 'info@gmail.com')
            ->notify(new LowStockAlert($event->lowStockItems));
    }
}
