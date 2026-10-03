// Vite（前端打包工具）的設定：指定要編譯的入口檔（css 與 js），並啟用 Laravel 與 Tailwind 外掛。
// 目前網站版面沒有使用編譯後的檔案，見 resources/js/app.js 的說明；若將來要啟用，開發時執行 npm run dev，上線前執行 npm run build。
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // 要編譯的入口檔。
            input: ['resources/css/app.css', 'resources/js/app.js'],
            // 存檔時自動重新整理瀏覽器（開發用）。
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            // 不監看 Blade 編譯後的暫存檔，避免每次渲染頁面都觸發重新整理。
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
