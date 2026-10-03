<?php

// i18n: Repair log module. Keep the keys identical to lang/zh_TW/repair_logs.php.
return [
    'create_title' => 'Fill In Repair Log',
    'case_line' => 'Case: :title (:location)',
    'not_filled_location' => 'Location not filled in',

    // 維修填單表單的欄位名稱與說明。
    'form' => [
        'cause' => 'Cause Description',
        'resolution' => 'Resolution',
        'started_at' => 'Start Time',
        'ended_at' => 'End Time',
        'parts_used_note' => 'Parts Used (optional, e.g. Replaced projector bulb x1)',
        'parts_used_note_hint' => "The parts master table hasn't been merged yet; using free text for now — will switch to a dropdown with automatic inventory deduction later.",
        'attachments' => 'Before/After Photos/Videos (optional, up to 5 files, jpg/png/pdf/mp4/mov/webm, max 20MB each)',
    ],

    'submit' => 'Submit Repair Log (case will move to Pending Review)',

    // 操作成功後顯示的綠色訊息。
    'flash' => [
        'submitted' => 'Repair log submitted; the case has moved to pending review.',
    ],
];
