<?php

namespace App\Services;

use App\Models\Device;
use App\Models\RepairRequest;

/**
 * 空間預約用：查出每間教室「目前有哪些設備在等待維修」，在預約頁與預約詳細顯示黃色提醒。
 *
 * 列入提醒的情況（文字一律是「XX正在等待維修」）：
 * - 設備有未結束的報修案件（已結案、已退回不算）→ 「投影機正在等待維修」
 * - 報修案件沒有指定設備、只填了教室 → 「報修案件「冷氣不冷」正在等待維修」
 *
 * 依《報修與保養流程修改說明》：只是提醒，教室不會因為設備狀況暫停預約（核心設備與暫停預約的規則已拿掉），
 * 借用教室有時只需要空間，不一定會用到報修中的設備。
 */
class ClassroomRepairNotices
{
    /**
     * @param  iterable<int>  $classroomIds
     * @param  bool  $withTitles  false＝不帶報修單標題（免登入查詢課表用：標題是使用者自由填寫的文字，不對外公開）
     * @return array<int, list<array{text: string}>> 以教室編號為鍵；沒有狀況的教室不會出現
     */
    public static function for(iterable $classroomIds, bool $withTitles = true): array
    {
        $ids = collect($classroomIds)->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        if ($ids === []) {
            return [];
        }

        $notices = [];

        // 未結束的報修：設備在這些教室，或沒有設備但報修單填了這些教室。
        $repairs = RepairRequest::query()
            ->open()
            ->where(fn ($q) => $q
                ->whereHas('device', fn ($d) => $d->whereIn('classroom_id', $ids))
                ->orWhere(fn ($q) => $q->whereNull('device_id')->whereIn('classroom_id', $ids)))
            ->with('device.category')
            ->orderBy('id')
            ->get();

        foreach ($repairs as $repair) {
            if ($repair->device) {
                self::add($notices, $repair->device->classroom_id, self::deviceLabel($repair->device) . '正在等待維修');
            } else {
                self::add($notices, $repair->classroom_id, $withTitles ? "報修案件「{$repair->title}」正在等待維修" : '有報修案件正在等待維修');
            }
        }

        return $notices;
    }

    /** 同一間教室同樣的文字只列一次。 */
    private static function add(array &$notices, ?int $classroomId, string $text): void
    {
        if (! $classroomId) {
            return;
        }

        foreach ($notices[$classroomId] ?? [] as $item) {
            if ($item['text'] === $text) {
                return;
            }
        }

        $notices[$classroomId][] = ['text' => $text];
    }

    /** 設備類別名稱（例如「投影機」）；沒有類別時用設備編號。 */
    private static function deviceLabel(Device $device): string
    {
        return $device->category?->name ?: $device->device_code;
    }
}
