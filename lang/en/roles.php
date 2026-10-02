<?php

// i18n: Role master. Keep the keys identical to lang/zh_TW/roles.php.
return [
    'index_title' => 'Role Master',
    'add_role' => 'Add Role',
    'empty_list' => 'No roles match the current filter.',
    'system_badge' => 'Built-in',
    'admin_all_permissions' => 'All permissions',
    'permission_count' => ':count permission(s)',

    'filter' => [
        'keyword' => 'Keyword',
        'keyword_placeholder' => 'Role name / description',
    ],

    'table' => [
        'name' => 'Role Name',
        'description' => 'Description',
        'users' => 'Users',
        'permissions' => 'Permissions',
        'actions' => 'Actions',
    ],

    'confirm' => [
        'delete' => 'Delete the role ":name"?',
    ],

    'form' => [
        'create_title' => 'Add Role',
        'edit_title' => 'Edit Role',
        'section_basic' => 'Basics',
        'section_permissions' => 'Granted Permissions',
        'name' => 'Role Name',
        'description' => 'Description',
        'permissions_hint' => 'Tick the features this role can use. Changes take effect the next time a user with this role opens a page.',
        'select_all' => 'Select all',
        'clear_all' => 'Clear all',
        'admin_notice' => 'The system administrator always has every permission and cannot be changed.',
        'system_notice' => 'This is a built-in role: you can change its name, description and permissions, but it cannot be deleted.',
    ],

    'flash' => [
        'created' => 'Role added.',
        'updated' => 'Role updated.',
        'deleted' => 'Role deleted.',
    ],

    'errors' => [
        'system_role' => 'Built-in roles cannot be deleted.',
        'in_use' => ':count user(s) still use this role, so it cannot be deleted. Move them to another role first.',
    ],
];
