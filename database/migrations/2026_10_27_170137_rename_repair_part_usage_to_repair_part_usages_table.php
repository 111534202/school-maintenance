<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename(
            'repair_part_usage',
            'repair_part_usages'
        );
    }

    public function down(): void
    {
        Schema::rename(
            'repair_part_usages',
            'repair_part_usage'
        );
    }
};