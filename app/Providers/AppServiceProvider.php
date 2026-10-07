<?php

namespace App\Providers;

use App\Services\AI\PredictionServiceInterface;
use App\Services\AI\RuleBasedPredictionService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 第 4 週：AI 預測服務的正式實作。之後要換成別的演算法，只需改這一行，
        // 其他呼叫端（候選掃描、設備履歷、Artisan 指令）全部吃 PredictionServiceInterface，不用動。
        $this->app->bind(PredictionServiceInterface::class, RuleBasedPredictionService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
