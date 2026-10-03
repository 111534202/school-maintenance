<?php

// i18n 共用字串：網站外框（標題、導覽選單、語言切換）與各頁面共用的按鈕文字。
// 修改這個檔案時，記得同步修改 lang/en/common.php，兩邊的 key 必須完全一致。
// 【語言檔怎麼用？】程式與畫面用 __('檔名.區塊.鍵名') 取得文字，例如 __('users.filter.keyword') 就是 lang/各語言資料夾/users.php 裡 filter 區塊的 keyword。
// 【注意】鍵名裡的「點」代表往下一層，所以不要把點放在鍵名裡（會找不到）；要分層請用巢狀陣列。
// 【想新增一段文字】1. 在 lang/zh_TW 的對應檔案加一個鍵；2. 在 lang/en 的同名檔案加同樣的鍵（英文）；3. 畫面或程式裡用 __() 取用。
// 【想新增一種語言】複製 lang/zh_TW 整個資料夾並翻譯，再到 app/Http/Middleware/SetLocale.php 的支援清單加上語言代碼。
return [
    'site_title' => '學校設備維保電子化系統',

    // 側邊選單的連結文字。
    'nav' => [
        'knowledge_base' => '自助知識庫',
        'repair_requests' => '報修',
        'dashboard' => '主控台',
        'system_section' => '系統',
        'master_section' => '主檔',
        'business_section' => '業務功能',
        'users' => '用戶主檔',
        'roles' => '身分主檔',
        'departments' => '部門主檔',
        'classrooms' => '教室主檔',
        'device_categories' => '設備類別',
        'devices' => '設備主檔',
        'audit_logs' => '操作紀錄',
    ],

    // 語言切換選單裡的語言名稱。
    'locale' => [
        'zh_TW' => '中文',
        'en' => 'English',
    ],

    // 各頁面共用的按鈕提示文字（篩選、編輯、刪除、儲存、取消…）。
    'buttons' => [
        'save' => '儲存',
        'cancel' => '取消',
        'edit' => '編輯',
        'delete' => '刪除',
        'back_to_list' => '返回列表',
        'filter' => '篩選',
        'clear_filter' => '清除篩選',
        'view' => '檢視',
    ],
];
