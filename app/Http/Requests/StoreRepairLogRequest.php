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
            'cause' => ['required', 'string'],
            'resolution' => ['required', 'string'],
            'started_at' => ['required', 'date'],
            'ended_at' => [
                'required',
                'date',
                'after_or_equal:started_at',
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

            // 最多 5 個附件，每個不超過 20 MB。
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => [
                'file',
                'mimes:jpg,jpeg,png,pdf,mp4,mov,webm',
                'max:20480',
            ],
        ];
    }
}