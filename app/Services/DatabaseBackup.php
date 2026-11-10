<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * 資料庫備份與還原（系統管理 → 資料備份）。
 *
 * 不需要另外安裝 mysqldump：直接用 Laravel 讀出每一張資料表的全部資料，壓縮成一個檔案
 * （storage/app/backups/backup-日期-時間-原因.json.gz），MySQL、SQLite 都能用，Windows 也不用設定路徑。
 *
 * - 定期備份：每月 1 號凌晨 0 點（routes/console.php 的排程，正式機要設定每分鐘執行 schedule:run）
 * - 手動備份：頁面上的「立即備份」
 * - 保留天數：頁面上可以改（預設 180 天，約半年），超過天數的自動刪除，但最新的一份永遠保留
 * - 還原：先自動備份一次「還原前」的資料，再把資料表內容換成備份裡的內容
 *
 * 只備份資料庫的內容；上傳的附件檔案（發票、照片）放在 storage 資料夾，不在這個備份裡。
 */
class DatabaseBackup
{
    /** 目前這一次備份的原因（寫進備份檔的標頭）。 */
    private string $reason = 'manual';

    /** 不備份、也不還原的資料表：登入連線、快取、佇列這類暫存資料。 */
    private const SKIP_TABLES = ['migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens'];

    public const REASONS = ['scheduled' => '定期備份', 'manual' => '手動備份', 'before-restore' => '還原前自動備份'];

    public const DEFAULT_KEEP_DAYS = 180;

    public function directory(): string
    {
        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir;
    }

    /** 建立一份備份，回傳檔名。 */
    public function create(string $reason = 'manual'): string
    {
        $reason = array_key_exists($reason, self::REASONS) ? $reason : 'manual';
        $this->reason = $reason;
        $name = 'backup-' . now()->format('Ymd-His') . '-' . $reason . '.json.gz';
        $path = $this->directory() . DIRECTORY_SEPARATOR . $name;
        $tmp = $path . '.part';

        $gz = gzopen($tmp, 'wb6');
        if ($gz === false) {
            throw new RuntimeException('無法建立備份檔案，請確認 storage/app/backups 可以寫入。');
        }

        try {
            $this->writeTables($gz);
        } catch (\Throwable $e) {
            gzclose($gz);
            @unlink($tmp);   // 寫到一半失敗：不留下半份備份

            throw $e;
        }
        gzclose($gz);
        rename($tmp, $path);

        return $name;
    }

    /** 把每一張資料表依序寫進壓縮檔。 */
    private function writeTables($gz): void
    {

        // 一張表一張表寫，資料多也不會一次佔滿記憶體。
        gzwrite($gz, '{"created_at":' . json_encode(now()->toIso8601String()) . ',"reason":' . json_encode($this->reason) . ',"tables":{');
        $first = true;
        foreach ($this->tables() as $table) {
            gzwrite($gz, ($first ? '' : ',') . json_encode($table) . ':[');
            $first = false;
            $firstRow = true;
            $order = Schema::hasColumn($table, 'id') ? 'id' : Schema::getColumnListing($table)[0];
            foreach (DB::table($table)->orderBy($order)->lazy(500, $order) as $row) {
                gzwrite($gz, ($firstRow ? '' : ',') . json_encode($row, JSON_UNESCAPED_UNICODE));
                $firstRow = false;
            }
            gzwrite($gz, ']');
        }
        gzwrite($gz, '}}');
    }

    /**
     * 所有備份，新的在前。
     *
     * @return Collection<int, array{name: string, size: int, created_at: Carbon, reason: string, reason_label: string}>
     */
    public function all(): Collection
    {
        return collect(glob($this->directory() . DIRECTORY_SEPARATOR . 'backup-*.json.gz') ?: [])
            ->map(function (string $path) {
                $name = basename($path);
                preg_match('/^backup-(\d{8}-\d{6})-([a-z-]+)\.json\.gz$/', $name, $m);
                $reason = $m[2] ?? 'manual';

                return [
                    'name' => $name,
                    'size' => filesize($path),
                    'created_at' => isset($m[1]) ? Carbon::createFromFormat('Ymd-His', $m[1]) : Carbon::createFromTimestamp(filemtime($path)),
                    'reason' => $reason,
                    'reason_label' => self::REASONS[$reason] ?? $reason,
                ];
            })
            ->sortByDesc('created_at')->values();
    }

