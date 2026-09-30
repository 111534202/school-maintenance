<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** 「新增報修」表單的驗證規則。 */
class StoreRepairRequestRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'impact_level' => ['required', 'string', 'in:low,medium,high'],
            'affects_class' => ['required', 'boolean'],
            // devices 表尚未合併，先用自由文字描述設備；合併後改為 device_id 下拉 + exists 驗證
            'device_note' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            // 附件：圖片/PDF/影片都允許（依《第四週個人工作計畫》第 2 項「故障照片/影片」新增
            // 影片格式），單檔上限提高到 20MB 以容納短片，避免任意檔案類型。
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,pdf,mp4,mov,webm', 'max:20480'],
        ];
    }
}
