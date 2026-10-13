<?php

namespace App\Providers;

use App\Enums\RepairRequestStatus;           // 報修單狀態列舉
use App\Models\RepairRequest;                // 報修單資料表模型
use App\Models\User;                         // 用戶資料表模型
use App\Services\AI\PredictionServiceInterface;   // AI 預測服務介面（王佑恩，第 4 週）
use App\Services\AI\RuleBasedPredictionService;   // 規則式 AI 預測實作（王佑恩，第 4 週）
use App\Support\PermissionCatalog;           // 權限清單
use Illuminate\Pagination\Paginator;         // 分頁元件的設定
use Illuminate\Support\Facades\Gate;         // 權限判斷（Gate）
use Illuminate\Support\Facades\View;         // 畫面相關設定（這裡用 composer 預先準備資料）
use Illuminate\Support\ServiceProvider;      // 所有「服務提供者」的父類別

/**
 * 【ServiceProvider（服務提供者）是什麼？】網站啟動時 Laravel 會執行的「開機設定」位置，
 * 這裡放的是整個網站共用的設定：分頁樣式、權限規則、頂部通知鈴鐺的資料。
 * 註冊在 bootstrap/providers.php。
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     * （register：註冊服務用，目前只有綁定 AI 預測服務（王佑恩）。）
     */
    public function register(): void
    {
        // 第 4 週：AI 預測服務的正式實作。之後要換成別的演算法，只需改這一行，
        // 其他呼叫端（候選掃描、設備履歷、Artisan 指令）全部吃 PredictionServiceInterface，不用動。
        $this->app->bind(PredictionServiceInterface::class, RuleBasedPredictionService::class);
    }

    /**
     * Bootstrap any application services.
     * （boot：所有服務都註冊完之後執行，適合放全站設定。）
     */
    public function boot(): void
    {
        // 全站已使用 Bootstrap 5，分頁元件也要用 Bootstrap 樣式，
        // 否則 Laravel 預設的 Tailwind 分頁在沒有 Tailwind CSS 時會破版（箭頭變超大）。
        Paginator::useBootstrapFive();

        // 把權限清單裡的每個權限註冊成 Gate，之後全站用同一種寫法判斷權限：
        // 路由 ->middleware('can:users.manage')、畫面 @can('users.manage')、程式 $user->can('...')。
        // 判斷規則：呼叫 User::hasPermission()，也就是「他的身分有沒有勾選這個權限（管理員永遠有）」。
        foreach (PermissionCatalog::all() as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }

        // 頂部通知鈴鐺：每個畫面開始組版前，先算好「這位用戶現在有哪些待辦」交給版面用。
        // View::composer('layouts.app', ...)：只要畫面用到 layouts.app 版面（幾乎所有頁面），
        // 就會先執行這段，把 notificationItems（清單）與 notificationTotal（總數）傳進去。
        View::composer('layouts.app', function ($view) {
            $items = $this->notificationItemsFor(auth()->user());

            // array_column 取出每一項的 count，array_sum 加總 = 鈴鐺上紅色數字。
            $view->with('notificationItems', $items)
                ->with('notificationTotal', array_sum(array_column($items, 'count')));
        });
    }

    /**
     * 通知清單，依用戶的權限決定看到哪幾種（沒有權限處理的事項不會來吵他）：
     * - 有「派工」權限：新報修還沒派工的案件
     * - 有「驗收」權限：等待驗收的案件
     * - 有「處理維修」權限：指派給自己、還沒做完的案件
     * 每一項都帶著點下去要跳轉的網址（直接帶篩選條件到報修看板）。
     *
     * 【想新增一種通知】照下面的格式再加一個 if 區塊即可：判斷權限 → 算數量 → 數量大於 0 才加進 $items；
     * 並到 lang/各語言資料夾/notifications.php 補上文字。
     *
     * @return list<array{icon: string, text: string, count: int, url: string}>
     */
    private function notificationItemsFor(?User $user): array
    {
        // 沒登入（例如登入頁）就沒有任何通知。
        if (! $user) {
            return [];
        }

        $items = [];

        // 有派工權限的人：提醒「還有幾張新報修沒派工」。
        if ($user->can('repairs.dispatch')) {
            $count = RepairRequest::where('status', RepairRequestStatus::Pending->value)->count();
            if ($count > 0) {   // 數量是 0 就不顯示這一項
                $items[] = [
                    'icon' => 'bi-inbox',                                                                  // Bootstrap Icons 圖示名稱
                    'text' => __('notifications.pending_dispatch', ['count' => $count]),                  // 顯示文字（含數字）
                    'count' => $count,                                                                     // 加總鈴鐺數字用
                    'url' => route('repairs.index', ['status' => RepairRequestStatus::Pending->value]),   // 點下去跳到篩好狀態的報修看板
                ];
            }
        }

        // 有驗收權限的人：提醒「有幾張工單等著驗收」。
        // 只算「這位用戶真的能驗收」的案件（報修人本人，或沒有報修人的案件；管理員全部），規則見 RepairRequestPolicy::accept。
        if ($user->can('repairs.accept')) {
            $count = RepairRequest::where('status', RepairRequestStatus::PendingReview->value)
                ->when(! $user->isAdmin(), fn ($q) => $q->where(fn ($w) => $w->where('reporter_id', $user->id)->orWhereNull('reporter_id')))
                ->count();
            if ($count > 0) {
                $items[] = [
                    'icon' => 'bi-clipboard-check',
                    'text' => __('notifications.pending_review', ['count' => $count]),
                    'count' => $count,
                    'url' => route('repairs.index', ['status' => RepairRequestStatus::PendingReview->value]),
                ];
            }
        }

        // 有處理維修權限的人：提醒「指派給我、還沒做完（已派工或處理中）的案件」。
        if ($user->can('repairs.process')) {
            $count = RepairRequest::where('assigned_to', $user->id)
                ->whereIn('status', [RepairRequestStatus::Assigned->value, RepairRequestStatus::InProgress->value])
                ->count();
            if ($count > 0) {
                $items[] = [
                    'icon' => 'bi-wrench',
                    'text' => __('notifications.assigned_to_me', ['count' => $count]),
                    'count' => $count,
                    'url' => route('repairs.index', ['assignee' => $user->name]),   // 用自己的姓名篩選報修看板
                ];
            }
        }

        return $items;
    }
}
