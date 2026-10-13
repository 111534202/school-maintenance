<?php

namespace App\Services;

use App\Models\AuditLog;                       // 操作紀錄資料表模型
use Illuminate\Database\Eloquent\Model;        // 任何資料表模型（操作的對象可以是任何一種資料）
use Illuminate\Support\Facades\Auth;           // 取得目前登入的人

/**
 * 全組共用的操作紀錄入口，其他模組（報修/保養/預約...）記錄關鍵操作時
 * 一律呼叫這裡，不要各自新建 log 資料表或寫法。
 *
 * 用法：
 *   AuditLogger::log('created', $classroom);
 *   AuditLogger::log('status_changed', $device, ['from' => 'normal', 'to' => 'repairing']);
 *   AuditLogger::log('login');
 *
 * 沒有自己給 $description 時，這裡會自動產生一句人看得懂的說明（例如「新增用戶「王小明」」），
 * 而且是「寫入當下」的快照：之後那筆資料被刪除或改名，操作紀錄上看到的還是當時的名字，
 * 不會變成只剩「User #5」這種看不出是誰的代碼。
 *
 * 方法前面的 static 代表「不用先建立物件，直接寫 AuditLogger::log(...) 就能呼叫」。
 */
class AuditLogger
{
    /**
     * 寫一筆操作紀錄。
     *
     * @param string      $action      事件代碼，例如 created、updated、deleted、login（中文名稱在 lang/各語言資料夾/audit.php）
     * @param Model|null  $subject     操作的對象（用戶、設備、報修單...）；沒有對象的事件（例如登入失敗）就不用給
     * @param array       $changes     變更內容（會以 JSON 存起來，在紀錄頁的「詳細」欄顯示）。絕對不要放密碼
     * @param string|null $description 人看得懂的說明；不給的話自動產生
     */
    public static function log(string $action, ?Model $subject = null, array $changes = [], ?string $description = null): AuditLog
    {
        return AuditLog::create([
            'user_id' => Auth::id(),                           // 目前登入者；沒登入就是 null（例如登入失敗）
            'action' => $action,
            'loggable_type' => $subject?->getMorphClass(),     // 對象的類型（類別名稱）；沒有對象就是 null
            'loggable_id' => $subject?->getKey(),              // 對象的編號（主鍵）
            'changes' => $changes ?: null,                     // 空陣列存成 null，不佔空間
            'description' => $description ?? self::describe($action, $subject),   // 沒給說明就自動產生
        ]);
    }

    /** 自動產生說明：「新增 用戶「王小明」」。事件與對象類型的中英文名稱在 lang 資料夾底下各語言的 audit.php。 */
    public static function describe(string $action, ?Model $subject = null): ?string
    {
        // 沒有對象（例如登入、登出）就沒辦法產生「對誰做了什麼」的句子，回傳空值。
        if ($subject === null) {
            return null;
        }

        // 查事件與對象類型的翻譯；查不到就用原始代碼，避免畫面出現一串翻譯鍵名。
        $actionLabel = self::label('audit.actions.' . $action, $action);
        $typeLabel = self::label('audit.types.' . class_basename($subject), class_basename($subject));

        // 組成句子，例如：新增 用戶「王小明」
        return "{$actionLabel} {$typeLabel}「" . self::subjectName($subject) . '」';
    }

    /** 這個對象「叫什麼」：依序嘗試常見的名稱欄位，都沒有就用編號。 */
    public static function subjectName(Model $subject): string
    {
        // 依序找這些欄位（姓名、標題、設備編號、教室名稱、帳號），第一個有內容的就是它的名字。
        foreach (['name', 'title', 'device_code', 'room_name', 'username'] as $field) {
            $value = $subject->getAttribute($field);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        // 都沒有名稱欄位（例如某些關聯資料），就顯示「#編號」。
        return '#' . $subject->getKey();
    }

    /** 有翻譯就用翻譯，沒有就退回原本的代碼，這樣新增了新事件類型也不會顯示成一串 key。 */
    public static function label(string $key, string $fallback): string
    {
        $translated = __($key);   // __() 查翻譯；找不到時會把鍵名原樣回傳

        // 回傳值等於鍵名 = 沒有翻譯 → 改用備用文字。
        return $translated === $key ? $fallback : $translated;
    }
}
