# Week2 待確認 — 給林政寬

發起人：彭仕衡（111534205，feature/repair）
日期：2026-09-26

---

## 問題：人工派工要記錄「派工事件」，但 audit_logs 表還沒出現

**背景（白話版）**：Week2 規格要求，主管幫報修單指派維修人員時，要把「這件事發生了」
記一筆到 `audit_logs`（誰在什麼時候做了什麼操作），這樣以後才能查「這張報修單經手過誰」。
`audit_logs` 這張表的設計屬於你的系統基礎模組，我不能自己建一張假的頂替。

**目前我怎麼處理（暫時方案）**：
- 派工這個動作本身，資料庫裡看得到：`repair_requests.assignee_note`（誰）、
  `scheduled_at`（何時要處理）、`updated_at`（何時派工的）。
- 但這只能看到「最新一次派工結果」，看不到「派工被改過幾次、每次是誰改的」這種完整
  歷程，等 `audit_logs` 出現才能補上。

**想請你回答**：
1. `audit_logs` 大概會長什麼樣子？（例如：`user_id`、`action`、`subject_type`、
   `subject_id`、`meta`（JSON）、`created_at` 這種通用格式？）
2. 會不會提供一個共用的寫入方式（例如 `AuditLog::record($user, $action, $subject)`
   這種靜態方法／Service），讓大家都用同一種格式寫，還是要我自己接你的 Model 寫？

**在你回答之前**：我不會自己建 `audit_logs` 表，這塊功能先維持「暫時方案」現狀，
不影響報修主流程正常運作。
