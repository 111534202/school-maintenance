<?php

// 右下角「AI 客服」的英文文字；keywords 請和 lang/zh_TW/assistant.php 保持一致。
return [
    // 畫面上的文字。
    'ui' => [
        'title' => 'AI Assistant',
        'subtitle' => 'Answers from the knowledge base',
        'open' => 'Open AI assistant',
        'close' => 'Close AI assistant',
        'clear' => 'Clear conversation',
        'question' => 'Question',
        'placeholder' => 'e.g. 投影機開不了機',
        'send' => 'Send',
        'thinking' => 'Searching…',
        'welcome' => 'Hi, I can find troubleshooting steps, check your repair progress, or explain how things work.',
        'error' => 'Unable to answer right now. Please try again later.',
        'expired' => 'Your session has expired. Please reload the page and sign in again.',
        'too_many' => 'Too many questions. Please wait a minute.',
        'invalid' => 'Please enter a question of 1 to 200 characters.',
    ],
    // 回覆的開頭。
    'reply' => [
        'greeting' => 'Hello! Tell me what is happening, or pick a question below.',
        'found' => 'These steps may help:',
        'maybe_helpful' => 'You may also try these before reporting:',
        'not_found' => 'Nothing related was found in the knowledge base. Try different words, or report a repair.',
        'progress' => 'Your repair requests and their status:',
        'no_repairs' => 'No repair request of yours was found.',
    ],
    // 按鈕。
    'action' => [
        'repair' => 'Report a repair',
        'my_repairs' => 'My repairs',
        'password' => 'Change password',
        'reservation' => 'Space requests',
        'maintenance' => 'Maintenance orders',
    ],
    // 操作說明；結尾是 _denied 的給沒有權限的人看。
    'guide' => [
        'repair' => 'Press "Report a repair", fill in the device code or location, title and description, then submit.',
        'repair_denied' => 'Your role cannot report repairs. Please contact the administrator.',
        'reservation' => 'Open "Space requests", choose a room, date and free period, fill in class and purpose, then submit.',
        'password' => 'Press "Change password". If you forgot it, sign out and use "Forgot password" on the sign-in page.',
        'accept' => 'When a repair is pending review, open it and accept it, or send it back with a reason. If another acceptor was assigned, that person accepts it.',
        'accept_denied' => 'Your role cannot accept repairs. The assigned acceptor or an administrator will do it.',
        'maintenance' => 'Open "Maintenance orders" to see scheduled work. The assignee can start it and report the result.',
        'maintenance_denied' => 'Your role has no access to maintenance. Please report a repair if a device has a problem.',
        'human' => 'There is no live agent. Please report a repair for device problems, or contact the administrator about accounts.',
    ],
    // 判斷問題類型的關鍵字。
    'keywords' => [
        'greeting' => ['你好', '您好', '哈囉', '嗨', 'hello', 'hi', '早安', '午安'],
        'progress' => ['進度', '我的報修', '報修狀態', '修好了嗎', '修好沒', '修到哪', '處理到哪', 'progress', 'my repair', 'my repairs', 'repair status'],
        'repair' => ['怎麼報修', '如何報修', '要報修', '想報修', '報修流程', 'how to report', 'report a repair', 'new repair'],
        'reservation' => ['借教室', '借用教室', '預約', '空間申請', '申請教室', 'reserve', 'reservation', 'book a room'],
        'password' => ['密碼', 'password'],
        'accept' => ['驗收', '退回重修', 'accept'],
        'maintenance' => ['保養', 'maintenance'],
        'human' => ['真人', '人工客服', '聯絡客服', '客服電話', 'human', 'real person'],
    ],
    // 一開始建議的問題。
    'suggestions' => ['My repair progress', 'How to report a repair', 'Reservation', 'Change password'],
];
