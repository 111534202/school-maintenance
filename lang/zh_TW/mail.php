<?php

// i18n：派工通知信的文字（主旨、標題、內文、按鈕）。信件版面在 resources/views/emails/repairs/dispatched.blade.php。修改時記得兩種語言的 key 要一致。
return [
    // 忘記密碼寄出的臨時密碼信（resources/views/emails/auth/temporary-password.blade.php）。
    'temporary_password' => [
        'subject' => '【學校設備維保系統】你的臨時密碼',
        'heading' => '臨時密碼',
        'intro' => ':name 你好，帳號「:account」申請了忘記密碼。下面是你的臨時密碼：',
        'expires' => '這組臨時密碼在 :time 以前有效，而且只能用一次。',
        'steps' => '請用帳號與這組臨時密碼登入，系統會請你立刻設定一組新密碼。',
        'button' => '前往登入',
        'not_you' => '如果不是你申請的，請不要理會這封信；你原本的密碼不會有任何改變。',
        'footer' => '此信件由系統自動寄出，如有疑問請洽資訊組。',
    ],

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
