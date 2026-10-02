<?php

// i18n：權限名稱與說明（身分主檔的勾選畫面用）。清單本身在 App\Support\PermissionCatalog，
// 新增權限時兩邊都要補。修改這個檔案時，記得同步修改 lang/en/permissions.php。
//
// 注意：權限代碼裡的句點在 Laravel 翻譯裡代表「下一層」，所以 'repairs.create' 要寫成
// 'repairs' => ['create' => [...]] 這種巢狀結構，不能寫成一個含句點的平面 key。
return [
    'groups' => [
        'master' => '主檔管理',
        'repair' => '報修與維修',
        'knowledge' => '自助知識庫',
    ],

    'items' => [
        'users' => [
            'manage' => ['name' => '用戶主檔', 'description' => '新增、編輯、停用、刪除用戶，重設密碼，指定身分與部門'],
        ],
        'roles' => [
            'manage' => ['name' => '身分主檔', 'description' => '新增、編輯、刪除身分，調整每個身分開放的權限'],
        ],
        'departments' => [
            'manage' => ['name' => '部門主檔', 'description' => '新增、編輯、停用、刪除部門'],
        ],
        'classrooms' => [
            'manage' => ['name' => '教室主檔', 'description' => '新增、編輯、啟用或停用教室'],
        ],
        'device-categories' => [
            'manage' => ['name' => '設備類別', 'description' => '新增、編輯、刪除設備類別'],
        ],
        'devices' => [
            'manage' => ['name' => '設備主檔', 'description' => '新增、編輯、停用設備，產生設備 QR Code'],
        ],
        'audit-logs' => [
            'view' => ['name' => '操作紀錄查詢', 'description' => '查看全系統的操作紀錄'],
        ],
        'repairs' => [
            'create' => ['name' => '提出報修', 'description' => '新增報修單、用條碼掃描帶入設備'],
            'dispatch' => ['name' => '派工與重新指派', 'description' => '把報修單指派給維修人員、更換維修人員'],
            'process' => ['name' => '處理維修', 'description' => '開始處理報修單、填寫維修紀錄'],
            'accept' => ['name' => '驗收報修', 'description' => '驗收通過結案，或驗收不通過退回重修'],
            'assignable' => ['name' => '可被指派為維修人員', 'description' => '會出現在派工的維修人員下拉選單裡'],
            'notice_cc' => ['name' => '接收派工通知副本', 'description' => '每次派工時，通知信會副本一份給擁有這個身分的用戶'],
        ],
        'knowledge-base' => [
            'manage' => ['name' => '管理知識庫', 'description' => '新增、編輯、刪除知識庫文章（所有人都能查看）'],
        ],
    ],
];
