<?php

use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 身分主檔：像 Discord 身分組，每個身分有自己勾選的權限清單。
     * - description：身分說明
     * - is_system：系統內建身分（五個預設身分）不能刪除、代碼不能改
     * - permissions：開放的權限代碼（JSON 陣列），清單見 App\Support\PermissionCatalog
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('description')->nullable()->after('slug');
            $table->boolean('is_system')->default(false)->after('description');
            $table->json('permissions')->nullable()->after('is_system');
        });

        // 舊資料補值：五個內建身分標成系統身分，並套用預設權限，不然升級後所有人都會被擋在門外。
        foreach (['admin', 'it_manager', 'technician', 'teacher', 'executive'] as $slug) {
            DB::table('roles')->where('slug', $slug)->update([
                'is_system' => true,
                'permissions' => json_encode(PermissionCatalog::defaultsFor($slug)),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['description', 'is_system', 'permissions']);
        });
    }
};
