# feature/repair 模組 — 資料表欄位與關聯註記

負責人：彭仕衡（111534205）｜隨每週進度更新。
2026-09-22 起改依《五週壓縮完工版 v2.0》個人工作計畫執行，取代舊版四週排程；
以下內容已對齊新排程的第 1～3 週。

**跨組待確認事項**：所有需要其他組員配合的資料/介面，都拆成一週一人一個檔案放在
`docs/待確認/` 資料夾（例如 `Week3_劉家芸.md`），方便直接傳給對方看、對方回覆後
也方便追蹤。這份文件只記錄「已經確定」的設計，尚未確定的都在那個資料夾裡。

本模組共四張表：`knowledge_base`、`repair_requests`、`repair_logs`、`attachments`（附件共用表）。

## 1. knowledge_base（正式表，含最小導流）

| 欄位 | 型別 | 說明 |
|---|---|---|
| id | bigint | 主鍵 |
| title | string | 標題 |
| category | string, nullable | 分類（目前為自由文字，見待確認①） |
| symptom | text | 常見故障現象 |
| solution | text | 自助排除步驟 |
| is_published | boolean | 是否上架顯示給使用者 |
| created_by | unsignedBigInteger, nullable | 建立者，見待確認② |
| timestamps | | |

show 頁提供「問題已解決」／「無法排除，前往報修」兩個按鈕，「前往報修」會把 `from_kb`
帶到 `repair-requests.create`，只做導流與描述欄位預先帶入文字，不做故障類型推薦。

## 2. repair_requests（正式版本，欄位仍不含正式外鍵）

| 欄位 | 型別 | 說明 |
|---|---|---|
| id | bigint | 主鍵 |
| title | string | 報修單簡短標題 |
| description | text | 故障描述 |
| impact_level | enum(low/medium/high), default medium | 影響程度，見待確認③ |
| affects_class | boolean, default false | 是否影響上課 |
| status | string, cast 成 `App\Enums\RepairRequestStatus` | 案件狀態，見下方「狀態機」 |
| reporter_id | unsignedBigInteger, nullable | 報修人，見待確認② |
| device_id | unsignedBigInteger, nullable | 設備，見待確認⑤ |
| device_note | string, nullable | devices 表合併前，暫時用文字描述設備 |
| assigned_to | unsignedBigInteger, nullable | 維修人員，見待確認② |
| assignee_note | string, nullable | users 表合併前，暫時用文字記錄維修人員（Week2 新增） |
| scheduled_at | timestamp, nullable | 預計處理日期（Week2 新增） |
| location | string, nullable | 地點，見待確認⑥ |
| rejection_reason | text, nullable | 驗收不通過退回時的原因，只保留最新一次（Week3 新增） |
| timestamps | | |

### 狀態機（`App\Services\RepairRequestWorkflow`）

```
新報修(pending) → 已派工(assigned) → 處理中(in_progress) → 待驗收(pending_review) → 已結案(completed)
                                                         └──（Week1 決策#3：驗收不通過）──> 處理中(in_progress)
```

所有轉換規則集中在 `App\Services\RepairRequestWorkflow`，Controller 一律呼叫這個 service，
不直接改 `status` 欄位。非法轉換會丟出 `DomainException`。Week3 補上 `complete()`（驗收通過結案）
與 `reject()`（驗收不通過退回，需附退回原因）兩個方法，對應 `repair-requests.complete`/
`repair-requests.reject` 這兩個路由。Week4 補上 `reassign()`（重新指派，只換
`assignee_note`/`scheduled_at`，不屬於狀態轉換，只允許在「已派工」「處理中」時使用）。

## 3. repair_logs（正式表，Week2 加上填單 UI + 附件）

| 欄位 | 型別 | 說明 |
|---|---|---|
| id | bigint | 主鍵 |
| repair_request_id | foreignId | **正式外鍵** -> repair_requests.id（自己負責的表，可直接加外鍵） |
| cause | text | 故障原因說明 |
| resolution | text | 處置方式 |
| parts_used_note | string, nullable | 使用備品說明（Week4 新增，文字暫代，待劉家芸 InventoryService 介面確認後改正式關聯+扣庫存） |
| started_at | timestamp, nullable | 處理起始時間 |
| ended_at | timestamp, nullable | 處理結束時間 |
| total_hours | decimal(5,2), nullable | 總工時（小時）＝ (ended_at - started_at) / 3600，用時間戳相減算，
不要用 Carbon 的 `diffInMinutes()`（Carbon 3.x 預設回傳有號數，方向沒對齊會算出負值，實測踩過） |
| timestamps | | |

填單頁（`repair-logs.create`/`store`）送出後會自動把所屬 `repair_requests` 從「處理中」
推進到「待驗收」（呼叫 `RepairRequestWorkflow::submitForReview()`）。

## 4. attachments（Week2 新增，多型共用表）

