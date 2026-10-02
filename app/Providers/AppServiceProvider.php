<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
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
    }
}
