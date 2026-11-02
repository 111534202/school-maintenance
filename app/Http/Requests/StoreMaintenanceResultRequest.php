<?php

namespace App\Http\Requests;

use App\Models\MaintenanceResult;
use Illuminate\Foundation\Http\FormRequest;

class StoreMaintenanceResultRequest extends FormRequest
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
            'result' => ['required', 'in:'.MaintenanceResult::RESULT_OK.','.MaintenanceResult::RESULT_NG],
            // 執行人不用填：由登入帳號自動帶入（controller 寫入 executed_by，指向 users.id）。
            'executed_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],

            // 保養用料（可以不填）：每種零件最多選一次，有選零件時數量必須是正整數。
            'part_usages' => ['nullable', 'array'],
            'part_usages.*.part_id' => ['nullable', 'integer', 'distinct', 'exists:parts,id'],
            'part_usages.*.quantity' => ['nullable', 'required_with:part_usages.*.part_id', 'integer', 'min:1'],
        ];
    }
}
