<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;           // 驗證規則的型別（只用於下面的型別說明）
use Illuminate\Foundation\Http\FormRequest;                   // 「表單請求」父類別：把驗證規則獨立成一支類別

/**
 * 「新增報修」表單的驗證規則。
 * （FormRequest 的運作方式見 AssignRepairRequestRequest.php 檔頭。）
 */
class StoreRepairRequestRequest extends FormRequest
{
    /** 這個人有沒有資格送這張表單：權限已由路由的 can:repairs.create 把關，所以這裡一律放行。 */
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
            'title' => ['required', 'string', 'max:255'],         // 報修標題：必填
            'description' => ['required', 'string'],              // 故障描述：必填
            'impact_level' => ['required', 'string', 'in:low,medium,high'],   // 影響程度：只能是這三個值之一
            'is_class_affected' => ['required', 'boolean'],           // 是否影響上課：true / false
            // devices 表已合併：掃描設備條碼建立的報修單會帶 device_id（真正外鍵）；
            // 沒有掃描、手動輸入的案件則沒有 device_id，device_note 當文字後備描述。
            'device_id' => ['nullable', 'integer', 'exists:devices,id'],
            'device_note' => ['nullable', 'string', 'max:255'],
            // 設備地點：可不填；有填就必須是現有、啟用中的教室（只能從清單選，不能自由輸入）。
            'classroom_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('classrooms', 'id')->whereNull('deleted_at')->where('is_active', true)],
            // 附件：圖片/PDF/影片都允許（依《第四週個人工作計畫》第 2 項「故障照片/影片」新增影片格式）。
            // 一筆最多 5 個檔案、加起來 5 MB 以內，單檔不另外限制（上限在 App\Support\AttachmentLimit）。
            'attachments' => ['nullable', 'array', 'max:' . \App\Support\AttachmentLimit::MAX_FILES, \App\Support\AttachmentLimit::totalSizeRule()],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,pdf,mp4,mov,webm'],
        ];
    }
}
