<?php

// i18n：寄信設定。修改這個檔案時，記得同步修改 lang/en/mail_settings.php，兩邊的 key 必須完全一致。
return [
    'title' => '寄信設定',
    'status_on' => '目前已啟用：系統用「:host」寄信（忘記密碼臨時密碼信、派工通知信都會真的寄出）。',
    'status_off' => '目前未啟用：系統使用 .env 的寄信方式（:mailer）。填好下面的設定並測試成功後，打開「啟用」即可。',
    'preset' => '常用設定',
    'preset_hint' => '學校信箱如果是 Google Workspace（Gmail）就按 Gmail；按下去只會自動填伺服器與連接埠。',
    'presets' => [
        'gmail' => 'Gmail／Google Workspace',
        'outlook' => 'Outlook／Microsoft 365',
    ],
    'host' => '寄信伺服器',
    'port' => '連接埠',
    'encryption' => '加密方式',
    'encryptions' => [
        'tls' => 'TLS（連接埠 587）',
        'ssl' => 'SSL（連接埠 465）',
        'none' => '不加密',
    ],
    'smtp_account' => '登入帳號',
    'smtp_account_hint' => '通常就是完整的信箱位址。',
    'password' => '密碼',
    'password_saved' => '已儲存（不修改請留空）',
    'password_hint' => 'Gmail 請填「應用程式密碼」，不是平常登入信箱的密碼。密碼加密保存，這個畫面不會再顯示。',
    'from_address' => '寄件人信箱',
    'from_address_hint' => 'Gmail 只能用登入帳號本人的信箱當寄件人。',
    'from_label' => '寄件人顯示名稱',
    'is_enabled' => '啟用（系統改用這組設定寄信）',
    'is_enabled_hint' => '關閉時維持 .env 的寄信方式。',
    'save' => '儲存設定',
    'saved' => '寄信設定已儲存。',

    'test_title' => '寄一封測試信',
    'test_hint' => '用上面「已儲存」的設定寄，不管有沒有啟用。改了設定請先按儲存再測試。',
    'recipient' => '收件信箱',
    'test_button' => '寄測試信',
    'test_need_save' => '請先儲存寄信設定，再寄測試信。',
    'test_sent' => '測試信已寄出，請到 :recipient 收信（沒收到請看垃圾郵件匣）。',
    'test_failed' => '測試信寄不出去：:reason',
    'test_subject' => '【學校設備維保系統】寄信測試',
    'test_body' => '這是一封測試信。收到代表系統的寄信設定正確，可以放心啟用。',

    'help_title' => '使用 Gmail／學校 Google 信箱的步驟',
    'help' => [
        '先登入該信箱的 Google 帳戶，開啟「兩步驟驗證」。',
        '到 Google 帳戶的「應用程式密碼」建立一組密碼（16 碼），貼到上面的密碼欄。',
        '登入帳號與寄件人信箱都填同一個信箱，按「儲存設定」，再按「寄測試信」。',
        '收到測試信後，回來打開「啟用」並儲存。之後忘記密碼信與派工通知信就會真的寄出。',
        '如果學校管理員關閉了應用程式密碼，就無法使用，請改用學校提供的寄信伺服器，或請管理員協助。',
    ],
];
