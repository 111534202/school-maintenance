<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 「退回報修單」表單的驗證規則（依《報修與保養流程修改說明》，取代原本的「取消（刪除）」）。
 * 只要是退回都必須填原因；誰能退回見 App\Policies\RepairRequestPolicy::returnRequest
 * （新報修：能派工的人；已派工：被指派的維修人員）。
 */
class ReturnRepairRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('returnRequest', $this->route('repair_request'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'return_reason' => ['required', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'return_reason.required' => '請填寫退回原因。',
            'return_reason.max' => '退回原因最多 1000 字。',
        ];
    }
}
