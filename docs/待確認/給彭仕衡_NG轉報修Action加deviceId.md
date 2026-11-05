# 需求：`CreateRepairRequestFromMaintenanceNg` 新增 `deviceId` 參數

> 提出者：王佑恩（保養／AI 預測性維護／設備履歷）　對象：彭仕衡（報修／維修模組）
> 這份文件寫給負責該 Action 的人（或他的 Claude）看，內容自成一體，不需要額外背景。

## 一、為什麼要改

王佑恩的保養模組在「保養結果 = NG」時，會呼叫你提供的 Action 自動建立報修單。
目前 Action 沒有「設備」參數，所以建出來的報修單 `device_id` 是空的，只有文字欄位 `device_note`。

這會造成：**設備履歷頁（以設備為中心的彙總頁）用 `repair_requests.device_id` 撈這台設備的報修紀錄，
撈不到剛建立的 NG 報修單。**

現在王佑恩那邊的 workaround（`app/Services/NgToRepairService.php`）是：Action 建完單之後，
再回頭 `$repairRequest->device_id = $device->id; save()` 補一次。能用，但是：

- 同一張單被寫兩次（第一次 `device_id` 是空的，中間有一瞬間資料不完整）。
- 呼叫端要自己知道「這個 Action 沒帶設備」這個缺陷，語意不乾淨。

所以請在 Action 加一個選用參數，讓建單當下就帶上設備。

## 二、要改什麼（只改 Action，其餘不用動）

檔案：`app/Actions/CreateRepairRequestFromMaintenanceNg.php`

1. `execute()` 新增**選用**參數 `?int $deviceId = null`，放在最後面（放最後，現有的具名參數呼叫才不會壞）：

   ```php
   public function execute(
       string $sourceLabel,
       string $title,
       string $description,
       ?string $deviceNote = null,
       string $impactLevel = 'medium',
       ?int $deviceId = null,          // ← 新增
   ): RepairRequest {
   ```

2. `RepairRequest::create([...])` 裡加上 `'device_id' => $deviceId,`。
   （`device_id` 已在 `RepairRequest::$fillable` 裡，且是有外鍵約束的正式欄位，不需要新 migration。）

3. 更新檔頭註解的「使用方式」範例，補上 `deviceId: $device->id,`，並說明：
   - 傳了 `deviceId` 就掛上設備關聯（設備履歷、設備入口頁才找得到這張單）；
   - 沒傳就維持原行為（`device_id` 為空，只有 `device_note` 文字）。

## 三、相容性要求（很重要）

- **不能破壞現有呼叫**：沒傳 `deviceId` 時，行為必須和現在一模一樣。
- 參數名稱請用 `deviceId`（王佑恩那邊會用具名參數 `deviceId:` 呼叫）。
- 型別是 `?int`；呼叫端會傳設備主檔的 `devices.id`（不是 `device_code`）。
- 不需要驗證設備是否存在：外鍵約束會擋掉不存在的 id（呼叫端傳的一律來自真實設備紀錄）。

## 四、測試

檔案：`tests/Unit/CreateRepairRequestFromMaintenanceNgTest.php`（現有一個測試，照樣保留）。請再加兩個：

1. **有帶 `deviceId`**：先建一台設備（用 factory 或 `Device::create`，欄位需符合 `devices` 表規則），
   呼叫 `execute(..., deviceId: $device->id)`，斷言
   `$repairRequest->device_id === $device->id`，且 `assertDatabaseHas('repair_requests', ['id' => ..., 'device_id' => $device->id])`。
2. **沒帶 `deviceId`**：斷言 `$repairRequest->device_id` 為 `null`（確認向下相容）。

完成後請執行 `php artisan test --compact`，確認全部通過（目前整套是 296 passed、1 skipped）。

## 五、改完之後王佑恩這邊要做的事（不用你處理，只是讓你知道）

- 他會把 `NgToRepairService` 裡「建完再補 `device_id`」的那段改成直接傳 `deviceId: $device->id`。
- 在你改好之前，他現有的 workaround 仍然正常運作（程式裡有 `if ($repairRequest->device_id === null)` 判斷，
  Action 已經帶好 `device_id` 時不會重複寫入），所以**兩邊誰先改都不會壞**，不需要同時上線。

## 六、不要動的東西

- 不要改 `RepairRequestStatus`、狀態流程、`RepairRequestPolicy`、`visibleTo()`。
- 不要新增資料表或欄位。
- 不要改 `sourceLabel` 寫進描述的格式（`【保養 NG 自動轉入，來源：maintenance_result:123】`）——
  保養模組的追溯功能會用到它。
