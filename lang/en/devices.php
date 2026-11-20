<?php

// i18n: Device master data (list, form, detail page, and the entry page shown after scanning a QR code).
// Keep the keys identical to lang/zh_TW/devices.php.
return [
    // Display names for device status codes stored in the database.
    'status' => [
        'normal' => 'Normal',
        'repairing' => 'Awaiting repair',
        'retired' => 'Retired',
        'disabled' => 'Disabled',
    ],

    'index_title' => 'Devices',
    'add_device' => 'Add Device',
    'short_category' => 'Category',   // 手機版右上角按鈕的短字（+Category）
    'short_device' => 'Device',
    'empty_list' => 'No matching devices',
    'no_value' => '-',
    'yes' => 'Yes',
    'no' => 'No',
    // Repeat-repair notice: shown on the device list and repair board when the repair count in the last
    // Device::REPEAT_REPAIR_DAYS days reaches the threshold.
    'repeat_repair_badge' => ':count repairs in 30 days',
    'repeat_repair_hint' => 'This device has been repaired repeatedly recently — consider inspecting it or planning a replacement',
    // QR sticker batch print page (DeviceController::qrSheet).
    'qr_sheet' => [
        'button' => 'Print QR stickers',
        'title' => 'Device QR sticker print',
        'print' => 'Print',
        'back' => 'Back to devices',
        'summary' => ':count stickers, about :pages A4 pages',
        'too_many' => ':total devices match, but at most :limit are printed at a time. Add filters (classroom, category…) on the device list and print in batches.',
        'tip' => 'When printing choose A4, default margins, 100% scale, and turn off headers and footers. 63.5 × 33.9 mm label paper (3×8 = 24 per page) can be used directly; on plain paper cut along the dashed lines.',
        'empty' => 'No devices match, nothing to print.',
    ],

    'no_department' => 'No department',

    'filter' => [
        'keyword' => 'Keyword',
        'keyword_placeholder' => 'Device code / asset code / brand / model',
        'classroom' => 'Classroom',
        'classroom_all' => 'All classrooms',
        'category' => 'Category',
        'category_all' => 'All categories',
        'status' => 'Status',
        'status_all' => 'All statuses',
        'only_abnormal' => 'Showing abnormal devices only (with open repair requests, retired excluded)',
    ],

    'table' => [
        'code' => 'Device Code',
        'category' => 'Category',
        'brand_model' => 'Brand / Model',
        'classroom' => 'Classroom',
        'status' => 'Status',
        'open_repairs' => 'Open Repairs',
        'actions' => 'Actions',
    ],

    // Tooltips of the links in the list.
    'links' => [
        'view_repairs' => 'View the open repair requests of this device',
    ],

    'actions' => [
        'detail' => 'Details',
        'disable' => 'Disable',
    ],

    'confirm' => [
        'disable' => 'Disable this device?',
    ],

    'form' => [
        'create_title' => 'Add Device',
        'edit_title' => 'Edit Device',
        'code' => 'Device Code',
        'asset_code' => 'Asset Code',
        'category' => 'Category',
        'classroom' => 'Classroom',
        'please_select' => '- Please select -',
        'brand' => 'Brand',
        'model' => 'Model',
        'serial_number' => 'Serial Number',
        'warranty_end_date' => 'Warranty Until',
        'status' => 'Status',
    ],

    'show' => [
        'title' => 'Device Details',
        'brand_model' => 'Brand / Model',
        'qr_title' => 'Device QR Code',
        'qr_hint' => 'Scanning opens the self-service entry page of this device. It looks the device up by its code at scan time, so nothing such as the classroom is hard-coded in the code.',
        'qr_download' => 'Download QR Code',
        'qr_preview' => 'Preview entry page',
        'qr_alt' => 'Device QR Code',
    ],

    'entry' => [
        'title_suffix' => 'Device entry',
        'current_status' => 'Current status',
        'need_help' => 'Need help?',
        'go_repair' => 'Report a problem',
        'go_repair_unavailable' => 'Report a problem (not available yet)',
        'go_repair_retired' => 'This device is retired and cannot be reported',
        'view_kb' => 'View the self-service troubleshooting guide',
        'tips_title' => 'Quick self-help tips:',
        'tip_power' => 'Check that the power and cables are connected',
        'tip_reboot' => 'Restart once and test again',
        'tip_report' => 'If it still does not work, use the report button above',
    ],

    'flash' => [
        'created' => 'Device added.',
        'updated' => 'Device updated.',
        'disabled' => 'Device disabled.',
    ],
];
