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
        // 能當驗收人的身分：身分主檔有勾「驗收」，或是系統管理員身分（永遠全開）。
        $acceptorRoleIds = Role::withPermission('repairs.accept')->pluck('id')->merge(Role::where('role_code', 'admin')->pluck('id'))->unique()->all();

        // 預計處理時間不能早於這張報修單送出的時間（精確到分鐘）。
        $submittedAt = $this->route('repair_request')?->created_at?->format('Y-m-d H:i') ?? '1971-01-01';

        return [
            // assigned_to（維修人員）：必填、必須是整數，而且要是 users 表裡「身分可被指派 + 帳號啟用中」的人。
            // 這樣即使有人偽造表單、塞入別人的編號，也會被擋下來。
            'assigned_to' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role_id', $assignableRoleIds)->where('is_active', true)->whereNull('deleted_at')),
            ],
            // 驗收人：可以不填（不填＝由報修人本人驗收）；有填必須是「有驗收權限（或系統管理員）、帳號啟用中」的人。
            'accepted_by' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at')->whereIn('role_id', $acceptorRoleIds)),
            ],
            // 預計處理時間：可以不填；有填就必須是正確的日期時間格式。
            // 上限 2037 年：資料庫的時間欄位超過 2038 年會寫入失敗（整頁 500），先在這裡擋下並說明。
            'scheduled_at' => ['nullable', 'date', 'after:1971-01-01', 'after_or_equal:' . $submittedAt, 'before:2038-01-01'],
        ];
    }

    /** 時間欄位的錯誤訊息：不要把「+5 minutes」這種程式用語顯示給使用者。 */
    public function messages(): array
    {
        return [
            'scheduled_at.before' => ':attribute 不能晚於 2037 年底。',
            'scheduled_at.after_or_equal' => ':attribute 不能早於報修申請的時間（:date）。',
            'scheduled_at.after' => ':attribute 不是合理的日期，請重新選擇。',
        ];
    }
}