| 欄位 | 型別 | 說明 |
|---|---|---|
| id | bigint | 主鍵 |
| attachable_type / attachable_id | morphs | 多型關聯，目前被 `repair_requests`（報修附件）與 `repair_logs`（維修前後照片）共用 |
| disk_path | string | 存放路徑（`storage/app/public/attachments`，透過 `php artisan storage:link` 對外） |
| original_name | string | 原始檔名 |
| mime_type | string | MIME type |
| size_bytes | unsignedBigInteger | 檔案大小 |
| timestamps | | |

允許 jpg/jpeg/png/pdf/mp4/mov/webm（Week4 新增影片格式，對應「故障照片/影片」規格），
單檔 20MB、單次最多 5 個檔案（`App\Services\AttachmentUploader` 統一處理上傳邏輯，
兩個 Controller 共用，不各寫一份）。

## 5. 設備狀態同步（`App\Services\DeviceStatusSync`，Week2，暫時為空介面）

devices 表尚未合併進本專案，`RepairRequestWorkflow` 在轉入「處理中」與轉入「待驗收/已結案」
時會呼叫 `DeviceStatusSync` 的對應方法，但目前內部只寫 log、不做任何資料庫寫入——**不建立
假的 devices 表**。等 devices 表真的合併後，把這兩個方法換成真正的 `Device::find(...)->update(...)`
即可，呼叫端不需要再改。提案中的規則（待跟林政寬確認，見下方待確認②）：
- 案件進入「處理中」時，若該設備為核心設備，標記設備狀態為「維修中」。
- 案件進入「待驗收」或「已結案」時，若該設備沒有其他進行中的案件，標記設備狀態改回「正常」。

## 6. 看板資訊補強（Week3）

`repair-requests.index`（維修案件看板）新增：
- 每個維修人員（`assignee_note`）手上還有幾張「未結案」的案件，方便主管判斷（只顯示
  客觀數字，不做自動派工推薦，依規格「未確認規則不做自動推薦」）。
- 每張案件「等待多久」（`created_at->diffForHumans()`）。

## 7. 保養 NG 轉報修接口（`App\Actions\CreateRepairRequestFromMaintenanceNg`，Week3）

提供給王佑恩（保養/AI 模組）呼叫的穩定介面，讓保養檢查 NG 可以直接建立一張報修單。
因為 `maintenance_results` 表還沒合併，暫時用 `sourceLabel` 字串記錄來源，不建外鍵。
詳細用法見 `docs/待確認/Week3_王佑恩.md`。

## 9. devices/users 表正式合併後的變更（額外需求追加：資產報修 + 派工通知信）

`develop` 合併了林政寬的 `feature/auth-device`（devices/users/roles/classrooms/audit_logs 都是
真正的表了），所以把待確認②⑤原本「先用文字欄位頂著」的部分正式補上：

- `repair_requests.device_id` / `reporter_id` / `assigned_to` 都補上真正外鍵
  （`2026_09_30_064236_add_foreign_keys_to_repair_requests_table.php`），`RepairRequest` model
  新增 `device()`／`reporter()`／`assignedTechnician()` 三個 `belongsTo()`。`device_note` /
  `assignee_note` 兩個文字欄位保留當後備顯示（沒有對應真實設備/帳號時，例如舊資料、保養 NG
  轉報修尚未掃碼建立設備關聯的案件）。
- **資產報修（簡化流程）**：報修表單新增「設備條碼」輸入框，掃描（條碼掃描器對電腦來說就是
  鍵盤輸入+Enter，不需要相機或解碼函式庫）或手動輸入設備編號後，用
  `GET repairs/device-lookup/{device_code}`（`RepairRequestController::deviceLookup()`）即時查
  詢，前端 JS 自動帶入設備名稱/型號/教室與報修標題建議，不用再手動描述設備。也支援林政寬
  `DeviceEntryController`（QR 掃描設備進入頁）「前往報修」按鈕直接帶 `?device={id}` 過來預填。
- **派工信件通知**：`RepairRequestController::assign()`/`reassign()` 呼叫
  `App\Services\RepairAssignmentNotifier`，寄送 `App\Mail\RepairDispatchedMail` 給被指派的
  維修人員（`to`），並副本（`cc`）給所有 `it_manager` 角色（決定「設備管理員」= `it_manager`，
  這個角色字面上沒有寫「設備管理員」但語意最接近）。目前 `.env` 是 `MAIL_MAILER=log`（寫進
  `storage/logs/laravel.log`，不會真的寄出），之後要接上真的 SMTP 只需要改 `.env`，
  Mailable／Notifier 完全不用改。
- 派工／重新指派表單改成挑選真正的 `users`（角色 = `technician`）下拉選單，不再讓主管自己
  打字輸入姓名（`AssignRepairRequestRequest` 用 `Rule::exists('users','id')->where('role_id', ...)`
  驗證）。
