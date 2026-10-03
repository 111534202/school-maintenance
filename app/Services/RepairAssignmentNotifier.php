<?php

namespace App\Services;

use App\Mail\RepairDispatchedMail;           // 「派工通知」信件的內容與版面（見 app/Mail）
use App\Models\RepairRequest;                // 報修單資料表模型
use App\Models\User;                         // 用戶資料表模型
use Illuminate\Support\Facades\Mail;         // Laravel 的寄信功能

/**
 * 派工／重新指派成立後寄送通知信：主收件人是被指派的維修人員，副本（cc）給
 * 所有「身分有勾選『接收派工通知副本』權限」的帳號（預設是設備管理員），讓兩邊同步知道案件、
 * 指派對象與預計處理時間。目前 .env 是 MAIL_MAILER=log（寫進 storage/logs/laravel.log，
 * 不會真的寄出），之後要接上真的 SMTP 只需要改 .env，這支 service 不用改。
 *
 * 想改誰收到副本：到「身分主檔」編輯身分，勾選或取消「接收派工通知副本」即可，不用改程式。
 */
class RepairAssignmentNotifier
{
    /** 寄出派工通知信。$technician 是被指派的維修人員（主要收件人）。 */
    public function notify(RepairRequest $repairRequest, User $technician): void
    {
        // 副本收件人由身分主檔決定：身分有勾選「接收派工通知副本」權限、且帳號啟用中的用戶。
        // pluck('email') 只取 Email 欄位；all() 把結果轉成一般陣列。
        $itManagerEmails = User::whereHas('role', fn ($q) => $q->withPermission('repairs.notice_cc'))
            ->where('is_active', true)
            ->pluck('email')
            ->all();

        // 主要收件人：被指派的維修人員。
        $mail = Mail::to($technician->email);

        // 有副本收件人才加上 cc（沒有人要收副本就跳過，避免寄給空名單）。
        if (! empty($itManagerEmails)) {
            $mail->cc($itManagerEmails);
        }

        // 送出信件，內容由 RepairDispatchedMail 組成。
        $mail->send(new RepairDispatchedMail($repairRequest, $technician));
    }
}
