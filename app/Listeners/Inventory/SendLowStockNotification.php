<?php

namespace App\Listeners\Inventory;

use App\Events\Inventory\LowStockDetected;
use App\Models\InventoryItem;
use App\Notifications\Inventory\LowStockAlert;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;

class SendLowStockNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(LowStockDetected $event): void
    {
        $itemIds = array_column($event->lowStockItems, 'item_id');
        $items = InventoryItem::whereIn('id', $itemIds)->pluck('name', 'id');

        $notificationData = [];
        foreach ($event->lowStockItems as $stockInfo) {
            $notificationData[] = [
                'name' => $items[$stockInfo['item_id']] ?? 'Unknown Item',
                'current_stock' => $stockInfo['available_quantity'],
            ];
        }

        $recipient = config('mail.from.address') ?? 'admin@example.com';

        Notification::route('mail', $recipient)
            ->notify(new LowStockAlert($notificationData));
    }
}
