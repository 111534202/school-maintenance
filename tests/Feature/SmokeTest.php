<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 冒煙測試（smoke test）：用正式的示範資料（等同 migrate:fresh --seed），
 * 讓每一個身分把「系統裡所有看得到的頁面」各打開一次，確認沒有任何一頁出現伺服器錯誤（HTTP 500），
 * 而且畫面用的是洋蔥式選單、沒有殘留沒被解析的 Blade 語法。
 *
 * 權限不足的頁面會回 403、被導向登入頁會回 302，這些都是正常的，只要不是 500 就算過。
 * 有網址參數的頁面（例如 /repairs/{id}）會自動用資料庫裡第一筆資料代入。
 */
class SmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-07 09:00:00'));
        $this->seed(DatabaseSeeder::class);
    }

    public static function roles(): array
    {
        return [
            'admin' => ['admin'],
            'it_manager' => ['it_manager'],
            'technician' => ['technician'],
            'executive' => ['executive'],
            'teacher' => ['teacher'],
        ];
    }

    /**
     * 列出所有「用 GET 打開的頁面」的完整網址。
     *
     * @return array<int, string>
     */
    private function pages(): array
    {
        $pages = [];

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $uri = ltrim($route->uri(), '/');
            if (preg_match('#^(up$|storage|_|sanctum|livewire)#', $uri)) {
                continue;
            }

            $params = $route->parameterNames();
            if ($params === []) {
                $pages[] = url('/'.$uri);

                continue;
            }

            // 有參數的頁面：只處理有名字的路由，而且每個參數都要能對應到一筆資料。
            $name = $route->getName();
            if (! $name) {
                continue;
            }

            $values = [];
            foreach ($route->signatureParameters(['subClass' => Model::class]) as $parameter) {
                $class = $parameter->getType()->getName();
                $model = $class::query()->orderBy('id')->first();
                // 網址參數名稱是 snake_case（device_category），方法參數是 camelCase（$deviceCategory），要對回網址上的名稱。
                $key = collect($params)->first(fn ($p) => $p === $parameter->getName() || Str::camel($p) === $parameter->getName());
                if ($model && $key) {
                    $values[$key] = $model;
                }
            }

            if (count($values) === count($params)) {
                $pages[] = route($name, $values);
            }
        }

        return array_values(array_unique($pages));
    }

    #[DataProvider('roles')]
    public function test_every_page_opens_without_a_server_error(string $slug): void
    {
        $user = User::whereHas('role', fn ($q) => $q->where('slug', $slug))->orderBy('id')->firstOrFail();
        $this->actingAs($user);

        $problems = [];
        $pages = $this->pages();

        foreach ($pages as $url) {
            $response = $this->get($url);
            $status = $response->getStatusCode();

            if ($status >= 500) {
                $why = $response->baseResponse->exception ?? null;
                $problems[] = "[$slug] $status $url".($why ? '  → '.get_class($why).': '.$why->getMessage() : '');

                continue;
            }

            $html = $response->getContent();
            if ($status !== 200 || ! is_string($html) || ! str_contains($html, 'id="sidebarOffcanvas"')) {
                continue;   // 不是完整的登入後頁面（轉址、下載檔案、權限不足…）就不檢查版面
            }

            // 登入後的完整頁面：手機與桌面各有一份洋蔥式選單，而且沒有殘留的 Blade 語法。
            foreach (['id="drill-desktop"', 'id="drill-mobile"'] as $needle) {
                if (! str_contains($html, $needle)) {
                    $problems[] = "[$slug] $url 缺少選單 $needle";
                }
            }
            foreach (['@foreach', '@endif', '@php', '@include', 'Undefined variable'] as $leftover) {
                if (str_contains($html, $leftover)) {
                    $problems[] = "[$slug] $url 頁面出現殘留文字 $leftover";
                }
            }
        }

        $this->assertGreaterThan(20, count($pages), '應該要掃到二十個以上的頁面');
        $this->assertSame([], $problems, "冒煙測試發現問題（共掃 ".count($pages)." 頁）：\n".implode("\n", $problems));
    }

    public function test_guest_never_gets_a_server_error(): void
    {
        $problems = [];

        foreach ($this->pages() as $url) {
            $status = $this->get($url)->getStatusCode();
            if ($status >= 500) {
                $problems[] = "[guest] $status $url";
            }
        }

        $this->assertSame([], $problems, "未登入也不應該出現 500：\n".implode("\n", $problems));
    }
}
