<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('maintenance_results', function (Blueprint $table) {
            $table->id();

            // 一張工單目前只對應一筆保養結果（回報後工單即完成），故建立唯一索引。
            $table->foreignId('maintenance_order_id')
                ->unique()
                ->comment('對應的保養工單')
                ->constrained('maintenance_orders')
                ->cascadeOnDelete();

            $table->string('result', 10)
                ->comment('保養結果：ok 正常 / ng 異常，細部狀態枚舉待全組確認後統一');

            $table->string('executed_by', 100)->nullable()
                ->comment('工程實作欄位：目前尚無正式登入/權限系統，先以文字記錄執行人姓名，待驗證模組完成後改為 user_id 外鍵');

            $table->dateTime('executed_at')
                ->comment('實際執行/回報時間');

            $table->text('notes')->nullable()
                ->comment('備註，NG 時可記錄異常說明');

            $table->string('ng_conversion_status', 20)->nullable()
                ->comment('工程實作欄位：NG 轉報修狀態，待彭仕衡提供正式報修建立介面後串接，目前僅由 NgToRepairService 標記本地狀態');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_results');
    }
};
