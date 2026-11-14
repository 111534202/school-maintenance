# public/vendor：放在專案裡的外部套件

畫面用到的外部套件都放在這裡，不從網路載入，所以校內網路擋外網或斷線時畫面與掃描仍可使用。
升級時請換掉對應檔案，並跑 `tests/Feature/VendorAssetsTest.php` 確認沒有漏檔。

| 資料夾 | 內容 | 版本 | 授權 | 來源 |
|---|---|---|---|---|
| bootstrap | 版面樣式與互動（按鈕、表格、彈出視窗） | 5.3.0 | MIT | https://getbootstrap.com |
| bootstrap-icons | 圖示字型 | 1.11.x | MIT | https://icons.getbootstrap.com |
| chartjs | 主控台與主檔頁面的統計圖表 | 4.4.3 | MIT | https://www.chartjs.org |
| barcode-detector | 瀏覽器沒有內建條碼辨識時的備用辨識程式（zxing-wasm 版） | 3.0.8 | MIT | https://github.com/Sec-ant/barcode-detector |
| zxing | 上面那個備用辨識程式的解碼檔（.wasm） | 隨 barcode-detector | Apache-2.0 | https://github.com/Sec-ant/zxing-wasm |

字型沒有下載：網站使用電腦內建的中文字體（Windows 為微軟正黑體與新細明體，Mac 為蘋方與宋體，手機為系統字體），
電腦剛好裝有思源字型時會優先使用。
