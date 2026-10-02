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
 * AI 測試資料集（第 3 週任務 6）。
 *
 * 目的：模擬「多台同型號設備」各自的保養歷史，讓第 4 週的 AI 風險評分/預防工單候選
 * 有比較真實的資料可以展示，而不是每台設備都只有 0~1 筆保養紀錄。
 *
 * 工程實作決定（待全組 /AI 任務正式確認前先這樣跑，之後要換成別的情境只要改這個檔案）：
 *   - 固定沿用 DeviceSeeder 既有的 DEV-0001（Dell OptiPlex 7010）當作參考設備，
 *     另外新增 3 台同廠牌/型號的設備（DEV-AI-001 ~ DEV-AI-003），分散到不同教室。
 *   - 這 4 台設備（含 DEV-0001）各自掛一個月檢保養計畫，補近 6 個月、每月一筆「已完成」
 *     保養工單 + 結果，其中每台設備都刻意安排 1 筆 NG（倒數第 2 個月），
 *     讓 OK/NG 比例看起來有變化，不是全部都正常，方便驗證 ng_rate 這類特徵有沒有算對。
 *   - 全部用 updateOrCreate / firstOrCreate，重跑 migrate:fresh --seed 不會變多筆或壞掉，
 *     符合「fresh --seed 後可重現 AI 測試資料」的驗收標準。
 */
class AiDemoDataSeeder extends Seeder
{
    private const EXTRA_DEVICE_CODES = ['DEV-AI-001', 'DEV-AI-002', 'DEV-AI-003'];

    public function run(): void
    {
        $referenceDevice = Device::where('device_code', 'DEV-0001')->first();

        if (! $referenceDevice) {
            $this->command?->warn('找不到 DEV-0001，略過 AiDemoDataSeeder（請先確認 DeviceSeeder 有跑過）。');

            return;
        }

        $classroomIds = Classroom::query()->orderBy('id')->pluck('id')->values();

        if ($classroomIds->isEmpty()) {
            $this->command?->warn('沒有教室資料，略過 AiDemoDataSeeder。');

            return;
        }

        $devices = collect([$referenceDevice]);

        foreach (self::EXTRA_DEVICE_CODES as $index => $code) {
            $devices->push(Device::updateOrCreate(
                ['device_code' => $code],
                [
                    'asset_code' => 'A-AI'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                    'device_category_id' => $referenceDevice->device_category_id,
                    'brand' => $referenceDevice->brand,
                    'model' => $referenceDevice->model,
                    'serial_number' => 'SN-AI-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                    'warranty_until' => $referenceDevice->warranty_until,
                    'classroom_id' => $classroomIds[$index % $classroomIds->count()],
                    'status' => Device::STATUSES[0], // normal
                    'is_core' => false,
                ]
            ));
        }

        foreach ($devices as $device) {
            $this->seedHistoryForDevice($device);
        }

        $this->command?->info('AI 測試資料集已就緒：'.$devices->count().' 台同型號設備（'.$referenceDevice->brand.' '.$referenceDevice->model.'），各 6 個月保養歷史。');
    }

    private function seedHistoryForDevice(Device $device): void
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

        for ($monthsAgo = 6; $monthsAgo >= 1; $monthsAgo--) {
            $scheduledDate = Carbon::now()->subMonths($monthsAgo)->startOfMonth()->addDays(4);

            $order = MaintenanceOrder::firstOrCreate(
                [
                    'maintenance_plan_id' => $plan->id,
                    'source' => MaintenanceOrder::SOURCE_PERIODIC,
                    'scheduled_date' => $scheduledDate->toDateString(),
                ],
                [
                    'device_id' => $device->id,
                    'device_category' => $device->category?->name,
                    'status' => MaintenanceOrder::STATUS_COMPLETED,
                ]
            );

            // firstOrCreate 命中既有紀錄時不會套用第二個陣列，這裡補確保狀態一定是已完成。
            if ($order->status !== MaintenanceOrder::STATUS_COMPLETED) {
                $order->update(['status' => MaintenanceOrder::STATUS_COMPLETED]);
            }

            // 每台設備都在倒數第 2 個月刻意安排 1 筆 NG，其餘都是 OK。
            $isNg = $monthsAgo === 2;

            MaintenanceResult::firstOrCreate(
                ['maintenance_order_id' => $order->id],
                [
                    'result' => $isNg ? MaintenanceResult::RESULT_NG : MaintenanceResult::RESULT_OK,
                    'executed_by' => 'AI 測試資料 Seeder',
                    'executed_at' => $scheduledDate->copy()->addHours(10),
                    'notes' => $isNg
                        ? '工程實作：AI 測試資料集刻意安排的異常紀錄，用於驗證 NG 比例特徵計算是否正確。'
                        : null,
                    'ng_conversion_status' => $isNg ? MaintenanceResult::NG_CONVERSION_PENDING : null,
                ]
            );
        }
    }
}
