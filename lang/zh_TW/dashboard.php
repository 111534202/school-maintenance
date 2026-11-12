<?php

// i18n：主控台。修改這個檔案時，記得同步修改 lang/en/dashboard.php，兩邊的 key 必須完全一致。
return [
    'title' => '主控台',
    'welcome' => '歡迎，:name',
    'role_line' => '身分：:role',
    'no_role' => '尚未指派身分',
    'click_hint' => '點擊數字卡片或圖表，可以直接跳到對應的功能。',

    // 主控台數字卡片的文字（標題、單位、備註）。
    'cards' => [
        'open_repairs' => '進行中工單',
        'monthly_repairs' => '本月新增報修',
        'pending_dispatch' => '待派工',
        'pending_review' => '待驗收',
        'pending_reservations' => '空間預約待審核',
        'pending_repairs' => '待修數',
        'in_progress' => '維修中',
        'overdue' => '逾時工單',
        'devices' => '設備總數',
        'users' => '用戶數量',
        'classrooms' => '教室數量',
        'classrooms_abnormal' => '設備異常教室 :count 間',
        'average_repair_hours' => '本月平均累計維修工時',
        'repair_hours_missing' => '有 :count 張已結案報修單缺少可計算工時',
        'unit_hours' => '小時',
        'unit_repairs' => '張',
        'unit_devices' => '台',
        'unit_users' => '人',
        'unit_classrooms' => '間',
        'unit_reservations' => '筆',
    ],

    // 主控台圖表的標題與說明。
    'charts' => [
        'repair_status' => '工單狀態分布',
        'repair_trend' => '近 14 日新增報修',
        'device_status' => '設備狀態分布',
        'role_distribution' => '用戶身分分布',
        'classroom_repair_ranking' => '本月教室報修排行',
        'device_category_repair_ranking' => '本月設備類別報修排行',
        'repair_trend_series' => '新增報修數',
        'no_data' => '目前沒有資料',
        'open' => '統計圖表',
    ],

    // 設備狀態的顯示名稱（主控台圖表用）。
    'device_status' => [
        'normal' => '正常',
        'repairing' => '維修中',
        'retired' => '已淘汰',
        'disabled' => '停用',
    ],
];
