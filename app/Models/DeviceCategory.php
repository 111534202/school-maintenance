<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

/**
 * 設備類別（device_categories 資料表），例如投影機、電腦、冷氣。每個類別底下有很多台設備。
 *
 *
 * @property int $id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class DeviceCategory extends Model
{
    // 允許批次寫入的欄位：目前只有名稱。
    protected $fillable = ['name'];

    /**
     * 屬於這個類別的所有設備。
     *
     * @return HasMany<Device, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }
}
