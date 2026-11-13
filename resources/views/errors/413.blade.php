{{-- 送出的資料太大（通常是上傳的檔案超過伺服器的上限，PHP 的 post_max_size）。
     Laravel 遇到 413 錯誤會自動顯示這一頁；正常情況下檔案欄位在送出前就會先跳出提示（見 layouts/app.blade.php 底部），不會走到這裡。 --}}
@php
    $limit = \App\Support\UploadLimit::human(\App\Support\UploadLimit::postBytes());
@endphp
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>上傳的檔案太大</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #F7F5F0; color: #2B2A26;
               font-family: 'Noto Sans TC', system-ui, -apple-system, 'Microsoft JhengHei', sans-serif; padding: 16px; }
        .box { max-width: 460px; background: #fff; border: 1px solid #E7E1D6; border-radius: 18px; padding: 28px 32px; }
        h1 { font-size: 1.35rem; margin: 0 0 12px; }
        p { margin: 0 0 12px; line-height: 1.7; }
        button { border: 0; border-radius: 999px; background: #2F6B4F; color: #fff; padding: 8px 20px; font-size: 1rem; cursor: pointer; }
    </style>
</head>
<body>
    <div class="box">
        <h1>上傳的檔案太大</h1>
        <p>這次送出的檔案總共超過伺服器允許的 {{ $limit }}，所以沒有儲存。</p>
        <p>請回到上一頁，改選較小的檔案（例如把照片縮小）後再送出一次。</p>
        <button type="button" onclick="history.back()">回到上一頁重新選擇</button>
    </div>
</body>
</html>