- 因為登入系統合併了，`knowledge-base`/`repairs` 系列路由全部搬進 `Route::middleware('auth')`
  群組裡，`reporter_id` 現在直接用 `Auth::id()`，不再是 null。
- 路由名稱從 `repair-requests.*` 改成 `repairs.*`，對齊林政寬 `layouts/partials/nav-links.blade.php`
  （用 `Route::has('repairs.index')` 判斷要不要顯示報修連結）與 `DeviceEntryController` 的命名。

## 待確認事項（不自行寫死，等規格/跨模組資料表確認後再定案）

1. `knowledge_base.category` 是否應改為關聯 `device_categories` 表，目前先用自由文字欄位。
2. ~~`created_by` / `reporter_id` / `assigned_to` 都應該是 `users.id` 的外鍵~~ —— **已完成**，見上方第 9 節。
   `KnowledgeBaseController` 的 `created_by` 尚未補（知識庫文章目前沒有作者欄位需求，暫不處理）。
3. `repair_requests.impact_level` 目前列舉值僅為草案（low/medium/high），需與其他組員對齊全系統的分級慣例。
4. `repair_requests.status` 五個狀態值與轉換規則（含驗收退回規則）已由彭仕衡在自己模組範圍內拍板，
   詳見上方「狀態機」；跟其他模組的介接方式（例如維修完成率統計公式：以 `repair_logs.total_hours`
   計算平均維修時間）也已先訂一版，供全組討論用。
5. ~~`repair_requests.device_id` / `device_note` 需等林政寬的 `devices` 表真正合併~~ —— **已完成**，見上方第 9 節。
6. `repair_requests.location` 是否直接關聯 `classrooms`，或維持自由文字，待該表合併後再確認。
7. 「人工派工」原規劃要記錄派工事件到 `audit_logs`，但這張表屬於林政寬的系統基礎模組，現在已經
   合併進來了，之後可以評估要不要接上（本次只先做通知信，尚未把派工事件寫進 audit_logs，
   範圍留給下一輪再做）。

## 8. i18n 多語系（中文／英文，額外需求追加）

除了原本五週排程外，追加「支援中英文雙語」的需求。做法：

- 所有畫面文字、驗證錯誤訊息、flash 訊息（`session('status')`/`session('error')`）、
  狀態機丟出的 `DomainException` 訊息，全部改用 Laravel 的 `__()` 翻譯函式，
  對照的翻譯檔放在 `lang/zh_TW/` 與 `lang/en/`（`common.php`、`knowledge_base.php`、
  `repair_requests.php`、`repair_logs.php`、`validation.php`、`pagination.php`）。
- 語言判斷：`App\Http\Middleware\SetLocale` 每次請求時從 session 讀出使用者上次選的
  語言（沒選過就用 `.env` 的 `APP_LOCALE`，目前預設 `zh_TW`），同時呼叫
  `Carbon::setLocale()`，讓「等待多久」這類 `diffForHumans()` 也會跟著換語言，
  不然 Carbon 的語言不會自動跟著 Laravel 的 App 語言走。
- 切換入口：漢堡選單最下面「中文／English」兩個連結，打
  `GET /locale/{locale}` 把選擇存進 session、導回原本那一頁。
- **刻意不翻譯的部分**：使用者自己輸入的內容（報修標題、故障描述、維修備註、
  退回原因等自由文字欄位）與資料庫種子測試資料，這些是「資料」不是「介面」，
  跟其他語言網站的慣例一樣不會被機器翻譯。`CreateRepairRequestFromMaintenanceNg`
  裡自動加在描述最前面的「【保養 NG 自動轉入，來源：...】」標記也一樣：它在
  案件建立當下就寫死存進資料庫，不是每次顯示時即時套用目前語言，所以維持中文，
  跟其他自由文字欄位一致（如果之後要讓這個標記也能跟著語言切換，需要改成另外
  存一個來源類型欄位、顯示時才組字串，不是單純套用 `__()` 就能做到，目前規格
  沒有要求做到這麼細）。

## 明確不做（依規格排除，等跨組介面確認後才做）

- 備品真正扣庫存（等 `docs/待確認/Week3_劉家芸.md`、`Week4_劉家芸.md` 的 InventoryService 介面確認）
- QR Code 掃描帶入設備（等 `docs/待確認/Week3_林政寬.md` 的介面確認）
- 附件刪除、正式檔案管理 UI（只做上傳與查看）
- 自行發明派工負荷量演算法或自動推薦人選（規格明確禁止，只顯示客觀資料）
- 角色權限限制（等林政寬的登入/角色系統合併，目前任何人都能操作所有按鈕）
- 第五週範圍控制：不改狀態模型、不重做 UI、不新增功能（只做回歸測試、RWD 收尾、
  文件整理），本次的漢堡選單/篩選欄位換行修正屬於 RWD 收尾範圍，不算新增功能。
