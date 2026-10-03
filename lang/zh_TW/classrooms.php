<?php

// i18n：教室主檔。修改這個檔案時，記得同步修改 lang/en/classrooms.php，兩邊的 key 必須完全一致。
// （語言檔怎麼用、怎麼新增文字，見 lang/zh_TW/common.php 檔頭。）
return [
    'index_title' => '教室主檔',
    'add_classroom' => '新增教室',
    'empty_list' => '尚無教室資料',
    'no_value' => '－',

    // 篩選列的文字：欄位名稱、輸入框提示、「全部」選項。
    'filter' => [
        'keyword' => '關鍵字',
        'keyword_placeholder' => '教室代碼／名稱',
        'department' => '部門',
        'department_all' => '所有部門',
        'status' => '狀態',
        'status_all' => '所有狀態',
    ],

    // 啟用狀態的顯示名稱。
    'status' => [
        'active' => '啟用中',
        'inactive' => '已停用',
    ],

    // 表格欄位標題。
    'table' => [
        'code' => '教室代碼',
        'name' => '教室名稱',
        'department' => '所屬部門',
        'location' => '位置',
        'manager' => '管理人',
        'status' => '狀態',
        'actions' => '操作',
    ],

    // 按鈕的提示文字（滑鼠移上去時看到的說明）。
    'actions' => [
        'activate' => '啟用',
        'deactivate' => '停用',
    ],

    // 新增／編輯表單的標題、欄位名稱與說明文字。
    'form' => [
        'create_title' => '新增教室',
        'edit_title' => '編輯教室',
        'department' => '所屬部門',
        'manager' => '管理人',
        'unassigned' => '－ 未指定 －',
        'campus' => '校區',
        'building' => '大樓',
        'floor' => '樓層',
        'room_code' => '教室代碼',
        'room_name' => '教室名稱',
        'room_type' => '教室類型',
    ],

    // 操作成功後顯示的綠色訊息。
    'flash' => [
        'created' => '教室已新增。',
        'updated' => '教室已更新。',
        'activated' => '教室已啟用。',
        'deactivated' => '教室已停用。',
    ],
];
