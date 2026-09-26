# Week3 待確認 — 給王佑恩

發起人：彭仕衡（111534205，feature/repair）
日期：2026-09-26

---

## 我已經先做好一個「保養 NG → 自動建立報修單」的入口，麻煩你接看看

**背景（白話版）**：規格要求，你的保養模組如果檢查出「這台設備 NG（不合格/需要維修）」，
要能自動幫使用者建立一張報修單，不用使用者自己重新打一次報修表單。這個「建立報修單」
的動作屬於我的模組，所以我先把入口做好，你只要呼叫它就行，不用等我。

**我做好的東西**：`App\Actions\CreateRepairRequestFromMaintenanceNg`

用法（在你的程式碼裡呼叫）：

```php
use App\Actions\CreateRepairRequestFromMaintenanceNg;

app(CreateRepairRequestFromMaintenanceNg::class)->execute(
    sourceLabel: 'maintenance_result:123',   // 隨便一個能追溯回你那筆 NG 紀錄的字串就好
    title: '投影機保養檢查 NG：燈泡亮度不足',
    description: '定期保養檢查時發現燈泡亮度低於標準值，建議更換。',
    deviceNote: 'A101 教室投影機',            // 設備文字描述（devices 表合併前的暫時做法）
    impactLevel: 'medium',                    // low / medium / high
);
```

呼叫完會回傳一個 `RepairRequest`，狀態會是 `pending`（新報修），跟一般人工報修完全一樣，
會出現在維修案件看板上，主管可以直接派工。

**這個做法你可能會問的問題**：
- **為什麼不是直接建外鍵關聯到你的 `maintenance_results` 表？** 因為那張表現在還沒合併
  進 `develop`，先不建立假的關聯；`sourceLabel` 先用一個字串記錄「這張報修單是哪個保養
  結果轉來的」，等你的表穩定後，我們可以再一起把它換成正式的 `maintenance_result_id` 外鍵。

**想請你回答**：
1. 你那邊的 NG 結果大概會有哪些欄位？（設備、檢查項目、NG 原因……）方便我確認上面
   `title`/`description` 這兩個要不要再細分。
2. 呼叫這個 Action 的時機，是你打算在 Controller 裡直接呼叫，還是想要一個 Job/事件
   （event）的方式來呼叫？如果要用事件，跟我說一下事件名稱，我可以幫忙註冊監聽器。
