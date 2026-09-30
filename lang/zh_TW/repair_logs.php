<?php

// i18n：維修填單模組。修改這個檔案時，記得同步修改 lang/en/repair_logs.php，
// 兩邊的 key 必須完全一致。
return [
    'create_title' => '填寫維修紀錄',
    'case_line' => '案件：:title（:location）',
    'not_filled_location' => '未填寫地點',

    'form' => [
        'cause' => '故障原因說明',
        'resolution' => '處置方式',
        'started_at' => '開始時間',
        'ended_at' => '結束時間',
        'parts_used_note' => '使用備品說明（選填，例如：更換投影機燈泡 x1）',
        'parts_used_note_hint' => '備品主檔尚未合併進來，暫時用文字描述；之後會改成選單並自動扣庫存。',
        'attachments' => '維修前後照片／影片（選填，最多 5 個檔案，jpg/png/pdf/mp4/mov/webm，單檔 20MB 以內）',
    ],

    'submit' => '送出維修紀錄（送出後案件進入待驗收）',

    'flash' => [
        'submitted' => '維修紀錄已送出，案件已進入待驗收。',
    ],
];
