<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('audit_logs')
            ->where('action', 'login')
            ->update(['action' => 'logged_in']);

        DB::table('audit_logs')
            ->where('action', 'logout')
            ->update(['action' => 'logged_out']);
    }

    public function down(): void
    {
        DB::table('audit_logs')
            ->where('action', 'logged_in')
            ->update(['action' => 'login']);

        DB::table('audit_logs')
            ->where('action', 'logged_out')
            ->update(['action' => 'logout']);
    }
};