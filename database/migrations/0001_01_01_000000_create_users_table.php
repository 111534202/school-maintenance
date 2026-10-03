<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 【資料庫遷移 migration 是什麼？】用程式描述資料表的建立與修改，執行 php artisan migrate 時，會依檔名開頭的日期順序一支一支執行。
// up()：套用這次的變更（建表／加欄位）；down()：還原這次的變更（php artisan migrate:rollback 用）。
// 已經跑過、或已推到共用分支的 migration 不要改內容；要調整資料表請「新增一支新的 migration」，這樣大家的資料庫才會一致。
// 這一支是 Laravel 內建的：建立 users（用戶）、password_reset_tokens（重設密碼用）、sessions（登入狀態）三張表。
// 後來新增的用戶欄位（username、身分、部門、電話、啟用狀態、最後登入時間、軟刪除）都在後面的 migration 補上。
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 建立 users 資料表（用戶／帳號）。
        Schema::create('users', function (Blueprint $table) {
            // 主鍵 id：自動遞增的編號，每一筆資料唯一。
            $table->id();
            // 姓名（文字欄位）。
            $table->string('name');
            // Email；unique 代表整張表不可重複。
            $table->string('email')->unique();
            // Email 驗證時間（本系統未使用 Email 驗證流程，保留預設欄位）。
            $table->timestamp('email_verified_at')->nullable();
            // 密碼（存的是加密後的字串，不是明碼）。
            $table->string('password');
            // 「記住我」功能用的 token。
            $table->rememberToken();
            // 自動建立 created_at（建立時間）與 updated_at（更新時間）兩個欄位，Laravel 會自動維護。
            $table->timestamps();
        });

        // 忘記密碼用的暫存 token 表（本系統由管理員重設密碼，這張表目前沒用到）。
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // sessions：存放「誰目前已登入」的資料；管理員停用帳號時，就是刪掉這裡的紀錄把人踢下線。
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            // 這個登入連線屬於哪個用戶（nullable：沒登入的訪客也會有 session，所以可以是空值）；index 讓查詢更快。
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // down()：還原時把三張表刪掉（dropIfExists = 存在才刪，不會報錯）。
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
