<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 「修改報修單」表單的驗證規則（只有有派工權限的人能改，權限由路由的 can:repairs.dispatch 把關）。
 * 可以改標題、故障描述、影響程度、是否影響上課、設備位置／描述與發現地點；還沒綁定設備的可以補上設備（掃描條碼），
 * 已派工、待驗收的報修單沒有設備時一定要補上。已綁定的設備與附件不在這裡改。
 */
class UpdateRepairRequestRequest extends FormRequest
{
    // 跟自己無關的案件看不到，也不能改（先擋權限，再檢查欄位）。
    public function authorize(): bool
    {
        $repair = $this->route('repair_request');

        // 先確認能不能改（有派工權限，或報修人自己在派工前改描述），再檢查欄位，不然沒權限的人會先看到欄位錯誤。
        return $repair === null || (
            $this->user()?->can('view', $repair)
            // 已結案的交給控制器導回並顯示「不能修改」的訊息
            && ($repair->status->isClosed() || \App\Http\Controllers\RepairRequestController::editScope($this->user(), $repair) !== null)
        );
    }

    /**
     * 和新增報修一樣：設備（已綁定）、設備位置／描述、發現地點至少要有一項，否則維修人員不知道是哪裡的設備。
     */
    /** 報修人自己（沒有派工權限）在派工前只能改故障描述。 */
    private function onlyDescription(): bool
    {
        $repair = $this->route('repair_request');

        return $repair !== null && \App\Http\Controllers\RepairRequestController::editScope($this->user(), $repair) === 'description';
    }

    public function after(): array
    {
        if ($this->onlyDescription()) {
            return [];
        }

        return [
            function ($validator) {
                $current = $this->route('repair_request');
                $hasDevice = $current?->device_id !== null || $this->filled('device_id');

                // 已派工（之後的狀態）還沒有設備：一定要補上設備才能儲存（維修人員要知道是哪一台）。
                $dispatched = $current && in_array($current->status, [\App\Enums\RepairRequestStatus::Assigned, \App\Enums\RepairRequestStatus::PendingReview], true);
                if ($dispatched && $current->device_id === null && ! $this->filled('device_id')) {
                    $validator->errors()->add('device_id', '這張報修單已派工，請掃描或輸入設備條碼，補上是哪一台設備。');

                    return;
                }
                if ($this->filled('device_id') && \App\Models\Device::query()->whereKey($this->integer('device_id'))->where('status', 'retired')->exists()) {
                    $validator->errors()->add('device_id', '這台設備已淘汰，不能報修。');
                }
                $hasNote = trim((string) $this->input('device_note')) !== '';
                $hasClassroom = $this->filled('classroom_id');

                if (! $hasDevice && ! $hasNote && ! $hasClassroom) {
                    $validator->errors()->add('device_note', '請至少提供一項：填寫設備位置／描述，或選擇發現地點（這張報修單沒有綁定設備）。');
                }

                // 發現地點和設備登記的教室不同：這次有改發現地點、或這次才補上設備時，要勾確認才能儲存（原本就不同、這次沒動的不用再勾）。
                $newDevice = $current?->device_id === null && $this->filled('device_id');
                $device = $newDevice ? \App\Models\Device::find($this->integer('device_id')) : $current?->device;
                $changedRoom = $hasClassroom && $this->integer('classroom_id') !== $current?->classroom_id;
                if ($device?->classroom_id && $hasClassroom && $this->integer('classroom_id') !== $device->classroom_id
                    && ($changedRoom || $newDevice) && ! $this->boolean('location_differs_confirmed')) {
                    $validator->errors()->add('classroom_id', '發現地點和設備登記的教室不同，請勾選「確認發現地點與設備登記的教室不同」，或改回設備所在的教室。');
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
        if ($this->onlyDescription()) {
            return ['description' => ['required', 'string', 'max:5000']];
        }

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'impact_level' => ['required', 'string', 'in:low,medium,high'],
            'is_class_affected' => ['required', 'boolean'],
            'device_note' => ['nullable', 'string', 'max:255'],
            // 補上設備（只有還沒綁定設備時才會用到；已停用的設備不能選）。
            'device_id' => ['nullable', 'integer', Rule::exists('devices', 'id')->whereNull('deleted_at')],
            // 發現地點：現有、啟用中的教室；目前已經選的那間即使後來停用，也可以維持不變。
            'classroom_id' => ['nullable', 'integer', Rule::exists('classrooms', 'id')->whereNull('deleted_at')->where(function ($query) use ($current) {
                $query->where('is_active', true)->orWhere('id', $current?->classroom_id);
            })],
        ];
    }
}
