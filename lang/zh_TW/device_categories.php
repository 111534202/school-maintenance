<?php

// i18n：設備類別主檔。修改這個檔案時，記得同步修改 lang/en/device_categories.php，兩邊的 key 必須完全一致。
// （語言檔怎麼用、怎麼新增文字，見 lang/zh_TW/common.php 檔頭。）
return [
    'index_title' => '設備類別管理',
    'add_category' => '新增類別',
    'empty_list' => '尚無設備類別',

    // 搜尋列的文字。
    'filter' => [
        'keyword' => '類別名稱',
        'keyword_placeholder' => '搜尋類別名稱',
    ],

    // 表格欄位標題。
    'table' => [
        'name' => '類別名稱',
        'devices_count' => '使用中設備數',
        'actions' => '操作',
    ],

    // 刪除前跳出的確認視窗文字。
    'confirm' => [
        'delete' => '確定要刪除此類別嗎？',
    ],

    // 新增／編輯表單的標題與欄位名稱。
    'form' => [
        'create_title' => '新增設備類別',
        'edit_title' => '編輯設備類別',
        'name' => '類別名稱',
    ],

    // 操作成功後顯示的綠色訊息。
    'flash' => [
        'created' => '設備類別已新增。',
        'updated' => '設備類別已更新。',
        'deleted' => '設備類別已刪除。',
    ],

    // 操作被擋下時顯示的紅色錯誤訊息。
    'errors' => [
        'in_use' => '此類別仍有設備使用中，無法刪除。',
    ],
];
