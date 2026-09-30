<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 「維修填單」表單的驗證規則。ended_at 一定要晚於或等於 started_at，
 * 這樣才不會算出負的維修工時。
 */
class StoreRepairLogRequest extends FormRequest
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
            'cause' => ['required', 'string'],
            'resolution' => ['required', 'string'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date', 'after_or_equal:started_at'],
            // 使用的備品說明（依《第四週個人工作計畫》第 4 項）。劉家芸的 parts 表跟
            // InventoryService 介面還沒確認（見 docs/待確認/Week3_劉家芸.md），先用
            // 自由文字記錄「用了什麼、用了多少」，之後介面確認後再換成真正選單 + 扣庫存。
            'parts_used_note' => ['nullable', 'string', 'max:255'],
            // 附件：圖片/PDF/影片都允許（第四週新增影片格式，方便錄短片說明維修過程）。
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,pdf,mp4,mov,webm', 'max:20480'],
        ];
    }
}
