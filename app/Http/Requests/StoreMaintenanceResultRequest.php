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
            'executed_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
