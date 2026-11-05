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
    | 節次時間（含早自習與午休）；實際上課時間有變動時，只需修改此處。
    | period_number：第幾節（早自習為 0；午休不是上課節次，為 null）；label：課表上顯示的名稱。
    | 第 1～4 節與第 5 節以後每節 50 分鐘、下課 10 分鐘，列到第 13 節。
    */
    'periods' => [
        ['period_number' => 0, 'label' => '早自習', 'start_time' => '08:00', 'end_time' => '08:20'],
        ['period_number' => 1, 'label' => '第1節', 'start_time' => '08:30', 'end_time' => '09:20'],
        ['period_number' => 2, 'label' => '第2節', 'start_time' => '09:30', 'end_time' => '10:20'],
        ['period_number' => 3, 'label' => '第3節', 'start_time' => '10:30', 'end_time' => '11:20'],
        ['period_number' => 4, 'label' => '第4節', 'start_time' => '11:30', 'end_time' => '12:20'],
        ['period_number' => null, 'label' => '午休', 'start_time' => '12:20', 'end_time' => '13:20'],
        ['period_number' => 5, 'label' => '第5節', 'start_time' => '13:20', 'end_time' => '14:10'],
        ['period_number' => 6, 'label' => '第6節', 'start_time' => '14:20', 'end_time' => '15:10'],
        ['period_number' => 7, 'label' => '第7節', 'start_time' => '15:20', 'end_time' => '16:10'],
        ['period_number' => 8, 'label' => '第8節', 'start_time' => '16:20', 'end_time' => '17:10'],
        ['period_number' => 9, 'label' => '第9節', 'start_time' => '17:20', 'end_time' => '18:10'],
        ['period_number' => 10, 'label' => '第10節', 'start_time' => '18:20', 'end_time' => '19:10'],
        ['period_number' => 11, 'label' => '第11節', 'start_time' => '19:20', 'end_time' => '20:10'],
        ['period_number' => 12, 'label' => '第12節', 'start_time' => '20:20', 'end_time' => '21:10'],
        ['period_number' => 13, 'label' => '第13節', 'start_time' => '21:20', 'end_time' => '22:10'],
    ],

    /*
    | 本機示範課程，鍵值格式：星期.教室代碼.節次
    | 星期：1=星期一，...，7=星期日。
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
