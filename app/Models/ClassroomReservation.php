<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

/**
 * 教室預約申請。一筆申請可以有多個時段（slots），每個時段分別審核。
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
        'reservation_date',
        'start_time',
        'end_time',
        'status',
        'purpose',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'reservation_date' => 'date',
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
