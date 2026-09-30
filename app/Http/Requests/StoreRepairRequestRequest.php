<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** 「新增報修」表單的驗證規則。 */
class StoreRepairRequestRequest extends FormRequest
{
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'impact_level' => ['required', 'string', 'in:low,medium,high'],
            'affects_class' => ['required', 'boolean'],
            // devices 表已合併：掃描設備條碼建立的報修單會帶 device_id（真正外鍵）；
            // 沒有掃描、手動輸入的案件則沒有 device_id，device_note 當文字後備描述。
            'device_id' => ['nullable', 'integer', 'exists:devices,id'],
            'device_note' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            // 附件：圖片/PDF/影片都允許（依《第四週個人工作計畫》第 2 項「故障照片/影片」新增
            // 影片格式），單檔上限提高到 20MB 以容納短片，避免任意檔案類型。
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,pdf,mp4,mov,webm', 'max:20480'],
        ];
    }
}
