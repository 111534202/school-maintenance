<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 建立 repair_requests（報修單）資料表，是整個報修／維修流程的主表。
// 【歷史註解提醒】下面寫的「尚未合併、待補 FK」是建立這張表當時的狀況；
// 後來 devices/users 表已合併，2026_09_30_064236_add_foreign_keys_to_repair_requests_table 已把 reporter_id / device_id / assigned_to 補上外鍵。
// （migration 的基本觀念見 0001_01_01_000000_create_users_table.php 檔頭。）
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 依《第三週進度安排》第 1 項升級為「正式」版本：新增故障描述/影響程度/是否影響上課等
        // 規格已明確欄位。devices/users 表仍不在這個暫存專案裡（尚未有共用 Repository 可合併），
        // 所以 reporter_id / device_id / assigned_to 依然不加外鍵約束，避免建立假的替代表；
        // 設備先用 device_note 文字欄位頂著，等 devices 表真的合併後再換成 device_id 下拉選單。
        Schema::create('repair_requests', function (Blueprint $table) {
            // 主鍵 id（自動遞增）。
            $table->id();
            // 報修標題。
            $table->string('title');
            // 故障描述（長文字）。
            $table->text('description')->comment('故障描述');
            // 待確認：正式列舉值僅為草案，之後跟林政寬/王佑恩對齊全系統的狀態命名慣例
            // 影響程度：只能是 low（輕微）/ medium（中等）/ high（嚴重），預設 medium。
            $table->enum('impact_level', ['low', 'medium', 'high'])->default('medium')->comment('影響程度');
            // 是否影響上課；預設否。
            $table->boolean('affects_class')->default(false)->comment('是否影響上課');
            // 案件狀態（pending/assigned/in_progress/pending_review/completed），預設 pending（新報修）；規則見 RepairRequestWorkflow。
            $table->string('status')->default('pending')->comment('案件狀態：pending/assigned/in_progress/completed/closed（草案）');
            // 待補 FK -> users.id（報修人），users 表由林政寬建立中，尚未合併進本專案
            // 報修人的用戶編號（現已有外鍵，指向 users）。
            $table->unsignedBigInteger('reporter_id')->nullable()->comment('待補 FK -> users.id');
            // 待補 FK -> devices.id，devices 表由林政寬建立中，尚未合併進本專案
            // 報修的設備編號（現已有外鍵，指向 devices）；沒有掃描設備的案件是空值。
            $table->unsignedBigInteger('device_id')->nullable()->comment('待補 FK -> devices.id，devices 表尚未合併');
            // devices 表正式合併前的暫時做法：直接讓使用者用文字描述/指認設備
            // 沒有掃描設備時，用文字描述設備位置的後備欄位。
            $table->string('device_note')->nullable()->comment('devices 表合併前，暫時用文字描述設備；合併後改用 device_id 下拉');
            // 待補 FK -> users.id（維修人員）
            // 被指派的維修人員編號（現已有外鍵，指向 users）。
            $table->unsignedBigInteger('assigned_to')->nullable()->comment('待補 FK -> users.id（維修人員）');
            // 地點（教室），目前用文字紀錄；看板可依它模糊篩選。
            $table->string('location')->nullable()->comment('待確認：是否改關聯 classrooms，目前先用文字紀錄地點');
            // 自動維護的建立時間 created_at 與更新時間 updated_at。
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // down()：還原時刪除這張表。
        Schema::dropIfExists('repair_requests');
    }
};
