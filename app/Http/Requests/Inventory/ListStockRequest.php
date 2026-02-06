<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class ListStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'search'       => ['nullable', 'string', 'max:190'],
            'search_by'    => ['required_with:search', 'string', 'in:warehouse_name,item_name,item_sku,warehouse_code'],
            'sort_by'      => ['nullable', 'in:created_at,quantity,price,name'],
            'sort_dir'     => ['nullable', 'in:asc,desc'],
            'per_page'     => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
