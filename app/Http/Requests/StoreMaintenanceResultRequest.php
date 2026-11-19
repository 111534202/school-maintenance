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
            // 執行人不用填：由登入帳號自動帶入（controller 寫入 executed_by，指向 users.id）。
            'executed_at' => ['required', 'date', 'after:1971-01-01', 'before_or_equal:+5 minutes'],   // 執行時間不能是未來
            'note' => ['nullable', 'string', 'max:2000'],

            // 保養用料（可以不填）：每種零件最多選一次，有選零件時數量必須是正整數。
            'part_usages' => ['nullable', 'array'],
            'part_usages.*.part_id' => ['nullable', 'integer', 'distinct', 'exists:parts,id'],
            'part_usages.*.quantity' => ['nullable', 'required_with:part_usages.*.part_id', 'integer', 'min:1'],

            // 附件（保養前後照片等，可以不填）：和報修共用規則，最多 5 個、加起來 5 MB 以內（App\Support\AttachmentLimit）。
            'attachments' => ['nullable', 'array', 'max:' . \App\Support\AttachmentLimit::MAX_FILES, \App\Support\AttachmentLimit::totalSizeRule()],
            'attachments.*' => ['file', 'mimes:' . \App\Support\AttachmentLimit::MIMES],
        ];
    }

    /** 時間欄位的錯誤訊息：不要把「+5 minutes」這種程式用語顯示給使用者。 */
    public function messages(): array
    {
        return [
            'executed_at.before_or_equal' => ':attribute 不能晚於現在的時間。',
            'executed_at.after' => ':attribute 不是合理的日期，請重新選擇。',
        ];
    }
}
