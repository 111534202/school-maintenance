// 載入 axios（發送網路請求的函式庫）並設定成預設帶上 X-Requested-With 標頭，讓後端知道這是 AJAX 請求。
// （目前頁面裡的查詢是直接用瀏覽器內建的 fetch，沒有用到 axios；見 app.js 的說明。）
import axios from 'axios';
// 把 axios 掛到全域的 window 上，其他程式可以直接用 window.axios。
window.axios = axios;

// 所有 axios 請求預設都帶上這個標頭。
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
