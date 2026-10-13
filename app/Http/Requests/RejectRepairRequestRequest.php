<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;           // 驗證規則的型別（只用於下面的型別說明）
use Illuminate\Foundation\Http\FormRequest;                   // 「表單請求」父類別：把驗證規則獨立成一支類別

/**
 * 「驗收不通過，退回處理」表單的驗證規則。
 * 一定要填寫退回原因，不然維修人員不知道要補做什麼。
 * （FormRequest 的運作方式見 AssignRepairRequestRequest.php 檔頭。）
 */
class RejectRepairRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * 這個人有沒有資格送這張表單：路由的 can:repairs.accept 先檢查「身分有沒有驗收權限」，
     * 這裡再檢查「這一張單是不是你能驗收的」（報修人本人，規則見 App\Policies\RepairRequestPolicy）。
     * 不通過會直接回 403，不會先顯示欄位錯誤。
     */
    public function authorize(): bool
    {
        return $this->user()->can('accept', $this->route('repair_request'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // 退回原因：必填、文字、最多 1000 字。
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
