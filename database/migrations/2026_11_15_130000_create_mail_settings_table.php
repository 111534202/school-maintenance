<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 寄信設定（mail_settings）：管理員在網頁上填寄信用的信箱帳號，不用改 .env 或重新部署。
 * 整張表只會有一筆資料。密碼以 Laravel 加密後存放（Model 的 encrypted 轉換），畫面與操作紀錄都不會顯示。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_settings', function (Blueprint $table) {
            $table->id();
            $table->string('host', 190)->comment('寄信伺服器位址，例如 smtp.gmail.com');
            $table->unsignedSmallInteger('port')->default(587)->comment('寄信伺服器連接埠，常見 587（TLS）或 465（SSL）');
            $table->string('encryption', 10)->default('tls')->comment('加密方式：tls（STARTTLS）、ssl（SMTPS）、none（不加密）');
            $table->string('smtp_account', 190)->nullable()->comment('登入寄信伺服器的帳號（通常就是信箱）');
            $table->text('password')->nullable()->comment('登入密碼或應用程式密碼；加密後存放，不顯示在畫面上');
            $table->string('from_address', 190)->comment('寄件人信箱（收件者看到的寄件地址）');
            $table->string('from_label', 100)->nullable()->comment('寄件人顯示的名稱，例如「學校設備維保系統」');
            $table->boolean('is_enabled')->default(false)->comment('是否啟用：啟用後系統用這組設定寄信，取代 .env 的寄信設定');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()->comment('最後修改的人');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_settings');
    }
};
