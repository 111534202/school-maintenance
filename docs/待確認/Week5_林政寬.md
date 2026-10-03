# Week5 待確認與異動通知 — 給林政寬

發起人：彭仕衡（111534205，feature/repair）
日期：2026-10-04

這份文件有兩個目的：（1）列出仍需要你或全組確認、**目前沒有人私自定案**的事項；（2）告知我這週動到你負責的檔案，請合併前看一下。

---

## 一、仍待確認的事項（目前程式沒有擅自決定）

### 1. 設備狀態何時自動切換（統整表責任人：林政寬）

報修案件進入「處理中」時，是否要自動把核心設備標成「維修中」？進入「待驗收／已結案」且沒有其他進行中案件時，是否改回「正常」？
我的提案見 `docs/repair-module-schema-notes.md` 第 5 節與 `docs/待確認/Week4_林政寬.md`。

**現況**：`App\Services\DeviceStatusSync` 仍然只寫日誌、不改設備資料，因為這條規則在統整表中仍是「待全組／指導老師確認」，
依規定未確認前只預留介面。你確認後，把那個類別的兩個方法改成呼叫你的 `DeviceStatusService::updateStatus()` 即可，
呼叫端（`RepairRequestWorkflow`）不用改。

### 2. 各角色「資料範圍」的細分規則（統整表責任人：林政寬）

規格書的角色說明（3.1.5）寫「維修人員檢視被指派工單」「一般操作員查看個人相關單據」「部門主管檢視部門設備與工單」，
但沒有寫更細的規則。我暫時用下面的做法（集中在 `App\Policies\RepairRequestPolicy`，要改只動這一個檔案）：

- 檢視報修單：能派工的人與管理員看全部；其他人只看自己報修的或指派給自己的。
- 開始處理、填維修紀錄：只有被指派的維修人員本人。
- 驗收通過或退回：只有報修人本人；沒有報修人的案件由有驗收權限的人處理。

**需要確認**：是否照這個規則？「部門主管檢視部門工單」要不要依部門限制範圍（目前沒有做部門隔離，部門主管＝有派工權限的人看全部）？
**確認前**這只是工程保留的做法，不視為已定案。

### 3. 帳號管理畫面是否列為正式功能（統整表責任人：林政寬）

用戶、身分、部門主檔（新增、停用、重設密碼、勾選權限）已經做了，規格書 3.1.4 有用戶主檔的欄位與防呆規則；
統整表仍把「完整管理 UI 是否列正式功能」列為待確認，請你確認範圍。

---

## 二、我這週動到你負責的檔案（請合併前確認）

| 檔案 | 異動 | 原因 |
|---|---|---|
| `app/Http/Controllers/ClassroomController.php` | 修正「啟用中」篩選：`$request->string('is_active') === '1'` 改成先 `->toString()` 再比較；訊息改走翻譯檔 | 原本物件跟字串用 `===` 永遠是 false，選「啟用中」反而列出已停用的教室（測試發現） |
| `app/Http/Controllers/DeviceController.php` | 設備換教室時，新教室也重新計算異常標記；訊息改走翻譯檔 | `DeviceStatusService::syncClassroom` 的說明寫「換教室時都要呼叫」，但原本只重算舊教室，壞掉的核心設備搬過去後新教室不會被標成異常（測試發現） |
| `app/Http/Controllers/DeviceCategoryController.php` | 訊息改走翻譯檔 | 規格書 3.1.2：UI 文字需讀取多語系配置檔 |
| `resources/views/classrooms/*`、`devices/*`、`device-categories/*` | 文字改走翻譯檔；按鈕改成圖示；篩選列改單行；樣式對齊其他主檔頁；加表單欄位錯誤顯示 | 同上，並與全組共用版面風格一致。**欄位名稱、路由、行為都沒有改** |
| `lang/{zh_TW,en}/classrooms.php`、`devices.php`、`device_categories.php` | 新增（devices.php 原本只有狀態名稱） | 同上 |
| `tests/Feature/ClassroomManagementTest.php`、`DeviceManagementTest.php`、`DeviceCategoryManagementTest.php`、`tests/Unit/DeviceStatusServiceTest.php` | 新增 | 這幾個主檔原本沒有任何測試 |
| `.env.example` | `DB_CONNECTION` 由 sqlite 改成 mysql（密碼留空），並加上說明 | 統一環境規定四人各用本機 MySQL |
| `app/Http/Middleware/EnsureUserIsActive.php`、`bootstrap/app.php`、`routes/web.php` | 新增「帳號必須啟用中」檢查，套用在登入後的所有路由 | 規格書 3.1.4：停用帳號不得登入；原本靠「記住我」cookie 仍可重新登入 |
| `app/Http/Controllers/Auth/LoginController.php` | 加登入失敗次數限制；登入失敗紀錄不再記錄不存在的帳號原文 | 防止被無限次猜密碼；避免把誤打進帳號欄位的密碼寫進操作紀錄 |
| `CheckRole` 中介層、Vite／Tailwind 相關檔案 | **沒有動** | 目前沒有地方使用，但是你的程式，請你決定要不要保留 |

附件相關：附件改存私有磁碟、改由 `GET /attachments/{id}` 先檢查權限再下載（規格書 2.2：資料需具備權限隔離），
包含一支 migration 把舊檔案搬過去。每個人拉到新程式後請執行一次 `php artisan migrate`。
另外 `composer.lock` 只更新了 `league/commonmark` 2.10.1 → 2.10.3（修補 2 項安全公告），拉下來後請執行 `composer install`。
