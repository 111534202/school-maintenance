<?php

// i18n：部門主檔。修改這個檔案時，記得同步修改 lang/en/departments.php，兩邊的 key 必須完全一致。
return [
    'index_title' => '部門主檔',
    'add_department' => '新增部門',
    'empty_list' => '沒有符合條件的部門。',

    'filter' => [
        'keyword' => '關鍵字',
        'keyword_placeholder' => '部門名稱／代碼／備註',
        'status' => '狀態',
        'status_all' => '全部',
    ],

    'status' => [
        'active' => '啟用中',
        'inactive' => '已停用',
    ],

    'table' => [
        'code' => '部門代碼',
        'name' => '部門名稱',
        'description' => '備註',
        'users' => '用戶數',
        'classrooms' => '教室數',
        'status' => '狀態',
        'actions' => '操作',
    ],

    'actions' => [
        'activate' => '啟用部門',
        'deactivate' => '停用部門',
    ],

    'confirm' => [
        'deactivate' => '確定要停用「:name」嗎？停用後不會再出現在用戶、教室的部門下拉選單，既有資料不受影響。',
        'delete' => '確定要刪除「:name」嗎？',
    ],

    'form' => [
        'create_title' => '新增部門',
        'edit_title' => '編輯部門',
        'code' => '部門代碼',
        'code_hint' => '選填，限英文、數字、點、底線、連字號，不能重複。',
        'name' => '部門名稱',
        'description' => '備註',
        'is_active' => '啟用（關閉後不會出現在下拉選單）',
    ],

    'flash' => [
        'created' => '部門已新增。',
        'updated' => '部門已更新。',
        'activated' => '部門已啟用。',
        'deactivated' => '部門已停用。',
        'deleted' => '部門已刪除。',
    ],

    'errors' => [
        'in_use' => '還有 :users 位用戶、:classrooms 間教室隸屬於這個部門，不能刪除。請先把他們改到別的部門，或改用「停用」。',
    ],
];
