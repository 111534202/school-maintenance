<?php

// i18n: Department master. Keep the keys identical to lang/zh_TW/departments.php.
return [
    'index_title' => 'Department Master',
    'add_department' => 'Add Department',
    'empty_list' => 'No departments match the current filter.',

    // 篩選列的文字：欄位名稱、輸入框提示、「全部」選項。
    'filter' => [
        'keyword' => 'Keyword',
        'keyword_placeholder' => 'Name / code / note',
        'status' => 'Status',
        'status_all' => 'All',
    ],

    // 狀態的顯示名稱。
    'status' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
    ],

    // 表格欄位標題。
    'table' => [
        'code' => 'Code',
        'name' => 'Name',
        'description' => 'Note',
        'users' => 'Users',
        'classrooms' => 'Classrooms',
        'status' => 'Status',
        'actions' => 'Actions',
    ],

    // 按鈕的提示文字（滑鼠移上去時看到的說明）。
    'actions' => [
        'activate' => 'Activate department',
        'deactivate' => 'Deactivate department',
    ],

    // 刪除或停用前跳出的確認視窗文字（:name 之類帶冒號的字會被換成實際名稱）。
    'confirm' => [
        'deactivate' => 'Deactivate ":name"? It will no longer appear in the department dropdowns for users and classrooms; existing data is not affected.',
        'delete' => 'Delete ":name"?',
    ],

    // 新增／編輯表單的標題、欄位名稱與說明文字。
    'form' => [
        'create_title' => 'Add Department',
        'edit_title' => 'Edit Department',
        'code' => 'Code',
        'code_hint' => 'Optional. Letters, digits, dot, underscore or hyphen; must be unique.',
        'name' => 'Name',
        'description' => 'Note',
        'is_active' => 'Active (turn off to hide it from the dropdowns)',
    ],

    // 操作成功後顯示的綠色訊息。
    'flash' => [
        'created' => 'Department added.',
        'updated' => 'Department updated.',
        'activated' => 'Department activated.',
        'deactivated' => 'Department deactivated.',
        'deleted' => 'Department deleted.',
    ],

    // 操作被擋下時顯示的紅色錯誤訊息。
    'errors' => [
        'in_use' => ':users user(s) and :classrooms classroom(s) still belong to this department, so it cannot be deleted. Move them to another department first, or deactivate it instead.',
    ],
];
