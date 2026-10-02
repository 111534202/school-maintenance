<?php

// i18n：身分主檔。修改這個檔案時，記得同步修改 lang/en/roles.php，兩邊的 key 必須完全一致。
return [
    'index_title' => '身分主檔',
    'add_role' => '新增身分',
    'empty_list' => '沒有符合條件的身分。',
    'system_badge' => '系統內建',
    'admin_all_permissions' => '全部權限',
    'permission_count' => ':count 項權限',

    'filter' => [
        'keyword' => '關鍵字',
        'keyword_placeholder' => '身分名稱／說明',
    ],

    'table' => [
        'name' => '身分名稱',
        'description' => '說明',
        'users' => '用戶數',
        'permissions' => '開放權限',
        'actions' => '操作',
    ],

    'confirm' => [
        'delete' => '確定要刪除身分「:name」嗎？',
    ],

    'form' => [
        'create_title' => '新增身分',
        'edit_title' => '編輯身分',
        'section_basic' => '基本資料',
        'section_permissions' => '開放的權限',
        'name' => '身分名稱',
        'description' => '說明',
        'permissions_hint' => '勾選這個身分可以使用的功能。勾選後，擁有這個身分的用戶下次開啟頁面就會生效。',
        'select_all' => '全選',
        'clear_all' => '全部取消',
        'admin_notice' => '系統管理員永遠擁有全部權限，不能調整。',
        'system_notice' => '這是系統內建身分：可以調整名稱、說明與權限，但不能刪除。',
    ],

    'flash' => [
        'created' => '身分已新增。',
        'updated' => '身分已更新。',
        'deleted' => '身分已刪除。',
    ],

    'errors' => [
        'system_role' => '系統內建的身分不能刪除。',
        'in_use' => '還有 :count 位用戶使用這個身分，不能刪除。請先把他們改成別的身分。',
    ],
];
