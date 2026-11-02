<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 將附件欄位改為統一命名。
     */
    public function up(): void
    {
        $this->renameColumnSafely('disk_path', 'file_path');
        $this->renameColumnSafely('original_name', 'file_name');
    }

    /**
     * 回復附件欄位原本的名稱。
     */
    public function down(): void
    {
        $this->renameColumnSafely('file_path', 'disk_path');
        $this->renameColumnSafely('file_name', 'original_name');
    }

    /**
     * 確認欄位狀態後才執行改名，避免重複改名或遺失欄位。
     */
    private function renameColumnSafely(string $oldName, string $newName): void
    {
        $hasOldColumn = Schema::hasColumn('attachments', $oldName);
        $hasNewColumn = Schema::hasColumn('attachments', $newName);

        if ($hasOldColumn && $hasNewColumn) {
            throw new RuntimeException(
                "attachments 同時存在 {$oldName} 與 {$newName}，停止改名。"
            );
        }

        if (! $hasOldColumn && ! $hasNewColumn) {
            throw new RuntimeException(
                "attachments 找不到 {$oldName} 或 {$newName}，停止改名。"
            );
        }

        if ($hasOldColumn) {
            Schema::table('attachments', function (Blueprint $table) use ($oldName, $newName) {
                $table->renameColumn($oldName, $newName);
            });
        }
    }
};