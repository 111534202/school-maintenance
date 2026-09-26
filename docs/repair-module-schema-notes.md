# feature/repair 模組 — 資料表欄位與關聯註記

負責人：彭仕衡（111534205）｜隨每週進度更新。
2026-09-22 起改依《五週壓縮完工版 v2.0》個人工作計畫執行，取代舊版四週排程；
以下內容已對齊新排程的第 1、2 週。

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
| timestamps | | |

### 狀態機（`App\Services\RepairRequestWorkflow`，Week2 新增）

```
新報修(pending) → 已派工(assigned) → 處理中(in_progress) → 待驗收(pending_review) → 已結案(completed)
                                                         └──（Week1 決策#3：驗收不通過）──> 處理中(in_progress)
```

所有轉換規則集中在 `App\Services\RepairRequestWorkflow`，Controller 一律呼叫這個 service，
不直接改 `status` 欄位。非法轉換會丟出 `DomainException`。

## 3. repair_logs（正式表，Week2 加上填單 UI + 附件）

| 欄位 | 型別 | 說明 |
|---|---|---|
| id | bigint | 主鍵 |
| repair_request_id | foreignId | **正式外鍵** -> repair_requests.id（自己負責的表，可直接加外鍵） |
| cause | text | 故障原因說明 |
| resolution | text | 處置方式 |
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

只允許 jpg/jpeg/png/pdf，單檔 5MB、單次最多 5 個檔案（`App\Services\AttachmentUploader`
統一處理上傳邏輯，兩個 Controller 共用，不各寫一份）。

## 5. 設備狀態同步（`App\Services\DeviceStatusSync`，Week2，暫時為空介面）

devices 表尚未合併進本專案，`RepairRequestWorkflow` 在轉入「處理中」與轉入「待驗收/已結案」
時會呼叫 `DeviceStatusSync` 的對應方法，但目前內部只寫 log、不做任何資料庫寫入——**不建立
假的 devices 表**。等 devices 表真的合併後，把這兩個方法換成真正的 `Device::find(...)->update(...)`
即可，呼叫端不需要再改。提案中的規則（待跟林政寬確認，見下方待確認②）：
- 案件進入「處理中」時，若該設備為核心設備，標記設備狀態為「維修中」。
- 案件進入「待驗收」或「已結案」時，若該設備沒有其他進行中的案件，標記設備狀態改回「正常」。

## 待確認事項（不自行寫死，等規格/跨模組資料表確認後再定案）

1. `knowledge_base.category` 是否應改為關聯 `device_categories` 表，目前先用自由文字欄位。
2. `created_by` / `reporter_id` / `assigned_to` 都應該是 `users.id` 的外鍵，但這個暫存專案裡還沒有正式合併
   林政寬的 `users`/角色權限成果，先以純欄位記錄 id，不加 `->constrained()`。等共用 Repository 合併、
   `users` 表穩定後，補一支新的 migration 加上外鍵約束。同時要跟林政寬確認設備狀態枚舉值，
   才能把 `DeviceStatusSync` 的兩個方法從「只寫 log」換成真正寫入。
3. `repair_requests.impact_level` 目前列舉值僅為草案（low/medium/high），需與其他組員對齊全系統的分級慣例。
4. `repair_requests.status` 五個狀態值與轉換規則（含驗收退回規則）已由彭仕衡在自己模組範圍內拍板，
   詳見上方「狀態機」；跟其他模組的介接方式（例如維修完成率統計公式：以 `repair_logs.total_hours`
   計算平均維修時間）也已先訂一版，供全組討論用。
5. `repair_requests.device_id` / `device_note` 需等林政寬的 `devices` 表真正合併進本專案後，把 `device_note`
   文字輸入換成 `device_id` 下拉選單 + `exists` 驗證，並補上外鍵約束。
6. `repair_requests.location` 是否直接關聯 `classrooms`，或維持自由文字，待該表合併後再確認。
7. 「人工派工」原規劃要記錄派工事件到 `audit_logs`，但這張表屬於林政寬的系統基礎模組，尚未合併，
   本週先不建立（避免建立假的 audit_logs 替代表），改用 `repair_requests` 自己的 `updated_at` 與
   Week3 後續會有的維修紀錄當替代審計軌跡；待 `audit_logs` 合併後再補寫入。

## 本週（Week2）明確不做（依規格排除）

- 附件刪除、正式檔案管理 UI（只做上傳與查看）
- 驗收/退回結案的實際操作介面（狀態機已支援轉換，但驗收頁面規劃在 Week3）
- 備品扣庫存串接
- 完整 QR → 自助排除 → 報修 → 派工 → 維修 → 驗收全流程
