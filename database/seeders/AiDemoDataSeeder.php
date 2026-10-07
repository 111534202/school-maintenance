<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\Device;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceResult;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * AI 測試資料集（第 3 週任務 6，第 4 週擴充成「情境」資料）。
 *
 * 目的：用「可重現的模擬歷史」展示第 4 週的規則式風險評分，不是真實營運資料，
 * 也沒有拿它訓練任何模型。每台設備是一個刻意設計的情境（由舊到新，最後一筆是上個月）：
 *
 *   DEV-0001   ok ok ok ok ng ok   風險低（約 0.08）          → 不觸發
 *   DEV-AI-001 ok ok ng ok ok ok   風險低（約 0.08）          → 不觸發
 *   DEV-AI-002 ok ng ok ng ng ng   風險高（約 0.63）          → 觸發，產生待審核候選
 *   DEV-AI-003 ok ok ok            只有 3 筆，資料不足         → 不評分、不觸發
 *   DEV-AI-004 ok ok ok ng ng ng   風險高（約 0.55）但已有 7 天後到期的定期工單 → 去重，不重複提出
 *
 * 預設門檻 0.5、最少 4 筆、去重 14 天（皆為工程實作決定，可在 AI 設定頁調整）。
 * 全部用 updateOrCreate / firstOrCreate，重跑 migrate:fresh --seed 不會變多筆或壞掉，
 * 符合「fresh --seed 後可重現 AI 測試資料」的驗收標準。
 */
class AiDemoDataSeeder extends Seeder
{
    private const REFERENCE_CODE = 'DEV-0001';

    /**
     * 每台設備的保養結果情境：由舊到新，最後一個字元對應「上個月」。o = OK、n = NG。
     */
    private const SCENARIOS = [
        'DEV-0001' => 'oooono',
        'DEV-AI-001' => 'oonooo',
        'DEV-AI-002' => 'ononnn',
        'DEV-AI-003' => 'ooo',
        'DEV-AI-004' => 'ooonnn',
    ];

    public function run(): void
    {
        $referenceDevice = Device::where('device_code', self::REFERENCE_CODE)->first();

        if (! $referenceDevice) {
            $this->command?->warn('找不到 DEV-0001，略過 AiDemoDataSeeder（請先確認 DeviceSeeder 有跑過）。');

            return;
        }

        $classroomIds = Classroom::query()->orderBy('id')->pluck('id')->values();

        if ($classroomIds->isEmpty()) {
            $this->command?->warn('沒有教室資料，略過 AiDemoDataSeeder。');

            return;
        }

        $devices = collect();
        $extraIndex = 0;

        foreach (self::SCENARIOS as $code => $pattern) {
            if ($code === self::REFERENCE_CODE) {
                $device = $referenceDevice;
            } else {
                $device = Device::updateOrCreate(
                    ['device_code' => $code],
                    [
                        'asset_code' => 'A-AI'.str_pad((string) ($extraIndex + 1), 4, '0', STR_PAD_LEFT),
                        'device_category_id' => $referenceDevice->device_category_id,
                        'brand' => $referenceDevice->brand,
                        'model' => $referenceDevice->model,
                        'serial_number' => 'SN-AI-'.str_pad((string) ($extraIndex + 1), 4, '0', STR_PAD_LEFT),
                        'warranty_until' => $referenceDevice->warranty_until,
                        'classroom_id' => $classroomIds[$extraIndex % $classroomIds->count()],
                        'status' => Device::STATUSES[0], // normal
                        'is_core' => false,
                    ]
                );
                $extraIndex++;
            }

            $plan = $this->seedHistoryForDevice($device, $pattern);
            $devices->push($device);

            // 去重展示情境：DEV-AI-004 風險高，但已經有一張 7 天後到期的定期保養工單。
            if ($code === 'DEV-AI-004') {
                $this->seedOpenPeriodicOrder($device, $plan);
            }
        }

        $this->command?->info('AI 測試資料集已就緒：'.$devices->count().' 台同型號設備（'.$referenceDevice->brand.' '.$referenceDevice->model.'），各有不同的保養歷史情境（觸發／不觸發／資料不足／去重）。');
    }

    private function seedHistoryForDevice(Device $device, string $pattern): MaintenancePlan
    {
        $plan = MaintenancePlan::updateOrCreate(
            ['name' => 'AI 測試資料：'.$device->device_code.' 月檢計畫'],
            [
                'device_id' => $device->id,
                'device_category' => $device->category?->name,
                'cycle_days' => 30,
                'start_date' => Carbon::now()->subMonths(6)->startOfMonth(),
                'next_due_date' => Carbon::now()->addMonth()->startOfMonth()->addDays(4),
                'is_active' => true,
            ]
        );

        $results = str_split($pattern);
        $count = count($results);

        foreach ($results as $index => $code) {
            // 最後一筆 = 1 個月前，往前依序遞增。
            $monthsAgo = $count - $index;
            $scheduledDate = Carbon::now()->subMonths($monthsAgo)->startOfMonth()->addDays(4);

            // 用 whereDate 比對排定日期：不同資料庫儲存日期的格式不一樣（有的帶 00:00:00），
            // 直接用 firstOrCreate 比對字串會漏掉既有紀錄，導致重跑 seeder 時重複建立工單。
            $order = MaintenanceOrder::query()
                ->where('maintenance_plan_id', $plan->id)
                ->where('source', MaintenanceOrder::SOURCE_PERIODIC)
                ->whereDate('scheduled_date', $scheduledDate->toDateString())
                ->first()
                ?? MaintenanceOrder::create([
                    'maintenance_plan_id' => $plan->id,
                    'source' => MaintenanceOrder::SOURCE_PERIODIC,
                    'scheduled_date' => $scheduledDate->toDateString(),
                    'device_id' => $device->id,
                    'device_category' => $device->category?->name,
                    'status' => MaintenanceOrder::STATUS_COMPLETED,
                ]);

            // 命中既有紀錄時不會套用 create 的欄位，這裡補確保狀態一定是已完成。
            if ($order->status !== MaintenanceOrder::STATUS_COMPLETED) {
                $order->update(['status' => MaintenanceOrder::STATUS_COMPLETED]);
            }

            $isNg = $code === 'n';

            MaintenanceResult::updateOrCreate(
                ['maintenance_order_id' => $order->id],
                [
                    'result' => $isNg ? MaintenanceResult::RESULT_NG : MaintenanceResult::RESULT_OK,
                    'executed_by' => 'AI 測試資料 Seeder',
                    'executed_at' => $scheduledDate->copy()->addHours(10),
                    'notes' => $isNg
                        ? '工程實作：AI 測試資料集刻意安排的異常紀錄，用於展示風險評分。'
                        : null,
                    'ng_conversion_status' => $isNg ? MaintenanceResult::NG_CONVERSION_PENDING : null,
                ]
            );
        }

        return $plan;
    }

    private function seedOpenPeriodicOrder(Device $device, MaintenancePlan $plan): void
    {
        MaintenanceOrder::firstOrCreate(
            [
                'maintenance_plan_id' => $plan->id,
                'source' => MaintenanceOrder::SOURCE_PERIODIC,
                'status' => MaintenanceOrder::STATUS_PENDING,
            ],
            [
                'device_id' => $device->id,
                'device_category' => $device->category?->name,
                'scheduled_date' => Carbon::today()->addDays(7)->toDateString(),
            ]
        );
    }
}
