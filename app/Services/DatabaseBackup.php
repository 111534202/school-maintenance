<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use ZipArchive;

/**
 * 資料庫備份與還原（系統管理 → 資料備份）。
 *
 * 不需要另外安裝 mysqldump：直接用 Laravel 讀出每一張資料表的全部資料，和上傳的附件檔案一起壓縮成一個檔案
 * （storage/app/backups/backup-日期-時間-原因.zip），MySQL、SQLite 都能用，Windows 也不用設定路徑。
 * zip 裡面：database.json（所有資料表）＋ attachments/（報修照片、維修照片、採購發票等附件檔案）。
 * 以前的備份（.json.gz，只有資料庫）仍然列得出來、也能還原（附件維持現狀）。
 *
 * - 定期備份：每月 1 號凌晨 0 點（routes/console.php 的排程，正式機要設定每分鐘執行 schedule:run）
 * - 手動備份：頁面上的「立即備份」
 * - 保留天數：頁面上可以改（預設 180 天，約半年），超過天數的自動刪除，但最新的一份永遠保留
 * - 還原：先自動備份一次「還原前」的資料，再把資料表內容換成備份裡的內容
 *
 * 附件檔案的位置：storage/app/private/attachments（AttachmentUploader 存的地方）。
 */
class DatabaseBackup
{
    /** 目前這一次備份的原因（寫進備份檔的標頭）。 */
    private string $reason = 'manual';

    /** 不備份、也不還原的資料表：登入連線、快取、佇列這類暫存資料。 */
    private const SKIP_TABLES = ['migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens'];

    public const REASONS = ['scheduled' => '定期備份', 'manual' => '手動備份', 'before-restore' => '還原前自動備份'];

    public const DEFAULT_KEEP_DAYS = 180;

    /** 備份檔名：backup-日期-時間-原因.zip（新）或 .json.gz（舊，只有資料庫）。 */
    private const NAME_PATTERN = '/^backup-(\d{8}-\d{6})-([a-z-]+)\.(zip|json\.gz)$/';

    /** 附件檔案所在的資料夾。 */
    public function attachmentsDirectory(): string
    {
        return storage_path('app/private/attachments');
    }

    public function directory(): string
    {
        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir;
    }

