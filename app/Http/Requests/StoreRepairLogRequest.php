<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;           // 驗證規則的型別（只用於下面的型別說明）
use Illuminate\Foundation\Http\FormRequest;                   // 「表單請求」父類別：把驗證規則獨立成一支類別

/**
 * 「維修填單」表單的驗證規則。ended_at 一定要晚於或等於 started_at，
 * 這樣才不會算出負的維修工時。
 * （FormRequest 的運作方式見 AssignRepairRequestRequest.php 檔頭。）
 */
class StoreRepairLogRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * 這個人有沒有資格送這張表單：路由的 can:repairs.process 先檢查「身分有沒有處理維修權限」，
     * 這裡再檢查「這一張單是不是指派給你」（規則見 App\Policies\RepairRequestPolicy）。
     * 不通過會直接回 403，不會先顯示欄位錯誤。
     */
    public function authorize(): bool
    {
        return $this->user()->can('fillLog', $this->route('repair_request'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cause' => ['required', 'string'],         // 故障原因：必填
            'resolution' => ['required', 'string'],    // 處置方式：必填
            'started_at' => ['required', 'date'],      // 開始處理時間：必填、要是日期時間
            // 結束時間：必填、要是日期時間，而且不能早於開始時間（after_or_equal:started_at）。
            'ended_at' => ['required', 'date', 'after_or_equal:started_at'],
            // 使用的備品說明（依《第四週個人工作計畫》第 4 項）。劉家芸的 parts 表跟
            // InventoryService 介面還沒確認（見 docs/待確認/Week3_劉家芸.md），先用
            // 自由文字記錄「用了什麼、用了多少」，之後介面確認後再換成真正選單 + 扣庫存。
            'parts_used_note' => ['nullable', 'string', 'max:255'],
            // 附件：圖片/PDF/影片都允許（第四週新增影片格式，方便錄短片說明維修過程）。
            // 最多 5 個檔案（max:5）；每個檔案的副檔名限定 jpg/jpeg/png/pdf/mp4/mov/webm，
            // 單檔最大 20480 KB = 20 MB。想放寬或限制，改這兩行的數字與副檔名即可。
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,pdf,mp4,mov,webm', 'max:20480'],
        ];
    }
}
