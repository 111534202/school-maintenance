<?php

namespace App\Actions;

use App\Enums\RepairRequestStatus;
use App\Models\RepairRequest;

/**
 * 【給王佑恩（保養/AI 模組）呼叫用】
 *
 * 這支類別的功能：當保養檢查結果是「NG（不合格）」時，自動幫使用者建立一張
 * 報修單，讓 NG 案件可以直接進到彭仕衡的報修/維修主流程，不用使用者自己
 * 重新填一次報修表單。
 *
 * 使用方式（在王佑恩的 Controller 或保養邏輯裡）：
 *
 *     use App\Actions\CreateRepairRequestFromMaintenanceNg;
 *
 *     $repairRequest = app(CreateRepairRequestFromMaintenanceNg::class)->execute(
 *         sourceLabel: 'maintenance_result:123',   // 能追溯回你那筆保養結果的字串
 *         title: '投影機保養檢查 NG：燈泡亮度不足',
 *         description: '定期保養檢查時發現燈泡亮度低於標準值，建議更換。',
 *         deviceNote: 'A101 教室投影機',
 *         impactLevel: 'medium',
 *     );
 *
 * 回傳值是一張全新的 RepairRequest，狀態是「新報修」（pending），會直接出現在
 * 維修案件看板上，主管可以照一般報修單一樣派工，後續流程完全一樣。
 *
 * 為什麼 sourceLabel 只是一個字串，不是外鍵？
 * 因為 maintenance_results 這張表現在還沒合併進 develop，先不建立假的關聯，
 * 只用文字記錄「這張報修單是哪筆保養結果轉來的」，方便追蹤。等 maintenance_results
 * 表穩定後，可以再一起把這個欄位換成正式的外鍵（不影響呼叫端的寫法）。
 */
class CreateRepairRequestFromMaintenanceNg
{
    public function execute(
        string $sourceLabel,
        string $title,
        string $description,
        ?string $deviceNote = null,
        string $impactLevel = 'medium',
    ): RepairRequest {
        return RepairRequest::create([
            'title' => $title,
            // 把來源標記寫進描述最前面，方便在報修列表/詳細頁一眼看出這張單不是
            // 使用者自己報的，而是保養檢查自動轉來的。
            'description' => "【保養 NG 自動轉入，來源：{$sourceLabel}】\n\n{$description}",
            'impact_level' => $impactLevel,
            // 保養 NG 轉來的案件，先假設不影響上課（王佑恩若知道會影響上課，
            // 可以之後在呼叫時加一個參數傳進來，目前規格沒要求，先用預設值）。
            'affects_class' => false,
            'device_note' => $deviceNote,
            'status' => RepairRequestStatus::Pending->value,
        ]);
    }
}
