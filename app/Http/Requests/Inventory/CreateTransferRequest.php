<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class CreateTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from_warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'to_warehouse_id'   => ['required', 'integer', 'exists:warehouses,id', 'different:from_warehouse_id'],
            'lines'             => ['required', 'array', 'min:1'],
            'lines.*.item_id'   => ['required', 'integer', 'exists:inventory_items,id', 'distinct'],
            'lines.*.quantity'  => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'lines.required' => 'At least one transfer line is required.',
            'lines.array'    => 'Lines must be an array.',
            'lines.min'      => 'At least one transfer line is required.',
            'lines.*.item_id.distinct' => 'Each item must appear only once in lines.',
            'lines.*.quantity.min' => 'Quantity must be at least 1.',
            'to_warehouse_id.different' => 'The destination warehouse must be different from the source warehouse.',
            'lines.*.item_id.exists' => 'One or more items do not exist in inventory.'
        ];
    }
}
