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
            // 設備類別必選：計畫要知道針對哪一類設備產生工單。
            'device_category_id' => ['required', 'integer', 'exists:device_categories,id'],
            'cycle_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'start_date' => ['required', 'date', 'after_or_equal:2020-01-01', 'before:2038-01-01'],
            'is_active' => ['sometimes', 'boolean'],
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer', 'exists:maintenance_items,id'],
        ];
    }
}
