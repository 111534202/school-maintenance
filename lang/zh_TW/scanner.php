<?php

// 相機掃描元件（resources/views/scanner/_camera.blade.php）的文字。
return [
    'title' => '掃描 QR／條碼',
    'hint' => '把 QR 或條碼對準畫面，距離約一個手掌，等對焦後會自動讀取；太小可以用下面的滑桿放大。',
    'loading' => '正在啟動相機與掃描功能…',
    'zoom' => '放大倍率',
    'zoom_in' => '放大',
    'zoom_out' => '縮小',
    'torch' => '手電筒',
    'from_photo' => '拍照／選照片掃描',
    'photo_reading' => '正在讀取照片…',
    'photo_not_found' => '照片裡找不到 QR 或條碼，請靠近一點、讓條碼塞滿畫面再拍一次。',
    'insecure' => '瀏覽器只允許在 HTTPS 或 localhost 開啟相機，請改用 HTTPS 網址，或直接在欄位輸入。',
    'insecure_photo' => '目前網址不是 HTTPS，瀏覽器不允許即時開鏡頭。請按下面的「拍照／選照片掃描」，用手機相機拍下條碼或 QR（靠近一點、塞滿畫面），系統會從照片裡讀取。',
    'camera_error' => '無法開啟相機，請確認已允許瀏覽器使用相機，或直接在欄位輸入。',
    'library_error' => '掃描元件載入失敗（可能是網路問題），請直接在欄位輸入。',
    'button' => '用相機掃描',
];
