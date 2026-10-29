<?php

// i18n: dashboard. Keep the keys identical to lang/zh_TW/dashboard.php.
return [
    'title' => 'Dashboard',
    'welcome' => 'Welcome, :name',
    'role_line' => 'Role: :role',
    'no_role' => 'No role assigned',
    'click_hint' => 'Click a card or chart to jump straight to the matching feature.',

    // 主控台數字卡片的文字（標題、單位、備註）。
    'cards' => [
        'open_repairs' => 'Open Repairs',
        'pending_dispatch' => 'Awaiting Dispatch',
        'pending_review' => 'Awaiting Acceptance',
        'devices' => 'Total Devices',
        'users' => 'Total Users',
        'classrooms' => 'Total Classrooms',
        'classrooms_abnormal' => ':count classroom(s) with device issues',
        'unit_repairs' => '',
        'unit_devices' => '',
        'unit_users' => '',
        'unit_classrooms' => '',
    ],

    // 主控台圖表的標題與說明。
    'charts' => [
        'repair_status' => 'Repairs by Status',
        'repair_trend' => 'New Repairs, Last 14 Days',
        'device_status' => 'Devices by Status',
        'role_distribution' => 'Users by Role',
        'repair_trend_series' => 'New repairs',
        'no_data' => 'No data yet',
    ],

    // 設備狀態的顯示名稱（主控台圖表用）。
    'device_status' => [
        'normal' => 'Normal',
        'repairing' => 'Under repair',
        'retired' => 'Retired',
        'disabled' => 'Disabled',
    ],
];
