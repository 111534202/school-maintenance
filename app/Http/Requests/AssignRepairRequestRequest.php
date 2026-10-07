<?php

namespace App\Http\Requests;

use App\Models\Role;                                          // 身分資料表模型
use Illuminate\Contracts\Validation\ValidationRule;           // 驗證規則的型別（只用於下面的型別說明）
use Illuminate\Foundation\Http\FormRequest;                   // 「表單請求」父類別：把驗證規則獨立成一支類別
use Illuminate\Validation\Rule;                               // 進階驗證規則

/**
 * 主管「派工」／「重新指派」表單的驗證規則：一定要挑一位維修人員，處理日期選填。
 * devices/users 表合併後改成挑選真正的 users（身分須有勾選「可被指派為維修人員」權限），
 * 不再讓主管自己打字輸入姓名，減少同名不同人、打錯字等問題。
 *
 * 【FormRequest 是什麼？】Controller 方法的參數寫成這個類別時，Laravel 會在進入方法之前
 * 先依 rules() 驗證表單；不通過就自動導回表單並顯示錯誤，通過才會執行 Controller。
 */
class AssignRepairRequestRequest extends FormRequest
{
    /** 這個人有沒有資格送這張表單。權限已由路由的 can:repairs.dispatch 把關，所以這裡一律放行。 */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 驗證規則：欄位名稱 => 規則清單。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Rule::exists()->where() 的 closure 收到的是純 query builder（不是 Eloquent
        // Builder），不能用 whereHas()，所以先查出「有勾選可被指派為維修人員」的身分 id，
        // 直接用 role_id 這個外鍵欄位比對；停用中的帳號也不能被指派。
        $assignableRoleIds = Role::withPermission('repairs.assignable')->pluck('id')->all();

        return [
            // assigned_to（維修人員）：必填、必須是整數，而且要是 users 表裡「身分可被指派 + 帳號啟用中」的人。
            // 這樣即使有人偽造表單、塞入別人的編號，也會被擋下來。
            'assigned_to' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role_id', $assignableRoleIds)->where('is_active', true)),
            ],
            // 預計處理時間：可以不填；有填就必須是正確的日期時間格式。
            'scheduled_at' => ['nullable', 'date'],
        ];
    }
}
