<?php

namespace Tests\Feature;

use App\Support\AttachmentLimit;
use App\Support\UploadLimit;
use Tests\TestCase;

/**
 * 穩定伺服器（tools/stable-server.py）的附件上傳上限。
 *
 * 曾經的錯誤：用 php-cgi 的命令列 -d 調高上限，但 php-cgi 有 QUERY_STRING 時會把它當命令列參數，-d 全部被忽略，
 * 網站上的上限仍是 php.ini 的 2M。改用額外的設定檔資料夾（PHP_INI_SCAN_DIR）才有效，這裡守住這個做法。
 */
class StableServerUploadLimitTest extends TestCase
{
    public function test_the_extra_ini_allows_the_full_attachment_limit(): void
    {
        $ini = parse_ini_file(base_path('tools/php-ini/stable-server.ini'));

        $this->assertNotFalse($ini);
        $this->assertGreaterThanOrEqual(AttachmentLimit::maxTotalBytes(), UploadLimit::toBytes((string) $ini['upload_max_filesize']), '單一檔案上限要能到 5 MB');
        $this->assertGreaterThanOrEqual(8 * 1024 * 1024, UploadLimit::toBytes((string) $ini['post_max_size']), '整個表單上限至少 8 MB');
    }

    public function test_the_server_passes_the_limits_by_ini_scan_dir_and_not_by_command_line(): void
    {
        $source = (string) file_get_contents(base_path('tools/stable-server.py'));

        $this->assertStringContainsString("env['PHP_INI_SCAN_DIR']", $source);
        $this->assertStringContainsString("'tools', 'php-ini'", $source);
        $this->assertDoesNotMatchRegularExpression("/'-d',\\s*'(upload_max_filesize|post_max_size)/", $source, 'php-cgi 會忽略命令列的 -d，不能再用它設定上傳上限');
    }
}
