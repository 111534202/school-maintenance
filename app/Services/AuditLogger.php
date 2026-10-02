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
            'description' => $description,
        ]);
    }
}
