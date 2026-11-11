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
	'monthly_repairs' => 'New Repairs This Month',
        'pending_dispatch' => 'Awaiting Dispatch',
        'pending_repairs' => 'Pending Repairs',
        'in_progress' => 'In Progress',
        'pending_review' => 'Awaiting Acceptance',
        'pending_reservations' => 'Reservations Awaiting Review',
        'overdue' => 'Overdue Repairs',
        'devices' => 'Total Devices',
        'users' => 'Total Users',
        'classrooms' => 'Total Classrooms',
        'classrooms_abnormal' => ':count classroom(s) with device issues',
        'average_repair_hours' => 'Average Cumulative Repair Hours This Month',
        'repair_hours_missing' => ':count completed repair request(s) have missing hours',
        'unit_hours' => 'hours',
        'unit_repairs' => '',
        'unit_devices' => '',
        'unit_users' => '',
        'unit_classrooms' => '',
        'unit_reservations' => '',
    ],

    // 主控台圖表的標題與說明。
    'charts' => [
        'repair_status' => 'Repairs by Status',
        'repair_trend' => 'New Repairs, Last 14 Days',
        'device_status' => 'Devices by Status',
        'role_distribution' => 'Users by Role',
        'classroom_repair_ranking' => 'Classroom Repair Ranking This Month',
        'device_category_repair_ranking' => 'Device Category Repair Ranking This Month',
        'repair_trend_series' => 'New repairs',
        'no_data' => 'No data yet',
        'open' => 'Charts',
    ],

    // 設備狀態的顯示名稱（主控台圖表用）。
    'device_status' => [
        'normal' => 'Normal',
        'repairing' => 'Under repair',
        'retired' => 'Retired',
        'disabled' => 'Disabled',
    ],
];
