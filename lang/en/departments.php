<?php

// i18n: Department master. Keep the keys identical to lang/zh_TW/departments.php.
return [
    'index_title' => 'Department Master',
    'add_department' => 'Add Department',
    'empty_list' => 'No departments match the current filter.',

    'filter' => [
        'keyword' => 'Keyword',
        'keyword_placeholder' => 'Name / code / note',
        'status' => 'Status',
        'status_all' => 'All',
    ],

    'status' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
    ],

    'table' => [
        'code' => 'Code',
        'name' => 'Name',
        'description' => 'Note',
        'users' => 'Users',
        'classrooms' => 'Classrooms',
        'status' => 'Status',
        'actions' => 'Actions',
    ],

    'actions' => [
        'activate' => 'Activate department',
        'deactivate' => 'Deactivate department',
    ],

    'confirm' => [
        'deactivate' => 'Deactivate ":name"? It will no longer appear in the department dropdowns for users and classrooms; existing data is not affected.',
        'delete' => 'Delete ":name"?',
    ],

    'form' => [
        'create_title' => 'Add Department',
        'edit_title' => 'Edit Department',
        'code' => 'Code',
        'code_hint' => 'Optional. Letters, digits, dot, underscore or hyphen; must be unique.',
        'name' => 'Name',
        'description' => 'Note',
        'is_active' => 'Active (turn off to hide it from the dropdowns)',
    ],

    'flash' => [
        'created' => 'Department added.',
        'updated' => 'Department updated.',
        'activated' => 'Department activated.',
        'deactivated' => 'Department deactivated.',
        'deleted' => 'Department deleted.',
    ],

    'errors' => [
        'in_use' => ':users user(s) and :classrooms classroom(s) still belong to this department, so it cannot be deleted. Move them to another department first, or deactivate it instead.',
    ],
];
