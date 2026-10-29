<?php

// i18n：派工通知信的文字（主旨、標題、內文、按鈕）。信件版面在 resources/views/emails/repairs/dispatched.blade.php。修改時記得兩種語言的 key 要一致。
return [
    // 派工通知信的標題與內文。
    'dispatched' => [
        'subject' => '[Repair Dispatched] :title',
        'heading' => 'Repair Request Dispatched',
        'intro' => 'The repair request ":title" has been assigned to a technician. Please note the scheduled time.',
        'description_heading' => 'Issue description:',
        'view_button' => 'View Repair Request',
        'footer' => 'This is an automated message. Contact the IT department with questions.',
    ],
];
