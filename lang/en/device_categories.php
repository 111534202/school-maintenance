<?php

// i18n: Device category master data. Keep the keys identical to lang/zh_TW/device_categories.php.
return [
    'index_title' => 'Device Categories',
    'add_category' => 'Add Category',
    'empty_list' => 'No device categories yet',
    'existing' => 'Existing categories',

    'filter' => [
        'keyword' => 'Category name',
        'keyword_placeholder' => 'Search category name',
        'search_placeholder' => 'Search category names',
        'search' => 'Search',
        'clear' => 'Clear',
    ],

    'table' => [
        'name' => 'Category Name',
        'devices_count' => 'Devices in use',
        'actions' => 'Actions',
    ],

    'confirm' => [
        'delete' => 'Delete this category?',
    ],

    'form' => [
        'create_title' => 'Add Device Category',
        'edit_title' => 'Edit Device Category',
        'name' => 'Category Name',
        'name_placeholder' => 'Enter a new category name',
        'submit' => 'Add',
    ],

    'flash' => [
        'created' => 'Device category added.',
        'updated' => 'Device category updated.',
        'deleted' => 'Device category deleted.',
    ],

    'errors' => [
        'in_use' => 'This category is still used by devices and cannot be deleted.',
        'has_disabled_devices' => 'This category still has disabled devices and cannot be deleted.',
    ],
];
