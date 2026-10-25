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
        ];
    }
}
