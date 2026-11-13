{{-- 在畫面中（對話框）送出的表單成功了：通知外面的頁面跳到 $url（成功訊息會在那一頁顯示），對話框跟著關掉。見 App\Http\Middleware\EmbeddedModal。 --}}
<!DOCTYPE html>
<html lang="zh-TW">
<head><meta charset="UTF-8"><title>完成</title></head>
<body>
<script>
    (function () {
        var url = @json($url);
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ type: 'modal-done', url: url }, window.location.origin);
        } else {
            window.location.href = url;
        }
    })();
</script>
</body>
</html>
