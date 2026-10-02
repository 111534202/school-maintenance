<?php

// i18n：主控台。修改這個檔案時，記得同步修改 lang/en/dashboard.php，兩邊的 key 必須完全一致。
return [
    'title' => '主控台',
    'welcome' => '歡迎，:name',
    'role_line' => '身分：:role',
    'no_role' => '尚未指派身分',
    'click_hint' => '點擊數字卡片或圖表，可以直接跳到對應的功能。',

    'cards' => [
        'open_repairs' => '進行中工單',
        'pending_dispatch' => '待派工',
        'pending_review' => '待驗收',
        'devices' => '設備總數',
        'users' => '用戶數量',
        'classrooms' => '教室數量',
        'classrooms_abnormal' => '設備異常教室 :count 間',
        'unit_repairs' => '張',
        'unit_devices' => '台',
        'unit_users' => '人',
        'unit_classrooms' => '間',
    ],

    'charts' => [
        'repair_status' => '工單狀態分布',
        'repair_trend' => '近 14 日新增報修',
        'device_status' => '設備狀態分布',
        'role_distribution' => '用戶身分分布',
        'repair_trend_series' => '新增報修數',
        'no_data' => '目前沒有資料',
    ],

    'device_status' => [
        'normal' => '正常',
        'repairing' => '維修中',
        'retired' => '已淘汰',
        'disabled' => '停用',
    ],
];
