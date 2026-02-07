<?php

namespace App\Notifications\Inventory;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockAlert extends Notification
{
    use Queueable;

    public array $lowStockItems;

    public function __construct($lowStockItems) {
        $this->lowStockItems = $lowStockItems;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mailMessage = (new MailMessage)
            ->subject('Low Stock Alert')
            ->line('The following items are low in stock:');

        foreach ($this->lowStockItems as $item) {
            $mailMessage->line("- {$item['item_id']} (Current Stock: {$item['available_quantity']})");
        }

        return $mailMessage;
    }
}
