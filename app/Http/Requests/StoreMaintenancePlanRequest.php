<?php

namespace App\Http\Requests;

use App\Models\Device;
use App\Models\DeviceCategory;
use App\Models\MaintenancePlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * 新增／修改保養計畫共用的驗證（UpdateMaintenancePlanRequest 繼承這支）。
 *
 * 依《報修與保養流程修改說明》：
 * - 保養對象：可以有好幾個，每一個是「設備」或「設備類別」（表單用可搜尋的下拉選單，送出 targets[]＝device:編號 或 category:編號），至少一個、不能重複。
 * - 「保養項目」勾選改成直接輸入「說明」（必填）。
 */
class StoreMaintenancePlanRequest extends FormRequest
{
    /** @var list<array{device_id: int|null, device_category_id: int|null}> */
    private array $parsedTargets = [];

    public function authorize(): bool
    {
        return true;
    }

    /** 拿掉空白的列（新增了一列卻沒選）。 */
    protected function prepareForValidation(): void
    {
        $targets = array_values(array_filter((array) $this->input('targets', []), fn ($value) => is_string($value) && trim($value) !== ''));
        $this->merge(['targets' => $targets]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:2000'],
            'targets' => ['required', 'array', 'min:1', 'max:100'],
            'targets.*' => ['string', 'distinct', 'regex:/^(device|category):\d+$/'],
            'cycle_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'start_date' => ['required', 'date', 'after_or_equal:2020-01-01', 'before:2038-01-01'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** 選的設備／類別要真的存在；已淘汰的設備不能選（修改時原本就選了的照樣保留）。 */
    public function after(): array
    {
        return [
            // 下次到期日（起始日＋週期）不能已經過了：例如今天 11/21，起始日 11/17、週期 3 天 → 下次到期 11/20，已經過期，擋下。
            // 修改舊計畫時，只有改了起始日或週期才檢查（沒改的話照原本的資料，不影響只改名稱、說明）。
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['start_date', 'cycle_days'])) {
                    return;
                }
                $plan = $this->route('maintenancePlan');
                $changed = ! $plan instanceof MaintenancePlan
                    || $plan->start_date?->toDateString() !== \Illuminate\Support\Carbon::parse($this->input('start_date'))->toDateString()
                    || (int) $plan->cycle_days !== (int) $this->input('cycle_days');
                if (! $changed) {
                    return;
                }
                $nextDue = \Illuminate\Support\Carbon::parse($this->input('start_date'))->startOfDay()->addDays((int) $this->input('cycle_days'));
                if ($nextDue->lt(today())) {
                    $validator->errors()->add('start_date', '依起始日加上週期設定，下次到期日是 ' . $nextDue->toDateString() . '，已經過了。請修改起始日或將週期調長。');
                }
            },
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                $plan = $this->route('maintenancePlan');
                $kept = $plan instanceof MaintenancePlan ? $plan->targets()->pluck('device_id')->push($plan->device_id)->filter()->all() : [];

                foreach ($this->input('targets', []) as $index => $value) {
                    [$type, $id] = explode(':', $value, 2);
                    $id = (int) $id;
                    if ($type === 'device') {
                        $status = Device::query()->whereKey($id)->value('status');
                        if ($status === null) {
                            $validator->errors()->add("targets.$index", '找不到這台設備，請重新選擇。');
                        } elseif ($status === 'retired' && ! in_array($id, $kept, true)) {
                            $validator->errors()->add("targets.$index", '已淘汰的設備不能建立保養計畫。');
                        }
                        $this->parsedTargets[] = ['device_id' => $id, 'device_category_id' => null];
                    } else {
                        if (! DeviceCategory::query()->whereKey($id)->exists()) {
                            $validator->errors()->add("targets.$index", '找不到這個設備類別，請重新選擇。');
                        }
                        $this->parsedTargets[] = ['device_id' => null, 'device_category_id' => $id];
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'targets.required' => '請至少選擇一個保養的設備或設備類別。',
            'targets.min' => '請至少選擇一個保養的設備或設備類別。',
            'targets.*.distinct' => '同一個設備或類別不能重複選。',
            'targets.*.regex' => '請從清單中選擇設備或設備類別。',
            'description.required' => '請輸入保養說明。',
        ];
    }

    public function attributes(): array
    {
        return ['name' => '計畫名稱', 'description' => '說明', 'targets' => '設備／設備類別', 'cycle_days' => '保養週期', 'start_date' => '起始日'];
    }

    /** 驗證過的保養對象清單（依表單順序）。 @return list<array{device_id: int|null, device_category_id: int|null}> */
    public function targets(): array
    {
        return $this->parsedTargets;
    }

    /** 存進計畫本身的欄位：第一個對象另外存在 device_id／device_category_id（給舊的程式讀）。 */
    public function planData(): array
    {
        $first = $this->parsedTargets[0] ?? ['device_id' => null, 'device_category_id' => null];

        return $this->safe()->only(['name', 'description', 'cycle_days', 'start_date', 'is_active']) + $first;
    }
}
