<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 第 4 週任務 2、3：AI 預防保養候選。
     * 風險分數達門檻時先產生「候選」，由主管核准後才建立保養工單（可由設定改成自動建單）。
     * risk_score / threshold / explanation / features 都是「當下快照」，之後參數或演算法改了，
     * 這筆候選仍可追溯當時為什麼被提出。
     */
    public function up(): void
    {
        Schema::create('preventive_candidates', function (Blueprint $table) {
            $table->id();

            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();

            $table->decimal('risk_score', 5, 4)->comment('提出當下的風險分數 0~1');
            $table->decimal('threshold', 5, 4)->comment('提出當下使用的門檻快照');
            $table->string('algorithm', 50)->comment('使用的演算法版本，例如 rule_based_v1');
            $table->json('explanation')->nullable()->comment('各評分項目的明細（可解釋性）');
            $table->json('features')->nullable()->comment('提出當下的歷史統計特徵快照');

            $table->string('status', 20)->default('pending')
                ->comment('pending 待審 / approved 已核准 / rejected 已駁回 / auto_created 自動建單 / duplicate 核准時發現重複');

            $table->foreignId('maintenance_order_id')->nullable()
                ->comment('核准（或自動建單）後產生的保養工單')
                ->constrained('maintenance_orders')->nullOnDelete();

            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 255)->nullable();

            $table->timestamps();

            $table->index(['device_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preventive_candidates');
    }
};
