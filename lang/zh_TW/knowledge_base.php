<?php

// i18n：自助知識庫模組（列表、詳細頁、新增/編輯表單）。
// 修改這個檔案時，記得同步修改 lang/en/knowledge_base.php，兩邊的 key 必須完全一致。
return [
    'index_title' => '知識庫列表',
    'index_heading' => '自助排除知識庫',
    'add_entry' => '新增項目',
    'empty_list' => '目前還沒有任何知識庫項目。',

    // 知識庫列表表格欄位標題。
    'table' => [
        'title' => '標題',
        'category' => '分類',
        'status' => '狀態',
        'updated_at' => '更新時間',
        'actions' => '操作',
    ],

    'uncategorized' => '未分類',
    'published' => '已上架',
    'unpublished' => '未上架',
    'confirm_delete' => '確定要刪除「:title」嗎？',

    'create_title' => '新增知識庫項目',
    'edit_title' => '編輯知識庫項目',

    // 知識庫新增／編輯表單的欄位名稱。
    'form' => [
        'title' => '標題',
        'category' => '分類（選填，例如：投影機 / 電腦 / 網路）',
        'symptom' => '常見故障現象',
        'solution' => '自助排除步驟',
        'is_published' => '上架顯示給使用者',
    ],

    // 詳細頁的文字。
    'show' => [
        'category_prefix' => '分類：',
        'status_prefix' => '狀態：',
        'symptom_heading' => '常見故障現象',
        'solution_heading' => '自助排除步驟',
        'resolved_prompt' => '照著上面步驟排除後，問題解決了嗎？',
        'resolved_button' => '問題已解決',
        'unresolved_button' => '無法排除，前往報修',
    ],

    // 操作成功後顯示的綠色訊息。
    'flash' => [
        'created' => '已新增知識庫項目。',
        'updated' => '已更新知識庫項目。',
        'deleted' => '已刪除知識庫項目。',
        'resolved_message' => '太好了，很高興「:title」幫你解決了問題！',
    ],
];
