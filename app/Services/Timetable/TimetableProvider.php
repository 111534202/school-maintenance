<?php

namespace App\Services\Timetable;

use App\Models\Classroom;
use Carbon\CarbonInterface;

interface TimetableProvider
{
    /**
     * 回傳指定教室、日期的節次課表。
     *
     * 每筆至少包含 period_no、label（例如「早自習」「第1節」「午休」）、start_time、end_time、class_name、course_name。
     */
    public function getDaySchedule(
        Classroom $classroom,
        CarbonInterface $date
    ): array;
}
