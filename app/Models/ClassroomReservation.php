<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;

/**
 * 教室預約申請。一筆申請可以有多個時段（slots），可以逐節審核；申請人勾選 is_all_or_nothing 時只能整筆同意或拒絕（見 ReservationReviewService）。
 * 使用時間（日期、開始、結束）只記在時段明細（classroom_reservation_slots），申請本身不另存一份
 * （2026-11-05 依 docs/待確認/預約時間重複儲存_劉家芸.md 方案 A 移除）；要「最早的時段」用 firstSlot()。
 * status 是整筆申請的彙總：pending 未審核；approved 全部同意；rejected 全部拒絕；partially_approved 部分同意（只會出現在以前可以分時段審核時的舊資料）。
 *
 *
 * @property int $id
 * @property int $classroom_id
 * @property int $requester_id
 * @property string $class_name
 * @property int|null $major_id
 * @property int|null $class_grade
 * @property int|null $class_section
 * @property string $status
 * @property int|null $reviewed_by
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 * @property string|null $rejection_reason
 * @property string|null $purpose
 * @property bool $is_all_or_nothing
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ClassroomReservation extends Model
{
    /**
     * 「等著審核」的申請：還沒審核，而且至少有一節還沒開始（時段全部已過的仍可補審核，但不再提醒）。
     * 通知鈴鐺的數字與空間申請列表的 ?upcoming=1 都用它，兩邊的數字才會一致。
     *
     * @param  \Illuminate\Database\Eloquent\Builder<ClassroomReservation>  $query
     * @return \Illuminate\Database\Eloquent\Builder<ClassroomReservation>
     */
    public function scopeAwaitingReview($query)
    {
        $today = now()->toDateString();
        $time = now()->format('H:i:s');

        return $query->where('status', self::STATUS_PENDING)
            ->whereHas('slots', fn ($q) => $q->whereNull('status')->where(fn ($q) => $q->whereDate('reservation_date', '>', $today)
                ->orWhere(fn ($q) => $q->whereDate('reservation_date', $today)->where('start_time', '>', $time))));
    }

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_PARTIALLY_APPROVED = 'partially_approved';

    protected $fillable = [
        'classroom_id',
        'requester_id',
        'class_name',
        'major_id',        // 下拉選的科系（舊資料與沒有科系主檔時是空白，class_name 仍有文字）
        'class_grade',     // 年級（1～）
        'class_section',   // 班別（1＝忠、2＝孝、3＝仁、4＝愛）
        'status',
        'purpose',
        'is_all_or_nothing',   // 申請人勾選「其中一節無法借用就整筆不要」：只能整筆同意或整筆拒絕
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'is_all_or_nothing' => 'boolean',
        ];
    }

    /** @return BelongsTo<Major, $this> */
    public function major(): BelongsTo
    {
        return $this->belongsTo(Major::class);
    }

    /** @return BelongsTo<Classroom, $this> */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return HasMany<ClassroomReservationSlot, $this> */
    public function slots(): HasMany
    {
        return $this->hasMany(
            ClassroomReservationSlot::class,
            'classroom_reservation_id'
        )->orderBy('reservation_date')->orderBy('start_time');
    }

    /**
     * 最早的一個時段（依日期、開始時間）；列表或通知要顯示「哪一天」時用這個。
     *
     * @return HasOne<ClassroomReservationSlot, $this>
     */
    public function firstSlot(): HasOne
    {
        return $this->hasOne(ClassroomReservationSlot::class, 'classroom_reservation_id')
            ->orderBy('reservation_date')->orderBy('start_time');
    }

    public function isReviewed(): bool
    {
        return $this->status !== self::STATUS_PENDING;
    }

    /**
     * 列表上的「申請時段」簡短寫法（需先載入 slots）：
     * - 只有一節：「11/13 第 6 節」
     * - 同一天多節：「11/13」＋節數
     * - 跨日：連續 3 天以上寫成「11/5~7」，其他用逗號分開「11/5,6」「11/5,9」；跨月時把月份補上（「10/30~11/2」）。
     * 回傳 [日期與節次文字, 節數]；節數大於 1 時畫面另外顯示「（共 N 節）」。
     *
     * @return array{0: string, 1: int}
     */
    public function slotSummary(): array
    {
        $slots = $this->slots;
        $count = $slots->count();
        if ($count === 0) {
            return ['—', 0];
        }
        if ($count === 1) {
            $slot = $slots->first();

            return [$slot->reservation_date->format('n/j') . ' ' . $slot->periodOrTime(), 1];
        }

        $dates = $slots->map(fn ($slot) => $slot->reservation_date->copy()->startOfDay())
            ->unique(fn ($date) => $date->toDateString())->sortBy(fn ($date) => $date->timestamp)->values();

        // 依「連續的日子」分段：[[11/5, 11/6, 11/7], [11/9]]。
        $runs = [];
        foreach ($dates as $date) {
            $last = $runs ? end($runs) : null;
            if ($last && end($last)->copy()->addDay()->isSameDay($date)) {
                $runs[array_key_last($runs)][] = $date;
            } else {
                $runs[] = [$date];
            }
        }

        // 同一個月接著寫時只寫「日」，換月才寫「月/日」。
        $month = null;
        $label = function ($date) use (&$month) {
            $text = $month === $date->format('Y-m') ? $date->format('j') : $date->format('n/j');
            $month = $date->format('Y-m');

            return $text;
        };
        $parts = [];
        foreach ($runs as $run) {
            if (count($run) >= 3) {
                $parts[] = $label($run[0]) . '~' . $label(end($run));
            } else {
                foreach ($run as $date) {
                    $parts[] = $label($date);
                }
            }
        }

        return [implode(',', $parts), $count];
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
