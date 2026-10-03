<?php

// i18n：派工通知信的文字（主旨、標題、內文、按鈕）。信件版面在 resources/views/emails/repairs/dispatched.blade.php。修改時記得兩種語言的 key 要一致。
return [
    // 派工通知信的標題與內文。
    'dispatched' => [
        'subject' => '【報修派工通知】:title',
        'heading' => '報修案件已派工',
        'intro' => '案件「:title」已經指派維修人員，請留意處理時間。',
        'description_heading' => '故障描述：',
        'view_button' => '查看案件詳情',
        'footer' => '此信件由系統自動寄出，如有疑問請洽資訊組。',
    ],
];
