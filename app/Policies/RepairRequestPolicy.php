<?php

namespace App\Policies;

use App\Models\RepairRequest;   // 報修單資料表模型
use App\Models\User;            // 用戶資料表模型

/**
 * 報修單的「歸屬權限」：決定「這位用戶能不能對『這一張』報修單做某件事」。
 *
 * 【為什麼需要它？】身分主檔勾選的權限（repairs.process、repairs.accept…）只回答
 * 「這個身分能不能做這類事」，不管是哪一張單。例如維修人員有「處理維修」權限，
 * 但不該能處理「指派給別人」的案件；教師有「驗收」權限，但不該能驗收「別人報修」的案件。
 * 這支 Policy 補上「這一張單跟你有沒有關係」的檢查，兩層都要通過才能操作：
 *   第一層：路由的 can:權限代碼（身分有沒有開放這類功能）；
 *   第二層：這裡（這張單是不是你的）。
 *
 * 【規則】系統管理員不受限制；其他人：
 * - 檢視（view）：能派工的人（repairs.dispatch）看全部；其他人只看「自己報修的」或「指派給自己的」。
 * - 開始處理、填維修紀錄（start、fillLog）：只有「被指派的維修人員本人」。
 * - 驗收通過或退回（accept）：只有「報修人本人」；系統轉入、沒有報修人的案件（例如保養 NG 轉報修），
 *   則由有驗收權限的人處理。
 *
 * 使用方式：Controller 裡寫 Gate::authorize('start', $repairRequest)；畫面裡寫
 * $user->can('start', $repairRequest)；列表用 RepairRequest::visibleTo($user) 只撈看得到的。
 * 這個類別放在 app/Policies，Laravel 會依命名（模型名稱 + Policy）自動找到，不用另外註冊。
 */
class RepairRequestPolicy
{
    /** 能看全部報修單的人：系統管理員，以及有「派工」權限的人（要看全部才能派工）。 */
    public static function canSeeAll(User $user): bool
    {
        return $user->isAdmin() || $user->can('repairs.dispatch');
    }

    /** 檢視這張報修單：能看全部的人，或這張單是自己報修的／指派給自己的。 */
    public function view(User $user, RepairRequest $repairRequest): bool
    {
        return self::canSeeAll($user)
            || $repairRequest->reporter_id === $user->id
            || $repairRequest->assigned_to === $user->id;
    }

    /** 開始處理：必須有處理維修權限，而且（管理員或）這張單指派給自己。 */
    public function start(User $user, RepairRequest $repairRequest): bool
    {
        return $user->can('repairs.process')
            && ($user->isAdmin() || $repairRequest->assigned_to === $user->id);
    }

    /** 填寫維修紀錄：規則和「開始處理」一樣。 */
    public function fillLog(User $user, RepairRequest $repairRequest): bool
    {
        return $this->start($user, $repairRequest);
    }

    /** 驗收（通過結案或退回重修）：必須有驗收權限，而且（管理員、或是報修人本人、或這張單沒有報修人）。 */
    public function accept(User $user, RepairRequest $repairRequest): bool
    {
        return $user->can('repairs.accept')
            && ($user->isAdmin()
                || $repairRequest->reporter_id === null
                || $repairRequest->reporter_id === $user->id);
    }
}
