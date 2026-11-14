<?php

// i18n: mail settings. Keep this file in sync with lang/zh_TW/mail_settings.php — both must have exactly the same keys.
return [
    'title' => 'Mail Settings',
    'status_on' => 'Enabled: the system sends mail through ":host" (password-reset and dispatch notification mails are really sent).',
    'status_off' => 'Not enabled: the system uses the mail method in .env (:mailer). Fill in the settings below, test them, then turn on "Enable".',
    'preset' => 'Presets',
    'preset_hint' => 'If the school mailbox is Google Workspace (Gmail), choose Gmail. This only fills in the server and port.',
    'presets' => [
        'gmail' => 'Gmail / Google Workspace',
        'outlook' => 'Outlook / Microsoft 365',
    ],
    'host' => 'Mail server',
    'port' => 'Port',
    'encryption' => 'Encryption',
    'encryptions' => [
        'tls' => 'TLS (port 587)',
        'ssl' => 'SSL (port 465)',
        'none' => 'None',
    ],
    'smtp_account' => 'Login account',
    'smtp_account_hint' => 'Usually the full email address.',
    'password' => 'Password',
    'password_saved' => 'Saved (leave blank to keep)',
    'password_hint' => 'For Gmail use an "app password", not your normal login password. It is stored encrypted and never shown again.',
    'from_address' => 'Sender address',
    'from_address_hint' => 'Gmail only allows the login account\'s own address as the sender.',
    'from_label' => 'Sender display name',
    'is_enabled' => 'Enable (send mail with these settings)',
    'is_enabled_hint' => 'When off, the mail method in .env is used.',
    'save' => 'Save settings',
    'saved' => 'Mail settings saved.',

    'test_title' => 'Send a test mail',
    'test_hint' => 'Uses the saved settings above, whether or not they are enabled. Save changes before testing.',
    'recipient' => 'Recipient',
    'test_button' => 'Send test mail',
    'test_need_save' => 'Please save the mail settings before sending a test mail.',
    'test_sent' => 'Test mail sent. Check the inbox of :recipient (and the spam folder).',
    'test_failed' => 'The test mail could not be sent: :reason',
    'test_subject' => '[School Maintenance System] Mail test',
    'test_body' => 'This is a test mail. If you received it, the mail settings are correct and can be enabled.',

    'help_title' => 'Steps for Gmail / a school Google mailbox',
    'help' => [
        'Sign in to the Google account of that mailbox and turn on 2-Step Verification.',
        'In the Google account, create an "App password" (16 characters) and paste it in the password field above.',
        'Use the same address for the login account and the sender, press "Save settings", then "Send test mail".',
        'After the test mail arrives, turn on "Enable" and save. Password-reset and dispatch notification mails will then be really sent.',
        'If the school administrator has disabled app passwords this will not work; use the school\'s own mail server or ask the administrator.',
    ],
];
