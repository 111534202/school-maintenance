<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRepairLogRequest extends FormRequest
{
    /**只有有權限填寫這張報修單的維修人員才能送出。
     */
    public function authorize(): bool
    {
        return $this->user()->can(
            'fillLog',
            $this->route('repair_request')
        );
    }

    /**
     * 驗證維修紀錄與零件使用明細。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cause' => ['required', 'string', 'max:5000'],
            'resolution' => ['required', 'string', 'max:5000'],
            // 維修時間不能是未來（容許 5 分鐘的電腦時間誤差）；也避免填到 2038 年之後造成資料庫寫入失敗。
            'started_at' => ['required', 'date', 'after:1971-01-01', 'before_or_equal:+5 minutes'],
            'ended_at' => [
                'required',
                'date',
                'after_or_equal:started_at',
                'after:1971-01-01',
                'before_or_equal:+5 minutes',
            ],

            // 零件使用明細：允許完全不使用零件。
            'part_usages' => ['nullable', 'array'],

            // 每種零件最多選一次，並且必須存在於零件資料表。
            'part_usages.*.part_id' => [
                'nullable',
                'integer',
                'distinct',
                'exists:parts,id',
            ],

            // 有選零件時，數量必須是大於 0 的整數。
            'part_usages.*.quantity' => [
                'nullable',
                'required_with:part_usages.*.part_id',
                'integer',
                'min:1',
            ],

            // 保留舊欄位相容性，不再把它當成正式的庫存扣除依據。
            'parts_used_note' => ['nullable', 'string', 'max:255'],

            // 最多 5 個附件，加起來 5 MB 以內（單檔不另外限制；上限在 App\Support\AttachmentLimit）。
            'attachments' => ['nullable', 'array', 'max:' . \App\Support\AttachmentLimit::MAX_FILES, \App\Support\AttachmentLimit::totalSizeRule()],
            'attachments.*' => [
                'file',
                'mimes:' . \App\Support\AttachmentLimit::MIMES,
            ],
        ];
    }

    /** 時間欄位的錯誤訊息：不要把「+5 minutes」這種程式用語顯示給使用者。 */
    public function messages(): array
    {
        return [
            'started_at.before_or_equal' => ':attribute 不能晚於現在的時間。',
            'ended_at.before_or_equal' => ':attribute 不能晚於現在的時間。',
            'started_at.after' => ':attribute 不是合理的日期，請重新選擇。',
            'ended_at.after' => ':attribute 不是合理的日期，請重新選擇。',
        ];
    }
}
