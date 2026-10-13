<?php

// i18n: permission names and descriptions (shown on the role master tick screen). The list itself
// lives in App\Support\PermissionCatalog — add to both places when introducing a permission.
//
// Note: a dot in a permission code means "next level" in Laravel translations, so 'repairs.create'
// must be written as 'repairs' => ['create' => [...]] — not as one flat key containing a dot.
return [
    // 權限分組的名稱（身分主檔勾選畫面上的卡片標題）。
    'groups' => [
        'master' => 'Master Data',
        'repair' => 'Repairs & Maintenance',
        'maintenance' => 'Maintenance & AI Predictive Maintenance',
        'knowledge' => 'Knowledge Base',
    ],

    // 每個權限的名稱與說明；鍵名必須和 App\Support\PermissionCatalog 裡的權限代碼一致（代碼裡的點代表下一層，所以這裡是巢狀陣列）。
    'items' => [
        'users' => [
            'manage' => ['name' => 'User Master', 'description' => 'Add, edit, deactivate and delete users, reset passwords, assign roles and departments'],
        ],
        'roles' => [
            'manage' => ['name' => 'Role Master', 'description' => 'Add, edit and delete roles, and change the permissions each role grants'],
        ],
        'departments' => [
            'manage' => ['name' => 'Department Master', 'description' => 'Add, edit, deactivate and delete departments'],
        ],
        'classrooms' => [
            'manage' => ['name' => 'Classroom Master', 'description' => 'Add, edit, activate or deactivate classrooms'],
        ],
        'device-categories' => [
            'manage' => ['name' => 'Device Categories', 'description' => 'Add, edit and delete device categories'],
        ],
        'devices' => [
            'manage' => ['name' => 'Device Master', 'description' => 'Add, edit and deactivate devices, generate device QR codes'],
        ],
        'audit-logs' => [
            'view' => ['name' => 'View Audit Log', 'description' => 'View the system-wide audit log'],
        ],
        'repairs' => [
            'create' => ['name' => 'Submit Repairs', 'description' => 'Create repair requests, fill in devices by scanning a barcode'],
            'dispatch' => ['name' => 'Dispatch & Reassign', 'description' => 'Assign repair requests to technicians and change technicians'],
            'process' => ['name' => 'Process Repairs', 'description' => 'Start work on repair requests and fill in repair logs'],
            'accept' => ['name' => 'Accept Repairs', 'description' => 'Accept and close a repair, or reject it back for rework'],
            'assignable' => ['name' => 'Assignable as Technician', 'description' => 'Appears in the technician dropdown when dispatching'],
            'notice_cc' => ['name' => 'Receive Dispatch Notice Copy', 'description' => 'Users with this role are copied on every dispatch notification email'],
        ],
        'maintenance' => [
            'view' => ['name' => 'View Maintenance Data', 'description' => 'View maintenance items, plans, orders and results, device profiles and the completion rate'],
            'manage' => ['name' => 'Manage Maintenance Plans', 'description' => 'Add, edit and deactivate maintenance items and plans, create orders from plans'],
            'report' => ['name' => 'Report Maintenance Results', 'description' => 'Fill in maintenance results (OK / NG); an NG is turned into a repair request'],
        ],
        'ai-maintenance' => [
            'manage' => ['name' => 'AI Preventive Maintenance', 'description' => 'Scan device risk, approve or reject AI candidates, tune AI parameters'],
        ],
        'knowledge-base' => [
            'manage' => ['name' => 'Manage Knowledge Base', 'description' => 'Add, edit and delete knowledge base articles (everyone can read them)'],
        ],
    ],
];
