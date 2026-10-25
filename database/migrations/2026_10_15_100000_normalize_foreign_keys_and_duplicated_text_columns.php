<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// （migration 的基本觀念見 0001_01_01_000000_create_users_table.php 檔頭。）
//
// 資料庫統一與正規化（規格書 7.3：第一～第三正規化、設備表只存外鍵避免名稱不一致）。
// 這一支把整合後發現的不一致一次整理好，而且「舊資料會轉換、不會遺失」：
//   1. 保養項目／保養計畫／保養工單的「設備類別」原本是自由文字 → 改成指向 device_categories 的外鍵 device_category_id。
//   2. 保養結果的「執行人」原本是文字姓名 → 改成指向 users 的外鍵 executed_by（舊的姓名對不上帳號時，補寫進備註，不遺失）。
//   3. 報修單的 assignee_note（維修人員姓名文字）和 assigned_to（外鍵）重複 → 舊資料先對應到帳號再刪除文字欄位。
//   4. 「提出者」欄位名稱統一：repair_requests.reporter_id、classroom_reservations.user_id 都改成 requester_id（規格書 repair_requests.requester_id）。
//   5. 補上缺少的外鍵約束與狀態索引：maintenance_orders.device_id、maintenance_plans.device_id、
//      maintenance_results.repair_request_id、knowledge_base.created_by。
//   6. 資料表命名統一成複數：repair_part_usage → repair_part_usages。
//      （knowledge_base 依規格文件固定為單數，所以不改。）
return new class extends Migration
{
    public function up(): void
    {
        $this->normalizeDeviceCategories();
        $this->normalizeMaintenanceResultExecutor();
        $this->dropDuplicatedAssigneeNote();
        $this->unifyRequesterColumns();
        $this->addMissingForeignKeysAndIndexes();

        // 資料表命名：單數改複數，跟其他資料表一致。
        if (Schema::hasTable('repair_part_usage') && ! Schema::hasTable('repair_part_usages')) {
            Schema::rename('repair_part_usage', 'repair_part_usages');
        }
    }

    // 1. 設備類別文字 → 外鍵。
    private function normalizeDeviceCategories(): void
    {
        // 計畫與工單的設備類別是「這份計畫／工單針對哪一類設備」，名稱對不上設備類別主檔時就補一筆新的類別，資料不會遺失。
        $names = collect()
            ->merge(DB::table('maintenance_plans')->whereNotNull('device_category')->pluck('device_category'))
            ->merge(DB::table('maintenance_orders')->whereNotNull('device_category')->pluck('device_category'))
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique();

        foreach ($names as $name) {
            if (! DB::table('device_categories')->where('name', $name)->exists()) {
                DB::table('device_categories')->insert(['name' => $name, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        $categories = DB::table('device_categories')->get(['id', 'name']);

        foreach (['maintenance_plans' => 'device_category', 'maintenance_orders' => 'device_category', 'maintenance_items' => 'category'] as $table => $oldColumn) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('device_category_id')->nullable()->constrained('device_categories')->nullOnDelete();
            });

            foreach ($categories as $category) {
                DB::table($table)->where($oldColumn, $category->name)->update(['device_category_id' => $category->id]);
            }
        }

        // 保養項目的舊「分類」是空調、電力設備這類籠統文字，不是設備類別；對不上設備類別主檔的，
        // 不去污染設備類別主檔，改把原分類寫進說明保留下來。
        foreach (DB::table('maintenance_items')->whereNull('device_category_id')->whereNotNull('category')->where('category', '!=', '')->get(['id', 'category', 'description']) as $item) {
            DB::table('maintenance_items')->where('id', $item->id)->update([
                'description' => trim("（原分類：{$item->category}）\n".($item->description ?? '')),
            ]);
        }

        Schema::table('maintenance_items', fn (Blueprint $table) => $table->dropColumn('category'));
        Schema::table('maintenance_plans', fn (Blueprint $table) => $table->dropColumn('device_category'));
        Schema::table('maintenance_orders', fn (Blueprint $table) => $table->dropColumn('device_category'));
    }

    // 2. 執行人姓名 → 使用者外鍵。
    private function normalizeMaintenanceResultExecutor(): void
    {
        Schema::table('maintenance_results', fn (Blueprint $table) => $table->renameColumn('executed_by', 'executed_by_name'));
        Schema::table('maintenance_results', function (Blueprint $table) {
            $table->foreignId('executed_by')->nullable()->after('result')->constrained('users')->nullOnDelete();
        });

        foreach (DB::table('maintenance_results')->whereNotNull('executed_by_name')->get(['id', 'executed_by_name', 'notes']) as $row) {
            $userId = DB::table('users')->where('name', $row->executed_by_name)->orderBy('id')->value('id');

            if ($userId) {
                DB::table('maintenance_results')->where('id', $row->id)->update(['executed_by' => $userId]);
            } elseif (trim((string) $row->executed_by_name) !== '') {
                // 對不上任何帳號：把姓名補寫進備註，不遺失。
                DB::table('maintenance_results')->where('id', $row->id)->update([
                    'notes' => trim(($row->notes ?? '')."\n（執行人：{$row->executed_by_name}）"),
                ]);
            }
        }

        Schema::table('maintenance_results', fn (Blueprint $table) => $table->dropColumn('executed_by_name'));
    }

    // 3. assignee_note（文字）和 assigned_to（外鍵）重複，留外鍵就好。
    private function dropDuplicatedAssigneeNote(): void
    {
        foreach (DB::table('repair_requests')->whereNull('assigned_to')->whereNotNull('assignee_note')->get(['id', 'assignee_note', 'description']) as $row) {
            $userId = DB::table('users')->where('name', $row->assignee_note)->orderBy('id')->value('id');

            if ($userId) {
                DB::table('repair_requests')->where('id', $row->id)->update(['assigned_to' => $userId]);
            } elseif (trim((string) $row->assignee_note) !== '') {
                DB::table('repair_requests')->where('id', $row->id)->update([
                    'description' => trim($row->description."\n（原指派人員：{$row->assignee_note}）"),
                ]);
            }
        }

        Schema::table('repair_requests', fn (Blueprint $table) => $table->dropColumn('assignee_note'));
    }

    // 4. 「提出者」欄位統一叫 requester_id。
    private function unifyRequesterColumns(): void
    {
        Schema::table('repair_requests', fn (Blueprint $table) => $table->renameColumn('reporter_id', 'requester_id'));
        Schema::table('classroom_reservations', fn (Blueprint $table) => $table->renameColumn('user_id', 'requester_id'));
    }

    // 5. 補外鍵約束與狀態索引（先把指向不存在資料的舊值清成空值，才不會因為舊的壞資料加不上外鍵）。
    private function addMissingForeignKeysAndIndexes(): void
    {
        foreach (['maintenance_orders', 'maintenance_plans'] as $table) {
            DB::table($table)->whereNotNull('device_id')->whereNotIn('device_id', DB::table('devices')->select('id'))->update(['device_id' => null]);
            Schema::table($table, fn (Blueprint $t) => $t->foreign('device_id')->references('id')->on('devices')->nullOnDelete());
        }

        DB::table('maintenance_results')->whereNotNull('repair_request_id')->whereNotIn('repair_request_id', DB::table('repair_requests')->select('id'))->update(['repair_request_id' => null]);
        Schema::table('maintenance_results', fn (Blueprint $t) => $t->foreign('repair_request_id')->references('id')->on('repair_requests')->nullOnDelete());

        DB::table('knowledge_base')->whereNotNull('created_by')->whereNotIn('created_by', DB::table('users')->select('id'))->update(['created_by' => null]);
        Schema::table('knowledge_base', fn (Blueprint $t) => $t->foreign('created_by')->references('id')->on('users')->nullOnDelete());

        // 狀態欄位常拿來篩選（報修看板、設備主檔、保養工單），加索引。
        Schema::table('repair_requests', fn (Blueprint $t) => $t->index('status'));
        Schema::table('devices', fn (Blueprint $t) => $t->index('status'));
        Schema::table('maintenance_orders', fn (Blueprint $t) => $t->index('status'));
    }

    public function down(): void
    {
        if (Schema::hasTable('repair_part_usages') && ! Schema::hasTable('repair_part_usage')) {
            Schema::rename('repair_part_usages', 'repair_part_usage');
        }

        Schema::table('maintenance_orders', fn (Blueprint $t) => $t->dropIndex(['status']));
        Schema::table('devices', fn (Blueprint $t) => $t->dropIndex(['status']));
        Schema::table('repair_requests', fn (Blueprint $t) => $t->dropIndex(['status']));

        Schema::table('knowledge_base', fn (Blueprint $t) => $t->dropForeign(['created_by']));
        Schema::table('maintenance_results', fn (Blueprint $t) => $t->dropForeign(['repair_request_id']));
        Schema::table('maintenance_plans', fn (Blueprint $t) => $t->dropForeign(['device_id']));
        Schema::table('maintenance_orders', fn (Blueprint $t) => $t->dropForeign(['device_id']));

        Schema::table('classroom_reservations', fn (Blueprint $table) => $table->renameColumn('requester_id', 'user_id'));
        Schema::table('repair_requests', fn (Blueprint $table) => $table->renameColumn('requester_id', 'reporter_id'));

        // 還原文字欄位（內容無法還原成原本的文字，只還原欄位結構；執行人與指派人姓名改從帳號補回）。
        Schema::table('repair_requests', fn (Blueprint $table) => $table->string('assignee_note')->nullable());
        foreach (DB::table('repair_requests')->whereNotNull('assigned_to')->get(['id', 'assigned_to']) as $row) {
            DB::table('repair_requests')->where('id', $row->id)->update(['assignee_note' => DB::table('users')->where('id', $row->assigned_to)->value('name')]);
        }

        Schema::table('maintenance_results', fn (Blueprint $t) => $t->dropForeign(['executed_by']));
        Schema::table('maintenance_results', fn (Blueprint $t) => $t->renameColumn('executed_by', 'executed_by_id'));
        Schema::table('maintenance_results', fn (Blueprint $t) => $t->string('executed_by', 100)->nullable());
        foreach (DB::table('maintenance_results')->whereNotNull('executed_by_id')->get(['id', 'executed_by_id']) as $row) {
            DB::table('maintenance_results')->where('id', $row->id)->update(['executed_by' => DB::table('users')->where('id', $row->executed_by_id)->value('name')]);
        }
        Schema::table('maintenance_results', fn (Blueprint $t) => $t->dropColumn('executed_by_id'));

        Schema::table('maintenance_items', fn (Blueprint $t) => $t->string('category', 100)->nullable());
        Schema::table('maintenance_plans', fn (Blueprint $t) => $t->string('device_category', 100)->nullable());
        Schema::table('maintenance_orders', fn (Blueprint $t) => $t->string('device_category', 100)->nullable());
        foreach (['maintenance_items' => 'category', 'maintenance_plans' => 'device_category', 'maintenance_orders' => 'device_category'] as $table => $column) {
            foreach (DB::table('device_categories')->get(['id', 'name']) as $category) {
                DB::table($table)->where('device_category_id', $category->id)->update([$column => $category->name]);
            }
            Schema::table($table, function (Blueprint $t) {
                $t->dropForeign(['device_category_id']);
                $t->dropColumn('device_category_id');
            });
        }
    }
};
