<?php

namespace App\Services;

use App\Mail\RepairDispatchedMail;
use App\Models\RepairRequest;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * 派工／重新指派成立後寄送通知信：主收件人是被指派的維修人員，副本（cc）給
 * 所有「設備管理員」（it_manager 角色）帳號，讓兩邊同步知道案件、指派對象與
 * 預計處理時間。目前 .env 是 MAIL_MAILER=log（寫進 storage/logs/laravel.log，
 * 不會真的寄出），之後要接上真的 SMTP 只需要改 .env，這支 service 不用改。
 */
class RepairAssignmentNotifier
{
    public function notify(RepairRequest $repairRequest, User $technician): void
    {
        // 副本收件人由身分主檔決定：身分有勾選「接收派工通知副本」權限、且帳號啟用中的用戶。
        $itManagerEmails = User::whereHas('role', fn ($q) => $q->withPermission('repairs.notice_cc'))
            ->where('is_active', true)
            ->pluck('email')
            ->all();

        $mail = Mail::to($technician->email);

        if (! empty($itManagerEmails)) {
            $mail->cc($itManagerEmails);
        }

        $mail->send(new RepairDispatchedMail($repairRequest, $technician));
    }
}
