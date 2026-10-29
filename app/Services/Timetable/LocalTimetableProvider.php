<?php

namespace App\Services\Timetable;

use App\Models\Classroom;
use Carbon\CarbonInterface;

class LocalTimetableProvider implements TimetableProvider
{
    public function getDaySchedule(
        Classroom $classroom,
        CarbonInterface $date
    ): array {
        $weekday = (int) $date->dayOfWeekIso;
        $periods = config('timetable.periods', []);
        $courses = config('timetable.courses', []);

        return array_map(function (array $period) use (
            $weekday,
            $classroom,
            $courses
        ): array {
            $key = $weekday . '.' . $classroom->classroom_code . '.' . $period['period_no'];
            $course = $courses[$key] ?? null;

            return [
                'period_no' => $period['period_no'],
                'start_time' => $period['start_time'],
                'end_time' => $period['end_time'],
                'class_name' => $course['class_name'] ?? null,
                'course_name' => $course['course_name'] ?? null,
                'is_class' => $course !== null,
            ];
        }, $periods);
    }
}
