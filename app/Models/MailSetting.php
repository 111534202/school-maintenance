<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Throwable;

/**
 * 寄信設定（mail_settings 資料表，只會有一筆）。
 *
 * 管理員在「寄信設定」頁填寫後，只要「啟用」，全站寄信（忘記密碼臨時密碼信、派工通知信…）
 * 就改用這組 SMTP 設定，取代 .env 的 MAIL_* 設定。沒有啟用或資料表還沒建立時，維持 .env 的設定。
 *
 * 密碼用 encrypted 轉換：存進資料庫時自動加密、讀出來自動解密；$hidden 讓它不會被轉成 JSON／陣列時帶出去。
 */
class MailSetting extends Model
{
    /** 畫面上的常用預設（按一下自動帶入伺服器與連接埠）。 */
    public const PRESETS = [
        'gmail' => ['host' => 'smtp.gmail.com', 'port' => 587, 'encryption' => 'tls'],
        'outlook' => ['host' => 'smtp.office365.com', 'port' => 587, 'encryption' => 'tls'],
    ];

    protected $fillable = ['host', 'port', 'encryption', 'smtp_account', 'password', 'from_address', 'from_label', 'is_enabled', 'updated_by'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['port' => 'integer', 'is_enabled' => 'boolean', 'password' => 'encrypted'];
    }

    /** 取得唯一那一筆設定（沒有就回傳 null）。 */
    public static function current(): ?self
    {
        return self::query()->orderBy('id')->first();
    }

    /**
     * 啟動時呼叫：若有啟用的寄信設定，就把 mail.* 設定改成它。
     * 資料表還沒建立（剛部署、還沒 migrate）或資料庫連不上時靜默略過，不能讓整個網站因此打不開。
     */
    public static function applyEnabledToConfig(): void
    {
        // 不做快取：解密後的密碼不該存進快取；這張表只有一筆，每個請求查一次很便宜。
        try {
            $setting = self::query()->where('is_enabled', true)->orderBy('id')->first();
        } catch (Throwable) {
            return;
        }

        $setting?->applyToConfig();
    }

    /** 不管有沒有啟用，直接套用這一筆（「寄測試信」用）。 */
    public function applyToConfig(): void
    {
        self::applyArrayToConfig($this->toConfigArray());
    }

    /** 轉成 Laravel mail 設定需要的資料（含解密後的密碼，只在記憶體內使用，不會寫到畫面）。 */
    public function toConfigArray(): array
    {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'encryption' => $this->encryption,
            'smtp_account' => $this->smtp_account,
            'password' => $this->password,
            'from_address' => $this->from_address,
            'from_label' => $this->from_label,
        ];
    }

    private static function applyArrayToConfig(array $setting): void
    {
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.transport', 'smtp');
        Config::set('mail.mailers.smtp.host', $setting['host']);
        Config::set('mail.mailers.smtp.port', $setting['port']);
        // Symfony Mailer：smtps 代表一開始就用 SSL（常見 465）；smtp 會在伺服器支援時自動升級成 TLS（STARTTLS，常見 587）。
        Config::set('mail.mailers.smtp.scheme', $setting['encryption'] === 'ssl' ? 'smtps' : 'smtp');
        Config::set('mail.mailers.smtp.username', $setting['smtp_account']);
        Config::set('mail.mailers.smtp.password', $setting['password']);
        Config::set('mail.from.address', $setting['from_address']);
        if (filled($setting['from_label'])) {
            Config::set('mail.from.name', $setting['from_label']);
        }
    }
}