    /** 建立一份備份（資料庫＋附件檔案），回傳檔名。 */
    public function create(string $reason = 'manual'): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP 沒有啟用 zip 擴充功能，無法建立備份（php.ini 打開 extension=zip）。');
        }
        $reason = array_key_exists($reason, self::REASONS) ? $reason : 'manual';
        $this->reason = $reason;
        $name = 'backup-' . now()->format('Ymd-His') . '-' . $reason . '.zip';
        $path = $this->directory() . DIRECTORY_SEPARATOR . $name;
        $tmp = $path . '.part';
        $json = $path . '.json.part';

        try {
            $fp = fopen($json, 'wb');
            if ($fp === false) {
                throw new RuntimeException('無法建立備份檔案，請確認 storage/app/backups 可以寫入。');
            }
            $this->writeTables(fn (string $chunk) => fwrite($fp, $chunk));
            fclose($fp);

            $zip = new ZipArchive;
            if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('無法建立備份檔案，請確認 storage/app/backups 可以寫入。');
            }
            $zip->addFile($json, 'database.json');
            $base = $this->attachmentsDirectory();
            if (is_dir($base)) {
                foreach (File::allFiles($base) as $file) {
                    $relative = str_replace(DIRECTORY_SEPARATOR, '/', $file->getRelativePathname());
                    $zip->addFile($file->getPathname(), 'attachments/' . $relative);
                    $zip->setCompressionName('attachments/' . $relative, ZipArchive::CM_STORE);   // 照片、影片本來就壓縮過，不再壓
                }
            }
            if (! $zip->close()) {
                throw new RuntimeException('備份檔案寫入失敗。');
            }
            rename($tmp, $path);
        } catch (\Throwable $e) {
            @unlink($tmp);   // 寫到一半失敗：不留下半份備份
            throw $e;
        } finally {
            @unlink($json);
        }

        return $name;
    }

    /** 把每一張資料表依序寫出去（$write 每次收到一小段 JSON 文字）。 */
    private function writeTables(callable $write): void
    {
        // 一張表一張表寫，資料多也不會一次佔滿記憶體。
        $write('{"created_at":' . json_encode(now()->toIso8601String()) . ',"reason":' . json_encode($this->reason) . ',"tables":{');
        $first = true;
        foreach ($this->tables() as $table) {
            $write(($first ? '' : ',') . json_encode($table) . ':[');
            $first = false;
            $firstRow = true;
            $order = Schema::hasColumn($table, 'id') ? 'id' : Schema::getColumnListing($table)[0];
            foreach (DB::table($table)->orderBy($order)->lazy(500, $order) as $row) {
                $write(($firstRow ? '' : ',') . json_encode($row, JSON_UNESCAPED_UNICODE));
                $firstRow = false;
            }
            $write(']');
        }
        $write('}}');
    }

    /**
     * 所有備份，新的在前。
     *
     * @return Collection<int, array{name: string, size: int, created_at: Carbon, reason: string, reason_label: string}>
     */
    public function all(): Collection
    {
        return collect(glob($this->directory() . DIRECTORY_SEPARATOR . 'backup-*') ?: [])
            ->filter(fn (string $path) => preg_match(self::NAME_PATTERN, basename($path)) === 1)
            ->map(function (string $path) {
                $name = basename($path);
                preg_match(self::NAME_PATTERN, $name, $m);
                $reason = $m[2] ?? 'manual';

                return [
                    'name' => $name,
                    'with_attachments' => ($m[3] ?? '') === 'zip',
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
        if (! preg_match(self::NAME_PATTERN, $name)) {
            return null;
        }
        $path = $this->directory() . DIRECTORY_SEPARATOR . $name;

        return is_file($path) ? $path : null;
    }

    /**
     * 還原：先備份目前的資料，再把每一張資料表的內容換成備份裡的內容，附件檔案也換成備份裡的。
     * 備份之後才新增的欄位會用預設值；備份裡有、現在已經沒有的欄位或資料表會略過。
     * 舊的 .json.gz 備份沒有附件，只還原資料庫，附件資料夾維持現狀。
     *
     * @return string 還原前自動備份的檔名
     */
    public function restore(string $name): string
    {
        $path = $this->path($name) ?? throw new RuntimeException('找不到這份備份。');
        $zip = null;
        if (str_ends_with($name, '.zip')) {
            $zip = new ZipArchive;
            if ($zip->open($path) !== true) {
                throw new RuntimeException('備份檔案打不開，無法還原。');
            }
            $data = json_decode((string) $zip->getFromName('database.json'), true);
        } else {
            $data = json_decode(gzdecode(file_get_contents($path)), true);
        }
        if (! is_array($data['tables'] ?? null)) {
            $zip?->close();
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

        if ($zip) {
            $this->restoreAttachments($zip);
            $zip->close();
        }

        return $safety;
    }

    /**
     * 把附件資料夾換成備份裡的附件：先解壓到暫存資料夾，成功後再換掉原本的資料夾（失敗時原本的檔案不動）。
     * 只解出 attachments/ 底下的檔案，路徑含「..」的一律略過（防止寫到別的地方）。
     */
    private function restoreAttachments(ZipArchive $zip): void
    {
        $target = $this->attachmentsDirectory();
        $staging = $target . '.restoring';
        File::deleteDirectory($staging);
        File::ensureDirectoryExists($staging);

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = (string) $zip->getNameIndex($i);
            if (! str_starts_with($entry, 'attachments/') || str_ends_with($entry, '/') || str_contains($entry, '..')) {
                continue;
            }
            $dest = $staging . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, substr($entry, strlen('attachments/')));
            File::ensureDirectoryExists(dirname($dest));
            $in = $zip->getStream($entry);
            $out = fopen($dest, 'wb');
            stream_copy_to_stream($in, $out);
            fclose($in);
            fclose($out);
        }

        $old = $target . '.old';
        File::deleteDirectory($old);
        if (is_dir($target)) {
            rename($target, $old);
        }
        rename($staging, $target);
        File::deleteDirectory($old);
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
