<?php

namespace App\Providers;

use App\Enums\RepairRequestStatus;
use App\Models\RepairRequest;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 全站已使用 Bootstrap 5，分頁元件也要用 Bootstrap 樣式，
        // 否則 Laravel 預設的 Tailwind 分頁在沒有 Tailwind CSS 時會破版（箭頭變超大）。
        Paginator::useBootstrapFive();

        // 把權限清單裡的每個權限註冊成 Gate，之後全站用同一種寫法判斷權限：
        // 路由 ->middleware('can:users.manage')、畫面 @can('users.manage')、程式 $user->can('...')。
        foreach (PermissionCatalog::all() as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }

        // 頂部通知鈴鐺：每個畫面開始組版前，先算好「這位用戶現在有哪些待辦」交給版面用。
        View::composer('layouts.app', function ($view) {
            $items = $this->notificationItemsFor(auth()->user());

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
     * @return list<array{icon: string, text: string, count: int, url: string}>
     */
    private function notificationItemsFor(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $items = [];

        if ($user->can('repairs.dispatch')) {
            $count = RepairRequest::where('status', RepairRequestStatus::Pending->value)->count();
            if ($count > 0) {
                $items[] = [
                    'icon' => 'bi-inbox',
                    'text' => __('notifications.pending_dispatch', ['count' => $count]),
                    'count' => $count,
                    'url' => route('repairs.index', ['status' => RepairRequestStatus::Pending->value]),
                ];
            }
        }

        if ($user->can('repairs.accept')) {
            $count = RepairRequest::where('status', RepairRequestStatus::PendingReview->value)->count();
            if ($count > 0) {
                $items[] = [
                    'icon' => 'bi-clipboard-check',
                    'text' => __('notifications.pending_review', ['count' => $count]),
                    'count' => $count,
                    'url' => route('repairs.index', ['status' => RepairRequestStatus::PendingReview->value]),
                ];
            }
        }

        if ($user->can('repairs.process')) {
            $count = RepairRequest::where('assigned_to', $user->id)
                ->whereIn('status', [RepairRequestStatus::Assigned->value, RepairRequestStatus::InProgress->value])
                ->count();
            if ($count > 0) {
                $items[] = [
                    'icon' => 'bi-wrench',
                    'text' => __('notifications.assigned_to_me', ['count' => $count]),
                    'count' => $count,
                    'url' => route('repairs.index', ['assignee' => $user->name]),
                ];
            }
        }

        return $items;
    }
}
