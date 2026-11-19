<?php

// i18n: Role master. Keep the keys identical to lang/zh_TW/roles.php.
return [
    'index_title' => 'Role Master',
    'add_role' => 'Add Role',
    'empty_list' => 'No roles match the current filter.',
    'system_badge' => 'Built-in',
    'admin_all_permissions' => 'All permissions',
    'permission_count' => ':count permission(s)',

    // 篩選列的文字：欄位名稱、輸入框提示、「全部」選項。
    'filter' => [
        'keyword' => 'Keyword',
        'keyword_placeholder' => 'Role name / description',
    ],

    // 表格欄位標題。
    'table' => [
        'name' => 'Role Name',
        'description' => 'Description',
        'users' => 'Users',
        'permissions' => 'Permissions',
        'actions' => 'Actions',
    ],

    // 刪除或停用前跳出的確認視窗文字（:name 之類帶冒號的字會被換成實際名稱）。
    'confirm' => [
        'delete' => 'Delete the role ":name"?',
    ],

    // 新增／編輯表單的標題、欄位名稱與說明文字。
    'form' => [
        'create_title' => 'Add Role',
        'edit_title' => 'Edit Role',
        'section_basic' => 'Basics',
        'section_permissions' => 'Granted Permissions',
        'name' => 'Role Name',
        'description' => 'Description',
        'permissions_hint' => 'Tick the features this role can use. Changes take effect the next time a user with this role opens a page.',
        'select_all' => 'Select all',
        'clear_all' => 'Clear all',
        'admin_notice' => 'The system administrator always has every permission and cannot be changed.',
        'system_notice' => 'This is a built-in role: its code is fixed; you can change its name, description and permissions, and delete it when no user has it.',
    ],

    // 操作成功後顯示的綠色訊息。
    'flash' => [
        'created' => 'Role added.',
        'updated' => 'Role updated.',
        'deleted' => 'Role deleted.',
    ],

    // 操作被擋下時顯示的紅色錯誤訊息。
    'errors' => [
        'admin_role' => 'The administrator role cannot be deleted: the system needs one role with every permission.',
        'in_use' => ':count user(s) still use this role, so it cannot be deleted. Move them to another role first.',
    ],
];
