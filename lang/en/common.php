<?php

// i18n shared strings: site chrome (title, nav menu, language switcher) and buttons
// reused across pages. Keep the keys identical to lang/zh_TW/common.php when editing.
// 【語言檔怎麼用？】程式與畫面用 __('檔名.區塊.鍵名') 取得文字，例如 __('users.filter.keyword') 就是 lang/各語言資料夾/users.php 裡 filter 區塊的 keyword。
// 【注意】鍵名裡的「點」代表往下一層，所以不要把點放在鍵名裡（會找不到）；要分層請用巢狀陣列。
// 【想新增一段文字】1. 在 lang/zh_TW 的對應檔案加一個鍵；2. 在 lang/en 的同名檔案加同樣的鍵（英文）；3. 畫面或程式裡用 __() 取用。
// 【想新增一種語言】複製 lang/zh_TW 整個資料夾並翻譯，再到 app/Http/Middleware/SetLocale.php 的支援清單加上語言代碼。
return [
    'site_title' => 'School Equipment Maintenance System',

    // 側邊選單的連結文字。
    'nav' => [
        'knowledge_base' => 'Self-Help Knowledge Base',
        'repair_requests' => 'Repairs',
        'dashboard' => 'Dashboard',
        'system_section' => 'System',
        'master_section' => 'Master Data',
        'business_section' => 'Operations',
        'users' => 'User Master',
        'roles' => 'Role Master',
        'departments' => 'Department Master',
        'classrooms' => 'Classroom Master',
        'device_categories' => 'Device Categories',
        'devices' => 'Device Master',
        'audit_logs' => 'Audit Log',
    ],

    // 語言切換選單裡的語言名稱。
    'locale' => [
        'zh_TW' => '中文',
        'en' => 'English',
    ],

    // 各頁面共用的按鈕提示文字（篩選、編輯、刪除、儲存、取消…）。
    'buttons' => [
        'save' => 'Save',
        'cancel' => 'Cancel',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'back_to_list' => 'Back to List',
        'filter' => 'Filter',
        'clear_filter' => 'Clear Filter',
        'view' => 'View',
    ],
];
