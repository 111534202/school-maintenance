<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 上正式機前（或每次更新後）的檢查：php artisan deploy:check
 *
 * 逐項檢查設定，每一項標示「通過／警告／錯誤」，並告訴你怎麼改：
 * 除錯模式、網址是否 https、寄信設定（忘記密碼要用）、資料庫連線、資料夾能不能寫入、PHP 擴充（備份用 zip）、
 * 上傳大小限制、時區、排程有沒有在跑。有「錯誤」時結束代碼為 1，其餘為 0。
 * 詳細說明見 docs/部署檢查清單.md。
 */
class DeployCheck extends Command
{
    protected $signature = 'deploy:check';

    protected $description = '檢查正式機設定（除錯模式、https、寄信、排程、資料夾權限…）';

    /** 排程每分鐘會寫一個「我還活著」的時間（routes/console.php）；超過這麼多分鐘沒更新就當作排程沒在跑。 */
    private const SCHEDULE_STALE_MINUTES = 5;

    /** @var list<array{0: string, 1: string, 2: string, 3: string}> 狀態、項目、結果、怎麼改 */
    private array $rows = [];

    public function handle(): int
    {
        $production = app()->environment('production');

        $this->add($production ? 'ok' : 'warn', '執行環境（APP_ENV）', (string) config('app.env'), '正式機請把 .env 的 APP_ENV 設成 production。');
        $this->add(config('app.debug') ? ($production ? 'error' : 'warn') : 'ok', '除錯模式（APP_DEBUG）', config('app.debug') ? '開啟' : '關閉',
            '正式機一定要關閉（.env 的 APP_DEBUG=false）：開著時出錯會把程式內部細節、資料庫資訊顯示給使用者。');

        $https = str_starts_with((string) config('app.url'), 'https://');
        $this->add($https ? 'ok' : 'warn', '網站網址（APP_URL）', (string) config('app.url'),
            '建議用 https：鏡頭掃描 QR／條碼只有 https 或 localhost 能即時使用（用 http 時會改成拍照掃描），密碼也不會明碼傳送。');
        $this->add($https && ! config('session.secure') ? 'warn' : 'ok', '登入 Cookie 只走 https（SESSION_SECURE_COOKIE）', config('session.secure') ? '是' : '否',
            '網站用 https 時，.env 加上 SESSION_SECURE_COOKIE=true。');

        // 寄信：.env 的 MAIL_MAILER，或管理員在「寄信設定」頁啟用的 SMTP（啟動時已套用到 config，所以這裡讀到的就是實際使用的方式）。
        $mailer = (string) config('mail.default');
        $this->add(in_array($mailer, ['log', 'array'], true) ? 'warn' : 'ok', '寄信方式（寄信設定頁或 MAIL_MAILER）', $mailer,
            '「忘記密碼」臨時密碼信與派工通知信要真的寄出：用管理員身分到「系統管理 → 寄信設定」填信箱帳號、寄測試信成功後啟用；或把 .env 的 MAIL_MAILER 改成 smtp 並填好 MAIL_HOST 等欄位（目前 log 只會寫進 storage/logs）。');

        try {
            DB::connection()->getPdo();
            $this->add('ok', '資料庫連線', DB::getDatabaseName(), '');
        } catch (\Throwable $e) {
            $this->add('error', '資料庫連線', '失敗：' . $e->getMessage(), '檢查 .env 的 DB_HOST、DB_PORT、DB_DATABASE、DB_USERNAME、DB_PASSWORD，並確認 MySQL 已啟動。');
        }

        foreach (['storage/app', 'storage/logs', 'storage/framework', 'bootstrap/cache'] as $dir) {
            $writable = is_writable(base_path($dir));
            $this->add($writable ? 'ok' : 'error', "資料夾可寫入：{$dir}", $writable ? '可以' : '不行', "讓網站執行的帳號對 {$dir} 有寫入權限（備份檔、附件、日誌、快取都存在這些資料夾）。");
        }

        $this->add(extension_loaded('zip') ? 'ok' : 'error', 'PHP 擴充：zip', extension_loaded('zip') ? '已啟用' : '沒有', '資料備份（含附件）需要；php.ini 打開 extension=zip。');
        $this->add(extension_loaded('mbstring') && extension_loaded('gd') ? 'ok' : 'warn', 'PHP 擴充：mbstring、gd', extension_loaded('gd') ? '已啟用' : 'gd 沒有', '中文處理與 Excel／PDF 匯出需要。');

        $post = \App\Support\UploadLimit::postBytes();
        $this->add($post === 0 || $post >= 8 * 1024 * 1024 ? 'ok' : 'warn', '上傳大小限制（post_max_size）', \App\Support\UploadLimit::human($post),
            '附件一筆最多 5 個、加起來 5 MB；php.ini 的 post_max_size 至少要 8M，upload_max_filesize 也要 5M 以上。');

        $this->add(config('app.timezone') === 'Asia/Taipei' ? 'ok' : 'warn', '時區（APP_TIMEZONE）', (string) config('app.timezone'), '台灣請設成 Asia/Taipei，否則預約時間、保養排定日會差 8 小時。');

        $beat = Cache::get('schedule:heartbeat');
        $age = $beat ? (int) floor(abs(now()->diffInMinutes($beat))) : null;   // 幾分鐘前（取整數）
        $this->add($age !== null && $age <= self::SCHEDULE_STALE_MINUTES ? 'ok' : 'warn', '排程有在跑（保養工單、備份、AI 掃描、申請清除）',
            $beat ? "最近一次 {$age} 分鐘前" : '從來沒有執行過',
            '伺服器要每分鐘執行一次 php artisan schedule:run（Windows 用「工作排程器」、Linux 用 crontab：* * * * * php /專案路徑/artisan schedule:run）。沒設定的話，保養工單不會自動產生、備份不會自動做。');

        $this->table(['狀態', '項目', '目前', '怎麼改'], array_map(fn ($r) => [match ($r[0]) { 'ok' => '通過', 'warn' => '警告', default => '錯誤' }, $r[1], $r[2], $r[0] === 'ok' ? '' : $r[3]], $this->rows));

        $errors = count(array_filter($this->rows, fn ($r) => $r[0] === 'error'));
        $warnings = count(array_filter($this->rows, fn ($r) => $r[0] === 'warn'));
        $this->line($errors ? "有 {$errors} 項錯誤、{$warnings} 項警告，請先處理錯誤。" : ($warnings ? "沒有錯誤，有 {$warnings} 項警告（開發機可以先不管，正式機請處理）。" : '全部通過。'));

        return $errors ? self::FAILURE : self::SUCCESS;
    }

    private function add(string $status, string $item, string $current, string $fix): void
    {
        $this->rows[] = [$status, $item, $current, $fix];
    }
}
