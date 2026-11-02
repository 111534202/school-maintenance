<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 課表來源
    |--------------------------------------------------------------------------
    | local：本機測試課表。未來可新增 school_api Provider。
    */
    'provider' => env('TIMETABLE_PROVIDER', 'local'),

    /*
    | 測試節次時間；實際上課時間確認後，只需修改此處。
    */
    'periods' => [
        ['period_no' => 1, 'start_time' => '08:10', 'end_time' => '09:00'],
        ['period_no' => 2, 'start_time' => '09:10', 'end_time' => '10:00'],
        ['period_no' => 3, 'start_time' => '10:10', 'end_time' => '11:00'],
        ['period_no' => 4, 'start_time' => '11:10', 'end_time' => '12:00'],
        ['period_no' => 5, 'start_time' => '13:10', 'end_time' => '14:00'],
        ['period_no' => 6, 'start_time' => '14:10', 'end_time' => '15:00'],
        ['period_no' => 7, 'start_time' => '15:10', 'end_time' => '16:00'],
        ['period_no' => 8, 'start_time' => '16:10', 'end_time' => '17:00'],
    ],

    /*
    | 本機示範課程，鍵值格式：星期.教室代碼.節次
    | 星期：1=星期一，...，5=星期五。
    */
    'courses' => [
        '1.IT-301.1' => ['class_name' => '資一忠', 'course_name' => '資訊科技'],
        '1.IT-301.2' => ['class_name' => '資一忠', 'course_name' => '程式設計'],
        '1.IT-301.4' => ['class_name' => '資二忠', 'course_name' => '資料處理'],
        '2.IT-301.1' => ['class_name' => '資二忠', 'course_name' => '程式設計'],
        '2.IT-301.3' => ['class_name' => '資一忠', 'course_name' => '電腦實習'],
        '3.IT-301.2' => ['class_name' => '資三忠', 'course_name' => '專題製作'],
        '4.IT-301.1' => ['class_name' => '資一忠', 'course_name' => '資訊科技'],
        '5.IT-301.3' => ['class_name' => '資二忠', 'course_name' => '電腦實習'],

        '1.IT-401.1' => ['class_name' => '資二忠', 'course_name' => '網路概論'],
        '2.IT-401.2' => ['class_name' => '資三忠', 'course_name' => '專題製作'],
        '3.IT-401.4' => ['class_name' => '資一忠', 'course_name' => '程式設計'],
        '4.IT-401.2' => ['class_name' => '資二忠', 'course_name' => '網路概論'],
        '5.IT-401.1' => ['class_name' => '資三忠', 'course_name' => '專題製作'],
    ],
];
