# 瀏覽器端到端測試（E2E）

用真的 Chrome 照使用者的操作方式，把主要流程從頭到尾走一遍：登入與權限、報修（教師 → 主管 → 維修人員 → 驗收）、
空間預約、保養工單、批量匯入設備與用戶、QR 貼紙、寄信設定、離線可用、手機版面、安全標頭。

和 `php artisan test`（單元／功能測試）的差別：後者不開瀏覽器，跑得快，檢查程式邏輯；
這裡開瀏覽器，檢查「畫面上看得到、點得到」的結果（按鈕、彈出視窗、下拉選單、JavaScript 是否出錯），比較慢。

## 第一次使用（只需一次）

```bash
pip install playwright
playwright install chromium     # 電腦有 Google Chrome 的話可以省略，測試會優先用 Chrome
```

另外需要：PHP 8.4、MySQL 已啟動、專案的 `.env` 資料庫帳密已設定（和平常開發一樣）。

## 執行

在專案根目錄：

```bash
python tests/e2e/run.py                  # 全部情境
python tests/e2e/run.py --only repair    # 只跑名稱含 repair 的情境
python tests/e2e/run.py --headed         # 顯示瀏覽器視窗，看著它操作
python tests/e2e/run.py --keep-db        # 跑完不刪測試資料庫（方便自己去看資料）
```

它會自動：建立專用的測試資料庫 `school_maintenance_e2e` → 建好資料表與示範資料 → 啟動只給測試用的網站（預設 8765 埠）
→ 逐一執行情境 → 關閉網站並刪除測試資料庫。**不會碰你平常開發用的資料庫**
（資料庫名稱一定要以 `_e2e` 結尾，而且不能是 `.env` 裡設定的那個，`tests/e2e/db.php` 會擋）。
有任何情境失敗，結束代碼是 1，並印出失敗原因。

## 檔案

| 檔案 | 用途 |
|---|---|
| `run.py` | 執行器：建資料庫、啟動網站、跑情境、清理 |
| `scenarios.py` | 所有情境。每個情境是一個函式，用 `@scenario` 登記，用 `check()` 檢查結果 |
| `db.php` | 建立／刪除測試資料庫的小工具（內建防誤刪） |

## 新增一個情境

在 `scenarios.py` 最後面加：

```python
@scenario
def my_new_flow(browser, base, root):
    """一句話說明這個情境在測什麼"""
    with session(browser, base) as (page, errors):
        login(page, 'teacher')                      # 示範帳號：admin、it_manager、repairer、teacher、executive（密碼 password）
        goto(page, '/repairs/create')
        page.fill('input[name="title"]', '標題')
        click_and_wait(page, 'button:has-text("送出報修")')
        check('已送出' in body(page), '送出後應顯示成功訊息')
        check(not errors['js'], 'JavaScript 錯誤：' + str(errors['js']))
```

## 已知狀況

- Windows 上 PHP 內建網站（`php -S`）一次只處理一個請求，偶爾會讓頁面的 load 事件拖很久（約 19 秒），所以在 Windows 跑會比較慢，
  測試已經用「DOM 好了就繼續」的方式避開，不影響結果。Linux／macOS 會自動開 4 個工作程序，比較快。
- 情境使用示範資料（`php artisan db:seed` 產生的帳號與設備）。如果示範資料改了，情境也要跟著調整。
