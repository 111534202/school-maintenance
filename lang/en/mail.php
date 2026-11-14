<?php

// i18n：派工通知信的文字（主旨、標題、內文、按鈕）。信件版面在 resources/views/emails/repairs/dispatched.blade.php。修改時記得兩種語言的 key 要一致。
return [
    // 派工通知信的標題與內文。
    'temporary_password' => [
        'subject' => '[School Maintenance System] Your temporary password',
        'heading' => 'Temporary password',
        'intro' => 'Hello :name, a password reset was requested for account ":account". Your temporary password is:',
        'expires' => 'It is valid until :time and can be used only once.',
        'steps' => 'Sign in with your account and this temporary password; you will then be asked to set a new password right away.',
        'button' => 'Go to sign in',
        'not_you' => 'If you did not request this, ignore this email; your current password is unchanged.',
        'footer' => 'This is an automated message. Contact IT if you have questions.',
    ],

    'dispatched' => [
        'subject' => '[Repair Dispatched] :title',
        'heading' => 'Repair Request Dispatched',
        'intro' => 'The repair request ":title" has been assigned to a technician. Please note the scheduled time.',
        'description_heading' => 'Issue description:',
        'view_button' => 'View Repair Request',
        'footer' => 'This is an automated message. Contact the IT department with questions.',
    ],
];
