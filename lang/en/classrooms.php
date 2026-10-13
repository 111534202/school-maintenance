<?php

// i18n: Classroom master data. Keep the keys identical to lang/zh_TW/classrooms.php.
return [
    'index_title' => 'Classrooms',
    'add_classroom' => 'Add Classroom',
    'empty_list' => 'No classrooms yet',
    'no_value' => '-',

    'filter' => [
        'keyword' => 'Keyword',
        'keyword_placeholder' => 'Room code / name',
        'department' => 'Department',
        'department_all' => 'All departments',
        'status' => 'Status',
        'status_all' => 'All statuses',
        'devices' => 'Devices',
        'devices_all' => 'All',
        'devices_abnormal' => 'Has abnormal devices',
    ],

    'status' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
    ],

    'table' => [
        'code' => 'Room Code',
        'name' => 'Room Name',
        'department' => 'Department',
        'location' => 'Location',
        'manager' => 'Manager',
        'devices' => 'Devices',
        'abnormal_devices' => 'Abnormal Devices',
        'open_repairs' => 'Open Repairs',
        'status' => 'Status',
        'actions' => 'Actions',
    ],

    // Tooltips of the links in the list. "Abnormal" = under repair, retired or disabled devices.
    'links' => [
        'view_devices' => 'View the devices of this classroom',
        'view_abnormal_devices' => 'View the abnormal devices of this classroom',
        'view_repairs' => 'View the open repair requests of devices in this classroom',
        'core_abnormal_hint' => 'A core device is abnormal: this classroom is flagged as having a device problem',
    ],
    'core_abnormal' => 'Core',

    'actions' => [
        'activate' => 'Activate',
        'deactivate' => 'Deactivate',
    ],

    'form' => [
        'create_title' => 'Add Classroom',
        'edit_title' => 'Edit Classroom',
        'department' => 'Department',
        'manager' => 'Manager',
        'unassigned' => '- Not assigned -',
        'campus' => 'Campus',
        'building' => 'Building',
        'floor' => 'Floor',
        'room_code' => 'Room Code',
        'room_name' => 'Room Name',
        'room_type' => 'Room Type',
    ],

    'flash' => [
        'created' => 'Classroom added.',
        'updated' => 'Classroom updated.',
        'activated' => 'Classroom activated.',
        'deactivated' => 'Classroom deactivated.',
    ],
];
