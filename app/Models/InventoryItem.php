<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InventoryItem extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'sku', 'price'];

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }
}
