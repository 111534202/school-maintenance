<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->renameColumnSafely('code', 'department_code');
    }

    public function down(): void
    {
        $this->renameColumnSafely('department_code', 'code');
    }

    private function renameColumnSafely(string $oldName, string $newName): void
    {
        $hasOldColumn = Schema::hasColumn('departments', $oldName);
        $hasNewColumn = Schema::hasColumn('departments', $newName);

        if ($hasOldColumn && $hasNewColumn) {
            throw new RuntimeException(
                "departments 同時存在 {$oldName} 與 {$newName}，停止改名。"
            );
        }

        if (! $hasOldColumn && ! $hasNewColumn) {
            throw new RuntimeException(
                "departments 找不到 {$oldName} 或 {$newName}，停止改名。"
            );
        }

        if ($hasOldColumn) {
            Schema::table('departments', function (Blueprint $table) use ($oldName, $newName) {
                $table->renameColumn($oldName, $newName);
            });
        }
    }
};