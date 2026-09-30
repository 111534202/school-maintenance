<?php

namespace App\Mail;

use App\Models\RepairRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * 派工（或重新指派）成立時寄出的通知信。同一封信會寄給維修人員（to）並
 * 副本給所有「設備管理員」角色（it_manager，cc），內容一樣，讓雙方都
 * 知道是哪張案件、指派給誰、預計什麼時候處理。
 *
 * 目前 .env 是 MAIL_MAILER=log（信件內容寫進 storage/logs/laravel.log，
 * 不會真的寄出），之後要接上真的 SMTP，只需要改 .env 設定，這支 Mailable
 * 完全不用改，Laravel 的 Mail facade 會自動套用設定檔裡的寄信方式。
 */
class RepairDispatchedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public RepairRequest $repairRequest,
        public User $technician,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.dispatched.subject', ['title' => $this->repairRequest->title]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.repairs.dispatched',
            with: [
                'repairRequest' => $this->repairRequest,
                'technician' => $this->technician,
            ],
        );
    }
}
