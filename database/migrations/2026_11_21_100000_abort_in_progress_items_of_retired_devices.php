<?php

use App\Models\Device;
use App\Services\RetiredDeviceCleanup;
use Illuminate\Database\Migrations\Migration;

/**
 * 資料整理（一次性）：已經是「已淘汰」的設備，把它名下還在進行中的報修單、保養工單、保養計畫、AI 候選一併中止。
 * 之後設備淘汰的當下由 DeviceStatusService 自動處理，這支只補歷史資料。
 */
return new class extends Migration
{
    public function up(): void
    {
        Device::withTrashed()->where('status', 'retired')->each(function (Device $device): void {
            app(RetiredDeviceCleanup::class)->run($device);
        });
    }

    public function down(): void
    {
        // 中止是定案，無法也不應還原（還原會讓已淘汰設備名下又出現進行中的工單）。
    }
};
