<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMaintenancePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'device_category_id' => ['nullable', 'integer', 'exists:device_categories,id'],
            'cycle_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'start_date' => ['required', 'date'],
            'is_active' => ['sometimes', 'boolean'],
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer', 'exists:maintenance_items,id'],
        ];
    }
}
