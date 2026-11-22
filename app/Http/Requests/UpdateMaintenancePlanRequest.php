<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaintenancePlanRequest extends FormRequest
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
            // 設備類別必選；只有「已指定單一設備」的舊計畫由設備推得類別，才可以不填。
            'device_category_id' => [Rule::requiredIf(fn () => $this->route('maintenancePlan')?->device_id === null), 'integer', 'exists:device_categories,id'],
            'cycle_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'start_date' => ['required', 'date', 'after_or_equal:2020-01-01', 'before:2038-01-01'],
            'is_active' => ['sometimes', 'boolean'],
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer', 'exists:maintenance_items,id'],
        ];
    }
}
