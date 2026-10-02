<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

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
 */
class AuditLogger
{
    public static function log(string $action, ?Model $subject = null, array $changes = [], ?string $description = null): AuditLog
    {
        return AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'loggable_type' => $subject?->getMorphClass(),
            'loggable_id' => $subject?->getKey(),
            'changes' => $changes ?: null,
            'description' => $description ?? self::describe($action, $subject),
        ]);
    }

    /** 自動產生說明：「新增 用戶「王小明」」。事件與對象類型的中英文名稱在 lang 資料夾底下各語言的 audit.php。 */
    public static function describe(string $action, ?Model $subject = null): ?string
    {
        if ($subject === null) {
            return null;
        }

        $actionLabel = self::label('audit.actions.' . $action, $action);
        $typeLabel = self::label('audit.types.' . class_basename($subject), class_basename($subject));

        return "{$actionLabel} {$typeLabel}「" . self::subjectName($subject) . '」';
    }

    /** 這個對象「叫什麼」：依序嘗試常見的名稱欄位，都沒有就用編號。 */
    public static function subjectName(Model $subject): string
    {
        foreach (['name', 'title', 'device_code', 'room_name', 'username'] as $field) {
            $value = $subject->getAttribute($field);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return '#' . $subject->getKey();
    }

    /** 有翻譯就用翻譯，沒有就退回原本的代碼，這樣新增了新事件類型也不會顯示成一串 key。 */
    public static function label(string $key, string $fallback): string
    {
        $translated = __($key);

        return $translated === $key ? $fallback : $translated;
    }
}
