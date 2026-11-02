<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 零件主檔補上：
 * - part_code：零件編號（PART-1、PART-2…，依新增順序，唯一）。同一種零件的不同規格是不同筆零件，所以各自有編號。
 * - brand／model：品牌、型號（和設備主檔同名），可用來搜尋。
 * 既有零件依建立順序（id）補上編號；之後新增的零件由 Part 模型自動產生下一個編號。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parts', function (Blueprint $table) {
            $table->string('part_code', 20)->nullable()->unique()->after('id');
            $table->string('brand')->nullable()->after('specification');
            $table->string('model')->nullable()->after('brand');
        });

        $number = 0;
        foreach (DB::table('parts')->orderBy('id')->pluck('id') as $id) {
            $number++;
            DB::table('parts')->where('id', $id)->update(['part_code' => 'PART-' . $number]);
        }
    }

    public function down(): void
    {
        Schema::table('parts', function (Blueprint $table) {
            $table->dropUnique(['part_code']);
            $table->dropColumn(['part_code', 'brand', 'model']);
        });
    }
};
