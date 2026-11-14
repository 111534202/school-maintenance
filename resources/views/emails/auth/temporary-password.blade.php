{{-- 臨時密碼信（Markdown 郵件版面，由 App\Mail\TemporaryPasswordMail 使用）；文字來自 lang/各語言資料夾/mail.php。 --}}
<x-mail::message>
# {{ __('mail.temporary_password.heading') }}

{{ __('mail.temporary_password.intro', ['name' => $user->name, 'account' => $user->account]) }}

<x-mail::panel>
{{ $temporaryPassword }}
</x-mail::panel>

{{ __('mail.temporary_password.expires', ['time' => $expiresAt->format('Y-m-d H:i')]) }}

{{ __('mail.temporary_password.steps') }}

<x-mail::button :url="url('/login')">
{{ __('mail.temporary_password.button') }}
</x-mail::button>

{{ __('mail.temporary_password.not_you') }}

{{ __('mail.temporary_password.footer') }}
</x-mail::message>
