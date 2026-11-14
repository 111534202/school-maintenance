<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * 科系主檔（majors 資料表）。空間申請的「科系 → 年級 → 班級」下拉選單從這裡讀。
 *
 * 每個科系各自設定年級數（grade_count）與每個年級的班數（class_count，最多 4 班，依序叫忠、孝、仁、愛）。
 * 班級名稱 ＝ 簡稱 ＋ 年級（中文數字）＋ 班名，例如簡稱「資」、3 年級、第 1 班 → 「資三忠」。
 */
class Major extends Model
{
    /** 班名：第 1 班叫忠、第 2 班叫孝、第 3 班叫仁、第 4 班叫愛（所以每個年級最多 4 班）。 */
    public const SECTION_NAMES = ['忠', '孝', '仁', '愛'];

    /** 年級的中文數字（最多 9 年級）。 */
    private const GRADE_NUMERALS = ['一', '二', '三', '四', '五', '六', '七', '八', '九'];

    public const MAX_GRADES = 9;

    protected $fillable = ['name', 'major_code', 'abbreviation', 'grade_count', 'class_count', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'grade_count' => 'integer', 'class_count' => 'integer'];
    }

    /** 用這個科系的空間申請。 */
    public function reservations()
    {
        return $this->hasMany(ClassroomReservation::class);
    }

    /** 年級的顯示文字：1 → 一年級。 */
    public static function gradeLabel(int $grade): string
    {
        return (self::GRADE_NUMERALS[$grade - 1] ?? (string) $grade) . '年級';
    }

    /** 班別的顯示文字：1 → 忠班。 */
    public static function sectionLabel(int $section): string
    {
        return (self::SECTION_NAMES[$section - 1] ?? (string) $section) . '班';
    }

    /** 組出班級名稱：這個科系的第 $grade 年級第 $section 班，例如「資三忠」。 */
    public function classLabel(int $grade, int $section): string
    {
        return $this->abbreviation . (self::GRADE_NUMERALS[$grade - 1] ?? (string) $grade) . (self::SECTION_NAMES[$section - 1] ?? (string) $section);
    }

    /** 這個年級、班別在這個科系裡存不存在（例如只有 5 個年級、2 個班的科系，6 年級或第 3 班都不存在）。 */
    public function hasClass(int $grade, int $section): bool
    {
        return $grade >= 1 && $grade <= $this->grade_count && $section >= 1 && $section <= $this->class_count;
    }

    /** 給下拉選單用：只列啟用中的科系；編輯／重新顯示時把目前已選的科系（即使後來停用）也列出來。 */
    public function scopeForSelect(Builder $query, ?int $currentId = null): Builder
    {
        return $query->where(function (Builder $q) use ($currentId) {
            $q->where('is_active', true);
            if ($currentId) {
                $q->orWhere('id', $currentId);
            }
        })->orderBy('name');
    }
}
