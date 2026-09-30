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
            // 工程實作欄位：目前無登入系統，先讓執行人自行輸入姓名。
            'executed_by' => ['required', 'string', 'max:100'],
            'executed_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
