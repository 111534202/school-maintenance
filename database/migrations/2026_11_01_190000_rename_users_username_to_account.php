<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->renameColumnSafely('username', 'account');
    }

    public function down(): void
    {
        $this->renameColumnSafely('account', 'username');
    }

    private function renameColumnSafely(string $oldName, string $newName): void
    {
        $hasOldColumn = Schema::hasColumn('users', $oldName);
        $hasNewColumn = Schema::hasColumn('users', $newName);

        if ($hasOldColumn && $hasNewColumn) {
            throw new RuntimeException(
                "users 同時存在 {$oldName} 與 {$newName}，停止改名。"
            );
        }

        if (! $hasOldColumn && ! $hasNewColumn) {
            throw new RuntimeException(
                "users 找不到 {$oldName} 或 {$newName}，停止改名。"
            );
        }

        if ($hasOldColumn) {
            Schema::table('users', function (Blueprint $table) use ($oldName, $newName) {
                $table->renameColumn($oldName, $newName);
            });
        }
    }
};