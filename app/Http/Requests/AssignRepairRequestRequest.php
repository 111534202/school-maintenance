<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 主管「派工」／「重新指派」表單的驗證規則：一定要挑一位維修人員，處理日期選填。
 * devices/users 表合併後改成挑選真正的 users（角色須為 technician），
 * 不再讓主管自己打字輸入姓名，減少同名不同人、打錯字等問題。
 */
class AssignRepairRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Rule::exists()->where() 的 closure 收到的是純 query builder（不是 Eloquent
        // Builder），不能用 whereHas()，所以先查出「有勾選可被指派為維修人員」的身分 id，
        // 直接用 role_id 這個外鍵欄位比對；停用中的帳號也不能被指派。
        $assignableRoleIds = Role::withPermission('repairs.assignable')->pluck('id')->all();

        return [
            'assigned_to' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role_id', $assignableRoleIds)->where('is_active', true)),
            ],
            'scheduled_at' => ['nullable', 'date'],
        ];
    }
}
