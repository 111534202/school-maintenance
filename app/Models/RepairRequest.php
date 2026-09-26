<?php

namespace App\Models;

use App\Enums\RepairRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * users/devices 表尚未合併進這個暫存專案，reporter_id/device_id/assigned_to 先以純欄位
 * 存放 id，不加 Eloquent 關聯或外鍵約束，避免跟之後合併的正式資料表衝突。
 *
 * status 欄位請一律透過 App\Services\RepairRequestWorkflow 修改，不要在 Controller
 * 或其他地方直接寫 ->status = '...'，狀態轉換規則集中在那支 service。
 */
class RepairRequest extends Model
{
    use HasFactory;

    protected $table = 'repair_requests';

    protected $fillable = [
        'title',
        'description',
        'impact_level',
        'affects_class',
        'status',
        'reporter_id',
        'device_id',
        'device_note',
        'assigned_to',
        'assignee_note',
        'scheduled_at',
        'location',
    ];

    protected $casts = [
        'affects_class' => 'boolean',
        'status' => RepairRequestStatus::class,
        'scheduled_at' => 'datetime',
    ];

    public function repairLogs()
    {
        return $this->hasMany(RepairLog::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
