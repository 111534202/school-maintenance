<?php

namespace App\Http\Controllers;

use App\Models\MailSetting;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * 系統管理 → 寄信設定（需要 mail-settings.manage；預設只有系統管理員）。
 *
 * 管理員在這裡填寄信用的信箱帳號（例如學校的 Gmail／Google Workspace 信箱＋應用程式密碼），
 * 「啟用」後全站寄信（忘記密碼臨時密碼信、派工通知信）就改用這組設定，不用改 .env、不用重新部署。
 * 密碼加密存放，畫面不會顯示；密碼欄留空代表「不修改」。操作紀錄只記「改了哪些欄位」，不記任何密碼。
 */
class MailSettingController extends Controller
{
    /** 設定頁（GET /mail-settings）。 */
    public function edit()
    {
        return view('mail_settings.edit', [
            'setting' => MailSetting::current(),
            'presets' => MailSetting::PRESETS,
            'envMailer' => config('mail.default'),
        ]);
    }

    /** 儲存設定（PUT /mail-settings）。 */
    public function update(Request $request): RedirectResponse
    {
        $setting = MailSetting::current();

        $data = $request->validate([
            'host' => ['required', 'string', 'max:190'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'encryption' => ['required', Rule::in(['tls', 'ssl', 'none'])],
            'smtp_account' => ['nullable', 'string', 'max:190'],
            // 第一次儲存且有填帳號時一定要有密碼；之後留空代表沿用原本的密碼。
            'password' => [Rule::requiredIf(fn () => $request->filled('smtp_account') && blank($setting?->password)), 'nullable', 'string', 'max:255'],
            'from_address' => ['required', 'email', 'max:190'],
            'from_label' => ['nullable', 'string', 'max:100'],
            'is_enabled' => ['sometimes', 'boolean'],
        ]);

        $data['is_enabled'] = $request->boolean('is_enabled');
        $data['updated_by'] = $request->user()->id;
        // 密碼留空 → 不動原本的密碼。
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }
        // 沒填帳號（例如校內不需登入的寄信伺服器）就連密碼一起清掉。
        if (blank($data['smtp_account'] ?? null)) {
            $data['smtp_account'] = null;
            $data['password'] = null;
        }

        if ($setting) {
            $setting->fill($data);
            $changedFields = array_keys($setting->getDirty());
            $setting->save();
            $changedFields = array_values(array_diff($changedFields, ['updated_by']));
        } else {
            $setting = MailSetting::create($data);
            $changedFields = null;
        }

        // 只記改了哪些欄位，密碼不論有沒有改都只寫「password」這個字，不寫內容。
        AuditLogger::log($changedFields === null ? 'created' : 'updated', $setting, $changedFields === null ? [] : ['fields' => $changedFields]);

        return redirect()->route('mail-settings.edit')->with('success', __('mail_settings.saved'));
    }

    /** 寄一封測試信（POST /mail-settings/test）：用「已儲存」的設定寄，不論是否啟用，方便先測再啟用。 */
    public function test(Request $request): RedirectResponse
    {
        $data = $request->validate(['recipient' => ['required', 'email', 'max:190']]);
        $setting = MailSetting::current();

        if (! $setting) {
            return redirect()->route('mail-settings.edit')->with('error', __('mail_settings.test_need_save'));
        }

        try {
            $setting->applyToConfig();
            Mail::purge('smtp');   // 清掉舊連線，確保用剛套用的設定
            Mail::raw(__('mail_settings.test_body'), function ($message) use ($data) {
                $message->to($data['recipient'])->subject(__('mail_settings.test_subject'));
            });
        } catch (Throwable $e) {
            AuditLogger::log('mail_tested', $setting, ['result' => 'failed']);

            return redirect()->route('mail-settings.edit')
                ->withInput(['recipient' => $data['recipient']])
                ->with('error', __('mail_settings.test_failed', ['reason' => $this->safeReason($e, $setting)]));
        }

        AuditLogger::log('mail_tested', $setting, ['result' => 'sent']);

        return redirect()->route('mail-settings.edit')->with('success', __('mail_settings.test_sent', ['recipient' => $data['recipient']]));
    }

    /** 把錯誤訊息整理成給人看的一句話，並確保不會把密碼帶出去。 */
    private function safeReason(Throwable $e, MailSetting $setting): string
    {
        $message = preg_replace('/\s+/', ' ', $e->getMessage());
        if (filled($setting->password)) {
            $message = str_replace($setting->password, '＊＊＊', $message);
        }

        return mb_strimwidth($message, 0, 300, '…');
    }
}
