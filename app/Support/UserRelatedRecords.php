<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 找出「還指向某個用戶」的資料，用來判斷帳號能不能永久刪除。
 *
 * 永久刪除會把 users 那一列真的刪掉，但資料庫對大部分關聯的設計是「刪了就把欄位設成空白」（SET NULL）：
 * 報修單的報修人、操作紀錄的操作人、教室管理人等都會變成查不到是誰。所以只允許刪除「完全沒有任何關聯資料」的帳號
 * （例如建立了但從沒用過的帳號）。
 *
 * 做法：讀資料庫實際的外鍵，找出所有指向 users 的欄位，逐一檢查有沒有資料；之後新增的關聯欄位只要有建外鍵就會自動被檢查，
 * 不需要回來改這支程式。LABELS 只是讓錯誤訊息顯示中文名稱，沒有列到的欄位會顯示「資料表.欄位」。
 */
class UserRelatedRecords
{
    /** 「資料表.欄位」 => 給人看的名稱。 */
    private const LABELS = [
        'repair_requests.requester_id' => '報修單（報修人）',
        'repair_requests.assigned_to' => '報修單（承辦人）',
        'classroom_reservations.requester_id' => '空間申請（申請人）',
        'classroom_reservations.reviewed_by' => '空間申請（審核人）',
        'classrooms.manager_id' => '教室（管理人）',
        'audit_logs.user_id' => '操作紀錄（操作人）',
        'knowledge_base.created_by' => '知識庫（建立者）',
        'maintenance_results.executed_by' => '保養結果（執行人）',
        'part_scraps.recorded_by' => '零件報廢（登記人）',
        'preventive_candidates.decided_by' => 'AI 預防保養（決定人）',
    ];

    /**
     * 還指向這個用戶的資料：['名稱' => 筆數, ...]；空陣列代表完全沒有關聯、可以永久刪除。
     *
     * @return array<string, int>
     */
    public static function for(User $user): array
    {
        $found = [];

        foreach (Schema::getTables() as $table) {
            $tableName = $table['name'];

            foreach (Schema::getForeignKeys($tableName) as $foreignKey) {
                if ($foreignKey['foreign_table'] !== 'users' || count($foreignKey['columns']) !== 1) {
                    continue;
                }

                $column = $foreignKey['columns'][0];
                $count = DB::table($tableName)->where($column, $user->getKey())->count();

                if ($count > 0) {
                    $found[self::LABELS["$tableName.$column"] ?? "$tableName.$column"] = $count;
                }
            }
        }

        return $found;
    }

    /** 錯誤訊息用的文字，例如「報修單（報修人）2 筆、操作紀錄（操作人）35 筆」。 */
    public static function describe(array $found): string
    {
        return collect($found)->map(fn (int $count, string $label) => "{$label} {$count} 筆")->implode('、');
    }
}
