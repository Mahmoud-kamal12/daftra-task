<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\CreateTransferRequest;
use App\Http\Resources\TransferResource;
use App\Services\Inventory\TransferService;
use Illuminate\Http\JsonResponse;

class TransferController extends Controller
{
    protected TransferService $transferService;
    public function __construct(TransferService $transferService) {
        $this->transferService = $transferService;
    }

    /**
     * @throws \Exception
     */
    public function store(CreateTransferRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = auth()->user()->id;

        $transfer = $this->transferService->transfer($data);

        return ApiResponse::created([
            'transfer' => TransferResource::make($transfer),
        ],
        'Stock transfer created successfully.'
        );
    }


}
