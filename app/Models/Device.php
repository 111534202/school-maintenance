<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Device extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'device_code', 'asset_code', 'device_category_id', 'brand',
        'model', 'serial_number', 'warranty_until', 'classroom_id',
        'status', 'is_core',
    ];

    protected $casts = [
        'is_core' => 'boolean',
        'warranty_until' => 'date',
    ];

    // 設備狀態列舉：normal=正常, repairing=維修中, retired=已淘汰, disabled=停用
    public const STATUSES = ['normal', 'repairing', 'retired', 'disabled'];

    /** 狀態的顯示名稱（中文／英文依目前語言），翻譯檔沒有的代碼就原樣顯示。 */
    public static function statusLabel(?string $status): string
    {
        $key = 'devices.status.' . $status;
        $label = __($key);

        return $label === $key ? (string) $status : $label;
    }

    public function category()
    {
        return $this->belongsTo(DeviceCategory::class, 'device_category_id');
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }
}
