<?php

// i18n: Major master. Keep the keys identical to lang/zh_TW/majors.php.
return [
    'index_title' => 'Major Master',
    'add_major' => 'Add Major',
    'empty_list' => 'No majors match the current filter.',
    'intro' => 'Feeds the major, grade and class drop-downs of space requests. Five-year-college and undergraduate programmes share this list, so every major sets its own number of grades and classes; classes are named 忠, 孝, 仁, 愛 in order.',

    'filter' => [
        'keyword' => 'Keyword',
        'keyword_placeholder' => 'Name / short name / code',
        'status' => 'Status',
        'status_all' => 'All',
    ],

    'status' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
    ],

    'table' => [
        'code' => 'Code',
        'name' => 'Major',
        'abbreviation' => 'Short name',
        'grades' => 'Grades',
        'classes' => 'Classes per grade',
        'example' => 'Class name example',
        'reservations' => 'Space requests',
        'status' => 'Status',
        'actions' => 'Actions',
    ],

    'actions' => [
        'activate' => 'Activate major',
        'deactivate' => 'Deactivate major',
    ],

    'confirm' => [
        'deactivate' => 'Deactivate ":name"? It will no longer appear in the major drop-down of space requests; existing requests are not affected.',
        'delete' => 'Delete major ":name"?',
    ],

    'form' => [
        'create_title' => 'Add Major',
        'edit_title' => 'Edit Major',
        'code' => 'Code',
        'code_hint' => 'Optional. Letters, digits, dot, underscore and hyphen only; must be unique.',
        'name' => 'Major name',
        'abbreviation' => 'Short name',
        'abbreviation_hint' => 'Used to build the class name, e.g. "資" gives 資一忠.',
        'grade_count' => 'Number of grades',
        'grade_count_hint' => 'How many grades this major has (usually 5 for five-year college, 4 for undergraduate; 1-9).',
        'class_count' => 'Classes per grade',
        'class_count_hint' => 'How many classes each grade has (1-4), named 忠, 孝, 仁, 愛 in order.',
        'example' => 'Class name example: ',
        'is_active' => 'Active (inactive majors are hidden from the drop-down)',
    ],

    'flash' => [
        'created' => 'Major added.',
        'updated' => 'Major updated.',
        'activated' => 'Major activated.',
        'deactivated' => 'Major deactivated.',
        'deleted' => 'Major deleted.',
    ],

    'errors' => [
        'in_use' => ':count space request(s) already use this major, so it cannot be deleted. Deactivate it instead: it leaves the drop-down and the history is kept.',
    ],
];