    /** 備份檔的完整路徑；檔名不合法或不存在就回傳 null（防止用 ../ 讀到別的檔案）。 */
    public function path(string $name): ?string
    {
        if (! preg_match('/^backup-\d{8}-\d{6}-[a-z-]+\.json\.gz$/', $name)) {
            return null;
        }
        $path = $this->directory() . DIRECTORY_SEPARATOR . $name;

        return is_file($path) ? $path : null;
    }

    /**
     * 還原：先備份目前的資料，再把每一張資料表的內容換成備份裡的內容。
     * 備份之後才新增的欄位會用預設值；備份裡有、現在已經沒有的欄位或資料表會略過。
     *
     * @return string 還原前自動備份的檔名
     */
    public function restore(string $name): string
    {
        $path = $this->path($name) ?? throw new RuntimeException('找不到這份備份。');
        $data = json_decode(gzdecode(file_get_contents($path)), true);
        if (! is_array($data['tables'] ?? null)) {
            throw new RuntimeException('備份檔案內容不正確，無法還原。');
        }

        $safety = $this->create('before-restore');
        $current = $this->tables();

        Schema::disableForeignKeyConstraints();
        try {
            DB::transaction(function () use ($data, $current) {
                foreach ($current as $table) {
                    DB::table($table)->delete();
                }
                foreach ($data['tables'] as $table => $rows) {
                    if (! in_array($table, $current, true) || $rows === []) {
                        continue;
                    }
                    $columns = array_flip(Schema::getColumnListing($table));
                    foreach (array_chunk($rows, 200) as $chunk) {
                        DB::table($table)->insert(array_map(fn (array $row) => array_intersect_key($row, $columns), $chunk));
                    }
                }
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        return $safety;
    }

    /** 刪除超過保留天數的備份（最新的一份永遠保留），回傳刪掉幾份。 */
    public function prune(?int $keepDays = null): int
    {
        $keepDays ??= $this->keepDays();
        if ($keepDays <= 0) {
            return 0;   // 0＝永遠保留
        }
        $cutoff = now()->subDays($keepDays);
        $deleted = 0;
        foreach ($this->all()->slice(1) as $backup) {
            if ($backup['created_at']->lt($cutoff)) {
                @unlink($this->directory() . DIRECTORY_SEPARATOR . $backup['name']);
                $deleted++;
            }
        }

        return $deleted;
    }

    /** 保留天數（頁面上設定，存在備份資料夾的 settings.json；0 代表永遠不刪）。 */
    public function keepDays(): int
    {
        $file = $this->directory() . DIRECTORY_SEPARATOR . 'settings.json';
        $settings = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return (int) ($settings['keep_days'] ?? self::DEFAULT_KEEP_DAYS);
    }

    public function setKeepDays(int $days): void
    {
        file_put_contents($this->directory() . DIRECTORY_SEPARATOR . 'settings.json', json_encode(['keep_days' => $days]));
    }

    /** @return list<string> 要備份的資料表 */
    private function tables(): array
    {
        // MySQL 要指定目前的資料庫：同一台伺服器上若還有別的資料庫，不指定的話會連別庫的資料表一起列進來。
        $schema = in_array(DB::getDriverName(), ['mysql', 'mariadb'], true) ? DB::getDatabaseName() : null;

        return collect(Schema::getTableListing(schema: $schema, schemaQualified: false))
            ->reject(fn (string $table) => in_array($table, self::SKIP_TABLES, true) || str_starts_with($table, 'sqlite_'))
            ->values()->all();
    }
}
