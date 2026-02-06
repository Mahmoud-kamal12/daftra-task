<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ListStockRequest;
use App\Http\Resources\StockResource;
use App\Services\Inventory\StockService;

class StockController extends Controller
{
    protected StockService $stockService;

    public function __construct(StockService $stockService) {
        $this->stockService = $stockService;
    }

    public function index(ListStockRequest $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        return StockResource::collection(
            $this->stockService->pagination($request->validated())
        );
    }
}
