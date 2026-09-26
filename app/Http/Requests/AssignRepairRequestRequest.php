<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** 主管「派工」表單的驗證規則：一定要填維修人員，處理日期選填。 */
class AssignRepairRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * 林政寬那邊的角色權限 Middleware 本週不要求接上，暫時一律放行。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // users 表尚未合併，先用文字記錄維修人員；合併後改為 assigned_to 下拉 + exists 驗證
            'assignee_note' => ['required', 'string', 'max:255'],
            'scheduled_at' => ['nullable', 'date'],
        ];
    }
}
