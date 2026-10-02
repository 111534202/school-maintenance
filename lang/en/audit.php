<?php

// i18n: audit log. Keep the keys identical to lang/zh_TW/audit.php.
// 'actions' keys are the first argument of AuditLogger::log(); 'types' keys are model class basenames.
return [
    'index_title' => 'Audit Log',

    'actions' => [
        'created' => 'Created',
        'updated' => 'Updated',
        'deleted' => 'Deleted',
        'restored' => 'Restored',
        'status_changed' => 'Status changed',
        'password_reset' => 'Password reset',
        'core_flag_changed' => 'Core flag changed',
        'login' => 'Login',
        'logout' => 'Logout',
        'login_failed' => 'Login failed',
        'assigned' => 'Dispatched',
        'reassigned' => 'Reassigned',
        'rejected' => 'Rejected',
    ],

    'types' => [
        'User' => 'User',
        'Role' => 'Role',
        'Department' => 'Department',
        'Classroom' => 'Classroom',
        'Device' => 'Device',
        'DeviceCategory' => 'Device category',
        'RepairRequest' => 'Repair request',
        'RepairLog' => 'Repair log',
        'KnowledgeBase' => 'Knowledge base article',
    ],

    'filter' => [
        'user' => 'User',
        'user_all' => 'All users',
        'action' => 'Event',
        'action_all' => 'All events',
        'type' => 'Object type',
        'type_all' => 'All object types',
        'system' => 'System / not logged in',
        'date_from' => 'From date',
        'date_to' => 'To date',
        'keyword' => 'Description keyword',
    ],

    'table' => [
        'time' => 'Time',
        'user' => 'User',
        'action' => 'Event',
        'description' => 'Description',
        'details' => 'Changes',
    ],

    'empty' => 'No records match the current filter',
    'system_user' => 'System',

    'messages' => [
        'login' => ':name logged in',
        'logout' => ':name logged out',
        'login_failed' => 'Login failed (account entered: :account)',
        'repair_assigned' => 'Dispatched to ":technician", repair request ":title"',
        'repair_reassigned' => 'Reassigned to ":technician", repair request ":title"',
        'repair_rejected' => 'Rejected repair request ":title"',
        'repair_status' => 'Repair request ":title" status: :from → :to',
        'repair_created' => 'Created repair request ":title"',
        'repair_log_created' => 'Filled in a repair log (repair request ":title", :hours hours), case moved to pending review',
    ],
];
