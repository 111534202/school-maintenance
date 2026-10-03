<?php

// i18n：設備主檔（列表、表單、詳細頁、掃描 QR 後的入口頁）。修改這個檔案時，記得同步修改 lang/en/devices.php，
// 兩邊的 key 必須完全一致。（語言檔怎麼用、怎麼新增文字，見 lang/zh_TW/common.php 檔頭。）
return [
    // 設備狀態的顯示名稱（資料庫存的是英文代碼，畫面一律顯示這裡的中文）
    'status' => [
        'normal' => '正常',
        'repairing' => '維修中',
        'retired' => '已淘汰',
        'disabled' => '停用',
    ],

    'index_title' => '設備主檔',
    'add_device' => '新增設備',
    'empty_list' => '尚無符合條件的設備',
    'no_value' => '－',
    'yes' => '是',
    'no' => '否',
    'core_badge' => '核心',
    'no_department' => '未指定部門',

    // 篩選列的文字：欄位名稱、輸入框提示、「全部」選項。
    'filter' => [
        'keyword' => '關鍵字',
        'keyword_placeholder' => '設備編號／資產編號／品牌／型號',
        'classroom' => '教室',
        'classroom_all' => '所有教室',
        'category' => '類別',
        'category_all' => '所有類別',
        'status' => '狀態',
        'status_all' => '所有狀態',
    ],

    // 表格欄位標題。
    'table' => [
        'code' => '設備編號',
        'category' => '類別',
        'brand_model' => '品牌／型號',
        'classroom' => '所在教室',
        'status' => '狀態',
        'core' => '核心設備',
        'actions' => '操作',
    ],

    // 按鈕的提示文字（滑鼠移上去時看到的說明）。
    'actions' => [
        'detail' => '詳細',
        'disable' => '停用',
    ],

    // 停用前跳出的確認視窗文字。
    'confirm' => [
        'disable' => '確定要停用此設備嗎？',
    ],

    // 新增／編輯表單的標題與欄位名稱。
    'form' => [
        'create_title' => '新增設備',
        'edit_title' => '編輯設備',
        'code' => '設備編號',
        'asset_code' => '資產編號',
        'category' => '類別',
        'classroom' => '所在教室',
        'please_select' => '－ 請選擇 －',
        'brand' => '品牌',
        'model' => '型號',
        'serial_number' => '序號',
        'warranty_until' => '保固期限',
        'status' => '狀態',
        'is_core' => '核心設備',
    ],

    // 設備詳細頁。
    'show' => [
        'title' => '設備詳細資料',
        'brand_model' => '品牌 / 型號',
        'qr_title' => '設備 QR Code',
        'qr_hint' => '掃描後開啟本設備的自助入口頁，內容只依設備代碼即時查詢，不會寫死教室等資料。',
        'qr_download' => '下載 QR Code',
        'qr_preview' => '預覽入口頁',
        'qr_alt' => '設備 QR Code',
    ],

    // 掃描 QR 後看到的設備入口頁。
    'entry' => [
        'title_suffix' => '設備入口',
        'current_status' => '目前狀態',
        'core_device' => '核心設備',
        'need_help' => '需要協助嗎？',
        'go_repair' => '前往報修',
        'go_repair_unavailable' => '前往報修（功能尚未上線）',
        'view_kb' => '查看自助排除知識庫',
        'tips_title' => '自助排除小提醒：',
        'tip_power' => '先確認電源與連接線是否正常',
        'tip_reboot' => '重新開機一次再測試',
        'tip_report' => '仍無法排除，請使用上方報修按鈕',
    ],

    // 操作成功後顯示的綠色訊息。
    'flash' => [
        'created' => '設備已新增。',
        'updated' => '設備已更新。',
        'disabled' => '設備已停用。',
    ],
];
