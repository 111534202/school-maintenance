<?php

namespace App\Mail;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * 忘記密碼時寄給本人的臨時密碼信（寄到註冊帳號時填的 Email）。
 * 信裡只有臨時密碼與有效期限；用臨時密碼登入之後，系統會要求立刻設定新密碼（見 PasswordRecoveryController）。
 * 信件內容：resources/views/emails/auth/temporary-password.blade.php；文字：lang/各語言資料夾/mail.php。
 */
class TemporaryPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $temporaryPassword,
        public CarbonInterface $expiresAt,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('mail.temporary_password.subject'));
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.auth.temporary-password',
            with: [
                'user' => $this->user,
                'temporaryPassword' => $this->temporaryPassword,
                'expiresAt' => $this->expiresAt,
            ],
        );
    }
}
