<?php

namespace App\Services;

use App\Enums\RepairRequestStatus;
use App\Models\Classroom;
use App\Models\RepairRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * 報修自動派工：收到新報修單後，不用等管理員手動派工，系統自動派給「目前沒有進行中工單」的維修人員。
 *
 * 規則：
 * - 誰能被派：帳號啟用中、身分有「可被指派為維修人員」（repairs.assignable）、而且目前沒有「已派工」或「處理中」的案件。
 *   （送出維修紀錄、進入「待驗收」的案件不算忙碌：維修人員已經做完了，在等報修人驗收。）
 * - 同時有好幾位閒置的：挑「最近 30 天被派過的案件數最少」的，一樣多就挑編號小的，輪流分配比較公平。
 * - 不會把案件派給報修人自己。
 * - 沒有閒置的維修人員：案件維持「新報修」，原本的「待派工」提醒會通知有派工權限的人手動派；
 *   之後有維修人員閒下來（送出維修紀錄、案件被取消或重新指派…），dispatchWaiting() 會依先後順序補派。
 * - 驗收人：報修人自己有驗收權限就不指定（由報修人本人驗收）；報修人沒有驗收權限（例如學生）或沒有報修人（保養 NG 轉入）時，
 *   自動指定一位驗收人，否則案件做完沒有人能驗收：優先是該教室的管理人，其次是同時有「派工」與「驗收」權限的人（例如主管）；
 *   都沒有就不指定（只有系統管理員能驗收，案件詳細頁會提醒管理員去重新指派時指定驗收人）。
 * - 派工會寫操作紀錄（操作人是「系統」），並寄通知信給維修人員、副本給管理員（RepairAssignmentNotifier）；
 *   信寄不出去（例如寄信設定有問題）不會讓報修或派工失敗。
 * - 可以用 .env 的 REPAIRS_AUTO_DISPATCH=false 關閉（見 config/repairs.php），全部改回手動派工。
 *
 * 狀態怎麼轉換仍然只有 RepairRequestWorkflow 說了算，這裡只是「幫管理員按派工」。
 */
class AutoRepairDispatcher
{
    public function __construct(
        private RepairRequestWorkflow $workflow,
        private RepairAssignmentNotifier $notifier,
    ) {}

    /** 自動派工功能有沒有開啟。 */
    public static function enabled(): bool
    {
        return (bool) config('repairs.auto_dispatch', true);
    }

    /**
     * 把這張新報修自動派出去。成功回傳被指派的維修人員；沒派成（功能關閉、不是「新報修」、沒有閒置的維修人員）回傳 null。
     */
    public function dispatch(RepairRequest $repairRequest): ?User
    {
        if (! self::enabled()) {
            return null;
        }

        $technician = DB::transaction(function () use ($repairRequest): ?User {
            // 鎖住這張單再確認狀態：同時有人手動派工或另一個程序也在派，只會有一個成功。
            $locked = RepairRequest::query()->whereKey($repairRequest->id)->lockForUpdate()->first();
            if ($locked === null || $locked->status !== RepairRequestStatus::Pending) {
                return null;
            }

            $technician = $this->pickIdleTechnician($locked->requester_id);
            if ($technician === null) {
                return null;
            }

            $this->workflow->assign($locked, $technician->id, null);

            $acceptor = $this->pickAcceptor($locked, $technician);
            if ($acceptor !== null) {
                $locked->update(['accepted_by' => $acceptor->id]);
            }

            AuditLogger::logAsSystem('assigned', $locked, [
                'technician' => $technician->name,
                'acceptor' => $acceptor?->name,
                'auto' => true,
                'from' => RepairRequestStatus::Pending->value,
                'to' => RepairRequestStatus::Assigned->value,
            ], __('audit.messages.repair_auto_assigned', ['technician' => $technician->name, 'title' => $locked->title]));

            return $technician;
        });

        if ($technician === null) {
            return null;
        }

        $repairRequest->refresh();
        $this->notifyQuietly($repairRequest, $technician);

        return $technician;
    }

    /**
     * 把還在等待的「新報修」依送出先後（舊的先）派給已經閒下來的維修人員；沒有閒置的人了就停。回傳派出幾張。
     * 送出維修紀錄後、排程每分鐘都會呼叫（案件取消、重新指派、新增維修人員也能補派）。
     */
    public function dispatchWaiting(): int
    {
        if (! self::enabled()) {
            return 0;
        }

        $dispatched = 0;
        $waiting = RepairRequest::query()
            ->where('status', RepairRequestStatus::Pending->value)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        foreach ($waiting as $repairRequest) {
            if ($this->dispatch($repairRequest) !== null) {
                $dispatched++;

                continue;
            }

            // 這張派不出去：沒有任何閒置的維修人員了就停（後面的也派不出去）；
            // 還有閒置的人，只是這張被別人手動派走、或唯一閒置的是報修人自己，就繼續看下一張。
            if ($this->pickIdleTechnician(null) === null) {
                break;
            }
        }

        return $dispatched;
    }

    /**
     * 找一位目前沒有進行中工單的維修人員；沒有就回傳 null。
     * 如果兩張報修同一秒進來，極少數情況可能挑到同一位（他就會同時有兩張），不影響資料正確。
     */
    private function pickIdleTechnician(?int $excludeUserId): ?User
    {
        $busyStatuses = [RepairRequestStatus::Assigned->value, RepairRequestStatus::InProgress->value];

        return User::query()
            ->where('is_active', true)
            ->when($excludeUserId, fn ($query) => $query->whereKeyNot($excludeUserId))
            ->whereHas('role', fn ($query) => $query->withPermission('repairs.assignable'))
            ->whereNotExists(function ($query) use ($busyStatuses) {
                $query->selectRaw('1')
                    ->from('repair_requests')
                    ->whereColumn('repair_requests.assigned_to', 'users.id')
                    ->whereIn('repair_requests.status', $busyStatuses)
                    ->whereNull('repair_requests.deleted_at');
            })
            ->orderBy(function ($query) {
                $query->selectRaw('count(*)')
                    ->from('repair_requests')
                    ->whereColumn('repair_requests.assigned_to', 'users.id')
                    ->where('repair_requests.created_at', '>=', now()->subDays(30));
            })
            ->orderBy('users.id')
            ->first();
    }

    /**
     * 報修人自己不能驗收時，替這張案件找一位驗收人；報修人自己能驗收（或找不到適合的人）就回傳 null。
     */
    private function pickAcceptor(RepairRequest $repairRequest, User $technician): ?User
    {
        $requester = $repairRequest->requester_id ? User::find($repairRequest->requester_id) : null;
        if ($requester !== null && $requester->hasPermission('repairs.accept')) {
            return null;
        }

        $candidates = User::query()
            ->where('is_active', true)
            ->whereKeyNot($technician->id)   // 維修的人不能自己驗收自己修的
            ->when($requester, fn ($query) => $query->whereKeyNot($requester->id))
            ->whereHas('role', fn ($query) => $query->withPermission('repairs.accept'))
            ->orderBy('id')
            ->get();

        $classroomManagerId = $repairRequest->classroom_id ? Classroom::withTrashed()->whereKey($repairRequest->classroom_id)->value('manager_id') : null;

        return $candidates->firstWhere('id', $classroomManagerId)
            ?? $candidates->first(fn (User $user) => $user->hasPermission('repairs.dispatch'));
    }

    /** 寄通知信；寄不出去只記錄錯誤，不影響已經完成的派工。 */
    private function notifyQuietly(RepairRequest $repairRequest, User $technician): void
    {
        try {
            $this->notifier->notify($repairRequest, $technician, auto: true);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
