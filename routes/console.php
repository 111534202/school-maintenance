<?php

// 這裡放「自訂的終端機指令」（php artisan xxx）。目前只有 Laravel 預設附的範例指令，專案本身沒有自訂指令。

use Illuminate\Foundation\Inspiring;          // 產生勵志名言的內建工具
use Illuminate\Support\Facades\Artisan;       // 定義終端機指令用

// 範例：執行 `php artisan inspire` 會在終端機印出一句名言。
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
