<?php

namespace App\Mail;

use App\Models\RepairRequest;                      // 報修單資料表模型
use App\Models\User;                               // 用戶資料表模型
use Illuminate\Bus\Queueable;                      // 讓信件可以丟進「佇列」排隊慢慢寄（目前沒用到佇列，但保留標準寫法）
use Illuminate\Mail\Mailable;                      // 所有「信件」類別的父類別
use Illuminate\Mail\Mailables\Content;             // 信件內容（用哪個版面、帶哪些資料）
use Illuminate\Mail\Mailables\Envelope;            // 信封資訊（主旨等）
use Illuminate\Queue\SerializesModels;             // 讓信件裡的資料模型可以安全地存進佇列

/**
 * 派工（或重新指派）成立時寄出的通知信。同一封信會寄給維修人員（to）並
 * 副本給所有「身分有勾選『接收派工通知副本』權限」的帳號（cc，預設是設備管理員），
 * 內容一樣，讓雙方都知道是哪張案件、指派給誰、預計什麼時候處理。
 * 收件人怎麼決定見 App\Services\RepairAssignmentNotifier。
 *
 * 目前 .env 是 MAIL_MAILER=log（信件內容寫進 storage/logs/laravel.log，
 * 不會真的寄出），之後要接上真的 SMTP，只需要改 .env 設定，這支 Mailable
 * 完全不用改，Laravel 的 Mail facade 會自動套用設定檔裡的寄信方式。
 *
 * 想改信件的文字與版面：改 resources/views/emails/repairs/dispatched.blade.php（內容）
 * 與 lang/各語言資料夾/mail.php（主旨與文字）。
 */
class RepairDispatchedMail extends Mailable
{
    use Queueable, SerializesModels;

    // 建構子：建立這封信時要給的資料。參數前面寫 public，等於同時宣告了同名屬性，
    // 之後 $this->repairRequest、$this->technician 就能直接用，信件版面裡也能拿到。
    public function __construct(
        public RepairRequest $repairRequest,
        public User $technician,
    ) {
    }

    /** 信封：決定主旨。 */
    public function envelope(): Envelope
    {
        return new Envelope(
            // 主旨文字來自翻譯檔，並帶入報修標題。
            subject: __('mail.dispatched.subject', ['title' => $this->repairRequest->title]),
        );
    }

    /** 信件內容：用哪個 markdown 版面，以及要傳給版面哪些資料。 */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.repairs.dispatched',   // 對應 resources/views/emails/repairs/dispatched.blade.php
            with: [
                'repairRequest' => $this->repairRequest,
                'technician' => $this->technician,
            ],
        );
    }
}
