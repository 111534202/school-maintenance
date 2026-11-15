<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 批次匯入紀錄（import_batches）：每按一次「確認匯入」就留一筆，記下「誰、什麼時候、匯入了什麼檔、成功幾筆」。
 * 設備與用戶各加一個 import_batch_id，標記「這筆資料是哪一次匯入來的」，匯錯時就能依這個欄位整批復原。
 * 復原是軟刪除（設備＝停用、用戶＝刪除），資料不會真的消失；已經有使用紀錄的資料會被保留，不會被復原。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('import_type', 20)->comment('匯入種類：devices（設備）、users（用戶）');
            $table->string('name', 190)->comment('匯入的檔案名稱（只是記錄，檔案本身確認後就刪除）');
            $table->unsignedInteger('imported_count')->default(0)->comment('成功寫入幾筆');
            $table->unsignedInteger('skipped_count')->default(0)->comment('因為有錯誤而略過幾列');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->comment('執行匯入的人');
            $table->timestamp('undone_at')->nullable()->comment('整批復原的時間；空白代表尚未復原');
            $table->foreignId('undone_by')->nullable()->constrained('users')->nullOnDelete()->comment('執行復原的人');
            $table->timestamps();
        });

        foreach (['devices', 'users'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('import_batch_id')->nullable()->constrained('import_batches')->nullOnDelete()
                    ->comment('由哪一次批次匯入建立；手動新增的資料是空白');
            });
        }
    }

    public function down(): void
    {
        foreach (['devices', 'users'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('import_batch_id');
            });
        }
        Schema::dropIfExists('import_batches');
    }
};
