<?php

namespace App\Services;

use App\Models\Device;
use App\Models\RepairRequest;

/**
 * 空間預約用：查出每間教室「目前有哪些設備在等待維修」，在預約頁與預約詳細顯示黃色提醒。
 *
 * 列入提醒的情況（文字一律是「XX正在等待維修」）；報修單算在哪間教室：以發現地點為準，沒填發現地點的才看設備放在哪（同 RepairRequest::scopeLocatedIn）：
 * - 有設備的報修單（已結案、已退回、已中止不算）→ 「投影機正在等待維修」
 * - 沒有設備、有填「設備位置／描述」→ 「「講台旁邊的延長線插座…」正在等待維修」（前 10 個字，點了顯示完整）
 * - 沒有設備也沒有描述 → 「報修案件「冷氣不冷」正在等待維修」
 *
 * 依《報修與保養流程修改說明》：只是提醒，教室不會因為設備狀況暫停預約（核心設備與暫停預約的規則已拿掉），
 * 借用教室有時只需要空間，不一定會用到報修中的設備。
 */
class ClassroomRepairNotices
{
    /** 「設備位置／描述」在提醒裡先顯示幾個字，超過的用「…」，點了才顯示完整。 */
    public const NOTE_LENGTH = 10;

    /**
     * @param  iterable<int>  $classroomIds
     * @param  bool  $withTitles  false＝不帶報修單標題（標題是使用者自由填寫的文字）
     * @param  bool  $withNotes  false＝不帶「設備位置／描述」（免登入查詢課表用：使用者自由填寫的文字，不對外公開）
     * @return array<int, list<array{text: string, full?: string}>> 以教室編號為鍵；沒有狀況的教室不會出現；
     *         full＝描述超過 10 個字時的完整文字（畫面上點了才顯示）
     */
    public static function for(iterable $classroomIds, bool $withTitles = true, bool $withNotes = true): array
    {
        $ids = collect($classroomIds)->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        if ($ids === []) {
            return [];
        }

        $notices = [];

        // 未結束的報修：發現地點是這些教室，或沒填發現地點、設備放在這些教室。
        $repairs = RepairRequest::query()
            ->open()
            ->where(fn ($q) => $q
                ->whereIn('classroom_id', $ids)
                ->orWhere(fn ($q) => $q->whereNull('classroom_id')->whereHas('device', fn ($d) => $d->whereIn('classroom_id', $ids))))
            ->with('device.category')
            ->orderBy('id')
            ->get();

        foreach ($repairs as $repair) {
            $roomId = $repair->classroom_id ?? $repair->device?->classroom_id;
            $note = trim((string) $repair->device_note);
            if ($repair->device) {
                self::add($notices, $roomId, self::deviceLabel($repair->device) . '正在等待維修');
            } elseif ($withNotes && $note !== '') {
                $short = mb_strlen($note) > self::NOTE_LENGTH ? mb_substr($note, 0, self::NOTE_LENGTH) . '…' : $note;
                self::add($notices, $roomId, "「{$short}」正在等待維修", $short !== $note ? "「{$note}」正在等待維修" : null);
            } else {
                self::add($notices, $roomId, $withTitles ? "報修案件「{$repair->title}」正在等待維修" : '有報修案件正在等待維修');
            }
        }

        return $notices;
    }

    /** 同一間教室同樣的文字只列一次。 */
    private static function add(array &$notices, ?int $classroomId, string $text, ?string $full = null): void
    {
        if (! $classroomId) {
            return;
        }

        foreach ($notices[$classroomId] ?? [] as $item) {
            if ($item['text'] === $text) {
                return;
            }
        }

        $notices[$classroomId][] = $full !== null ? ['text' => $text, 'full' => $full] : ['text' => $text];
    }

    /** 設備類別名稱（例如「投影機」）；沒有類別時用設備編號。 */
    private static function deviceLabel(Device $device): string
    {
        return $device->category?->name ?: $device->device_code;
    }
}
