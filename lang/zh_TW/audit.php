<?php

// i18n：操作紀錄。修改這個檔案時，記得同步修改 lang/en/audit.php，兩邊的 key 必須完全一致。
// actions 的 key 是 AuditLogger::log() 第一個參數；types 的 key 是 Model 的類別名稱（不含 namespace）。
return [
    'index_title' => '操作紀錄',

    // 事件名稱：鍵是 AuditLogger::log() 的第一個參數，值是操作紀錄頁顯示的中文／英文名稱。
    'actions' => [
        'created' => '新增',
        'updated' => '修改',
        'deleted' => '刪除',
        'restored' => '還原',
        'status_changed' => '狀態變更',
        'password_reset' => '重設密碼',
        'core_flag_changed' => '核心設備標記變更',
        'login' => '登入',
        'logout' => '登出',
        'login_failed' => '登入失敗',
        'assigned' => '派工',
        'reassigned' => '重新指派',
        'rejected' => '驗收退回',
    ],

    // 對象類型名稱：鍵是 Model 的類別名稱（不含路徑），值是操作紀錄頁顯示的名稱。
    'types' => [
        'User' => '用戶',
        'Role' => '身分',
        'Department' => '部門',
        'Classroom' => '教室',
        'Device' => '設備',
        'DeviceCategory' => '設備類別',
        'RepairRequest' => '報修單',
        'RepairLog' => '維修紀錄',
        'KnowledgeBase' => '知識庫文章',
    ],

    // 操作紀錄頁篩選列的文字。
    'filter' => [
        'user' => '使用者',
        'user_all' => '所有使用者',
        'action' => '事件',
        'action_all' => '所有事件',
        'type' => '對象類型',
        'type_all' => '所有對象類型',
        'system' => '系統／未登入',
        'date_from' => '起始日期',
        'date_to' => '結束日期',
        'keyword' => '說明關鍵字',
    ],

    // 操作紀錄表格欄位標題。
    'table' => [
        'time' => '時間',
        'user' => '使用者',
        'action' => '事件',
        'description' => '說明',
        'details' => '異動內容',
    ],

    'empty' => '尚無符合條件的紀錄',
    'system_user' => '系統',

    // 登入、重設密碼等沒有對象的事件，說明用這些句子
    // 各功能寫入操作紀錄時使用的說明句子（帶冒號的字會被換成實際內容，例如 :title、:name）。
    'messages' => [
        'login' => ':name 登入系統',
        'logout' => ':name 登出系統',
        'login_failed' => '登入失敗（輸入的帳號：:account）',
        // 輸入的帳號不存在時，操作紀錄裡顯示這句，不記錄輸入的原文（可能是誤打進去的密碼）。
        'unknown_account' => '（不存在的帳號）',
        'repair_assigned' => '派工給「:technician」，報修單「:title」',
        'repair_reassigned' => '重新指派給「:technician」，報修單「:title」',
        'repair_rejected' => '驗收退回報修單「:title」',
        'repair_status' => '報修單「:title」狀態：:from → :to',
        'repair_created' => '新增報修單「:title」',
        'repair_log_created' => '填寫維修紀錄（報修單「:title」，工時 :hours 小時），案件進入待驗收',
    ],
];
