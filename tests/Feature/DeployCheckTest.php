<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** php artisan deploy:check：正式機設定檢查。 */
class DeployCheckTest extends TestCase
{
    use RefreshDatabase;

    private function run_check(string $environment, array $config): array
    {
        $this->app['env'] = $environment;   // app()->environment() 讀的是這個
        config($config);
        $code = Artisan::call('deploy:check');

        return [$code, Artisan::output()];
    }

    public function test_it_warns_about_debug_mode_http_log_mail_and_a_scheduler_that_never_ran(): void
    {
        Cache::forget('schedule:heartbeat');

        [$code, $out] = $this->run_check('local', ['app.debug' => true, 'app.url' => 'http://192.168.1.10', 'mail.default' => 'log']);

        $this->assertStringContainsString('除錯模式（APP_DEBUG）', $out);
        $this->assertStringContainsString('APP_DEBUG=false', $out);
        $this->assertStringContainsString('MAIL_MAILER 改成 smtp', $out);
        $this->assertStringContainsString('建議用 https', $out);
        $this->assertStringContainsString('從來沒有執行過', $out);
        $this->assertSame(0, $code);   // 開發機只有警告，不算失敗
    }

    public function test_debug_on_in_production_is_an_error(): void
    {
        [$code, $out] = $this->run_check('production', ['app.debug' => true]);

        $this->assertStringContainsString('請先處理錯誤', $out);
        $this->assertSame(1, $code);
    }

    public function test_a_recent_scheduler_heartbeat_passes_the_schedule_check(): void
    {
        Cache::put('schedule:heartbeat', now()->subMinutes(2), now()->addDay());

        [$code, $out] = $this->run_check('production', ['app.debug' => false, 'app.url' => 'https://school.example.com', 'session.secure' => true, 'mail.default' => 'smtp']);

        $this->assertStringContainsString('最近一次 2 分鐘前', $out);
        $this->assertSame(0, $code);
    }

    public function test_a_stale_scheduler_heartbeat_is_a_warning(): void
    {
        Cache::put('schedule:heartbeat', now()->subMinutes(30), now()->addDay());

        [, $out] = $this->run_check('local', []);

        $this->assertStringContainsString('最近一次 30 分鐘前', $out);
        $this->assertStringContainsString('schedule:run', $out);
    }

    public function test_the_scheduler_writes_a_heartbeat_every_minute(): void
    {
        Artisan::call('schedule:list');

        $this->assertStringContainsString('schedule:heartbeat', Artisan::output());
    }
}
