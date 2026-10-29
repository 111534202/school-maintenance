// 前端 JavaScript 的總入口（由 Vite 編譯）。這裡只負責載入 bootstrap.js。
// 【請注意】目前網站的版面（resources/views/layouts/app.blade.php）是直接從網路 CDN 載入 Bootstrap 與各頁面自己的小段 JavaScript，
// 並沒有使用這個 Vite 編譯後的檔案（只有 Laravel 預設的歡迎頁 welcome.blade.php 會引用）；這個檔案與 css、vite.config.js 是 Laravel 專案預設附帶、目前保留。
import './bootstrap';
