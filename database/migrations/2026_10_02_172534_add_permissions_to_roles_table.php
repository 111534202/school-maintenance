<?php

use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// （migration 的基本觀念見 0001_01_01_000000_create_users_table.php 檔頭。）
// 這一支除了加欄位，還會「補舊資料」：把五個內建身分標成系統身分並寫入預設權限，否則升級後沒有人有任何權限。
return new class extends Migration
{
    /**
     * 身分主檔：像 Discord 身分組，每個身分有自己勾選的權限清單。
     * - description：身分說明
     * - is_system：系統內建身分（五個預設身分）不能刪除、代碼不能改
     * - permissions：開放的權限代碼（JSON 陣列），清單見 App\Support\PermissionCatalog
     */
    // 套用變更：替 roles 表加欄位，並替五個內建身分補上預設權限。
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            // 身分說明（可空）。
            $table->string('description')->nullable()->after('slug');
            // 是否系統內建身分（內建的不能刪除）。
            $table->boolean('is_system')->default(false)->after('description');
            // 開放的權限代碼，用 JSON 陣列存放。
            $table->json('permissions')->nullable()->after('is_system');
        });

        // 舊資料補值：五個內建身分標成系統身分，並套用預設權限，不然升級後所有人都會被擋在門外。
        // 逐一處理五個內建身分。
        foreach (['admin', 'it_manager', 'technician', 'teacher', 'executive'] as $slug) {
            // 直接用資料庫語法更新（migration 裡不用 Model，避免之後 Model 改了影響舊 migration）。
            DB::table('roles')->where('slug', $slug)->update([
                'is_system' => true,
                // 把預設權限清單轉成 JSON 文字存進去。
                'permissions' => json_encode(PermissionCatalog::defaultsFor($slug)),
            ]);
        }
    }

    // 還原變更：移除這三個欄位（補進去的權限資料也會一起消失）。
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            // down()：還原時刪除這三個欄位。
            $table->dropColumn(['description', 'is_system', 'permissions']);
        });
    }
};
