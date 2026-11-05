<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;

/**
 * 教室預約申請。一筆申請可以有多個時段（slots），每個時段分別審核。
 * 使用時間（日期、開始、結束）只記在時段明細（classroom_reservation_slots），申請本身不另存一份
 * （2026-11-05 依 docs/待確認/預約時間重複儲存_劉家芸.md 方案 A 移除）；要「最早的時段」用 firstSlot()。
 * status 是整筆申請的彙總：pending 未審核；approved 全部同意；rejected 全部拒絕；partially_approved 部分同意。
 */
class ClassroomReservation extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_PARTIALLY_APPROVED = 'partially_approved';

    protected $fillable = [
        'classroom_id',
        'requester_id',
        'class_name',
        'status',
        'purpose',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(
            ClassroomReservationSlot::class,
            'classroom_reservation_id'
        )->orderBy('reservation_date')->orderBy('start_time');
    }

    /** 最早的一個時段（依日期、開始時間）；列表或通知要顯示「哪一天」時用這個。 */
    public function firstSlot(): HasOne
    {
        return $this->hasOne(ClassroomReservationSlot::class, 'classroom_reservation_id')
            ->orderBy('reservation_date')->orderBy('start_time');
    }

    public function isReviewed(): bool
    {
        return $this->status !== self::STATUS_PENDING;
    }

    /** 整筆申請的狀態文字與徽章顏色。 */
    public function statusLabel(): array
    {
        return match ($this->status) {
            self::STATUS_PENDING => ['待審核', 'warning'],
            self::STATUS_APPROVED => ['已核准', 'success'],
            self::STATUS_REJECTED => ['已拒絕', 'danger'],
            self::STATUS_PARTIALLY_APPROVED => ['部分核准', 'info'],
            default => [$this->status, 'secondary'],
        };
    }
}
