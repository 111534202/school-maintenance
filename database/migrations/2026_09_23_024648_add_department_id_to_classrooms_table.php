<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 在 classrooms 表加上 department_id：教室屬於哪個部門（指向 departments 表）。
// （migration 的基本觀念見 0001_01_01_000000_create_users_table.php 檔頭。）
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Week1 阻塞決策：departments 與 classrooms 的歸屬關係，先落定為 classroom 屬於一個 department
        Schema::table('classrooms', function (Blueprint $table) {
            // 外鍵欄位；放在 id 後面；部門被刪時，這個欄位自動變成空值（nullOnDelete）。
            $table->foreignId('department_id')->nullable()->after('id')
                  ->constrained('departments')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            // down()：先移除外鍵約束。
            $table->dropForeign(['department_id']);
            // 再刪除欄位。
            $table->dropColumn('department_id');
        });
    }
};
