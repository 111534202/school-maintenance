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
            // 附件上傳第一版：只允許常見圖片格式與 PDF，單檔上限 5MB，避免任意檔案類型
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }
}
