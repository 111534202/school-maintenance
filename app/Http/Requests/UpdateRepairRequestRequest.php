<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 「修改報修單」表單的驗證規則（只有有派工權限的人能改，權限由路由的 can:repairs.dispatch 把關）。
 * 可以改標題、故障描述、影響程度、是否影響上課、設備位置／描述與設備地點；綁定的設備與附件不在這裡改。
 */
class UpdateRepairRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 和新增報修一樣：設備（已綁定）、設備位置／描述、設備地點至少要有一項，否則維修人員不知道是哪裡的設備。
     */
    public function after(): array
    {
        return [
            function ($validator) {
                $hasDevice = $this->route('repair_request')?->device_id !== null;
                $hasNote = trim((string) $this->input('device_note')) !== '';
                $hasClassroom = $this->filled('classroom_id');

                if (! $hasDevice && ! $hasNote && ! $hasClassroom) {
                    $validator->errors()->add('device_note', '請至少提供一項：填寫設備位置／描述，或選擇設備地點（這張報修單沒有綁定設備）。');
                }
            },
        ];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $current = $this->route('repair_request');

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'impact_level' => ['required', 'string', 'in:low,medium,high'],
            'is_class_affected' => ['required', 'boolean'],
            'device_note' => ['nullable', 'string', 'max:255'],
            // 設備地點：現有、啟用中的教室；目前已經選的那間即使後來停用，也可以維持不變。
            'classroom_id' => ['nullable', 'integer', Rule::exists('classrooms', 'id')->whereNull('deleted_at')->where(function ($query) use ($current) {
                $query->where('is_active', true)->orWhere('id', $current?->classroom_id);
            })],
        ];
    }
}
