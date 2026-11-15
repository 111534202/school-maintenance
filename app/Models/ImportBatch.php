<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * 批次匯入紀錄（import_batches 資料表）：每確認匯入一次就一筆，記下誰、何時、哪個檔、成功與略過幾筆。
 * 設備與用戶的 import_batch_id 指向這裡，所以匯錯時可以依批次整批復原。
 *
 * @property int $id
 * @property string $import_type
 * @property string $name
 * @property int $imported_count
 * @property int $skipped_count
 * @property int|null $created_by
 * @property Carbon|null $undone_at
 * @property int|null $undone_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ImportBatch extends Model
{
    protected $fillable = ['import_type', 'name', 'imported_count', 'skipped_count', 'created_by', 'undone_at', 'undone_by'];

    protected function casts(): array
    {
        return ['undone_at' => 'datetime'];
    }

    /** 這一批是否已經復原過。 */
    public function isUndone(): bool
    {
        return $this->undone_at !== null;
    }

    /** @return BelongsTo<User, $this> 執行匯入的人 */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
