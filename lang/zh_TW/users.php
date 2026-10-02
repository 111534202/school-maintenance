<?php

// i18n：用戶主檔（帳號管理）。修改這個檔案時，記得同步修改 lang/en/users.php，兩邊的 key 必須完全一致。
return [
    'index_title' => '用戶主檔',
    'add_user' => '新增用戶',
    'empty_list' => '沒有符合條件的用戶。',
    'never_logged_in' => '從未登入',
    'current_user_badge' => '目前登入',
    'no_department' => '未指定',

    'filter' => [
        'keyword' => '關鍵字',
        'keyword_placeholder' => '帳號／姓名／Email／電話',
        'role' => '身分權限',
        'role_all' => '全部',
        'department' => '部門',
        'department_all' => '全部',
        'status' => '狀態',
        'status_all' => '全部',
    ],

    'status' => [
        'active' => '啟用中',
        'inactive' => '已停用',
        'deleted' => '已刪除',
    ],

    'table' => [
        'username' => '帳號',
        'name' => '姓名',
        'email' => '電子郵件',
        'phone' => '電話',
        'role' => '身分權限',
        'department' => '部門',
        'status' => '狀態',
        'last_login_at' => '最後登入',
        'actions' => '操作',
    ],

    'actions' => [
        'activate' => '啟用帳號',
        'deactivate' => '停用帳號',
        'restore' => '還原帳號',
        'reset_password' => '重設密碼',
    ],

    'confirm' => [
        'deactivate' => '確定要停用「:name」嗎？停用後這個帳號無法登入，目前已登入的連線也會被登出。',
        'delete' => '確定要刪除「:name」嗎？帳號會被標記為已刪除（可以還原），目前已登入的連線會被登出。',
        'restore' => '確定要還原「:name」嗎？',
    ],

    'form' => [
        'create_title' => '新增用戶',
        'edit_title' => '編輯用戶',
        'section_account' => '帳號資料',
        'section_contact' => '聯絡與所屬',
        'section_password' => '登入密碼',
        'section_reset_password' => '重設密碼',
        'username' => '帳號',
        'username_hint' => '3～30 個字元，限英文、數字、點、底線、連字號，建立後仍可修改。',
        'name' => '姓名',
        'email' => '電子郵件',
        'email_hint' => '派工通知信會寄到這個信箱。',
        'phone' => '電話',
        'role' => '身分權限',
        'role_placeholder' => '請選擇身分權限',
        'department' => '部門',
        'department_placeholder' => '未指定',
        'is_active' => '帳號啟用（關閉後無法登入）',
        'password' => '密碼',
        'password_hint' => '至少 8 個字元。',
        'password_confirmation' => '確認密碼',
        'new_password' => '新密碼',
        'reset_password_hint' => '設定後該用戶目前已登入的連線會被登出，需用新密碼重新登入。',
        'created_at' => '建立時間',
        'last_login_at' => '最後登入',
    ],

    'flash' => [
        'created' => '用戶已新增。',
        'updated' => '用戶資料已更新。',
        'activated' => '帳號已啟用。',
        'deactivated' => '帳號已停用。',
        'password_reset' => '密碼已重設。',
        'deleted' => '用戶已刪除（可在「已刪除」篩選中還原）。',
        'restored' => '用戶已還原。',
    ],

    'errors' => [
        'self_protected' => '不能刪除、停用自己的帳號，也不能變更自己的身分權限。',
        'last_admin' => '系統必須保留至少一位啟用中的系統管理員，這個帳號不能被刪除、停用或降級。',
    ],
];
