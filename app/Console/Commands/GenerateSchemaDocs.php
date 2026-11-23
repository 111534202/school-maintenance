<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 從「實際的資料庫結構」產生一份文件 docs/資料庫命名規範與資料字典.md（命名邏輯、代碼值、例外、正規化結論、
 * 資料表一覽、ERD、完整名稱清單與資料字典、與規格書的差異），確保文件和資料庫永遠一致。
 * 用法：
 *   php artisan docs:schema           重新產生（資料表或欄位改了以後執行）
 *   php artisan docs:schema --check   只檢查文件是不是最新（不寫檔；測試會用它，文件過期就失敗）
 * 連到 MySQL 時，另外會產生／檢查 docs/資料字典.md（實際欄位型態、預設值、外鍵刪除規則與索引，依業務模組分章）。
 * 欄位的中文說明、資料表的中文名稱與正規化結論是這個檔案裡的文字（下面的 $columnText、$tables），新增欄位時請一併補上。
 * 欄位順序固定成「編號 → 關聯 → 一般欄位 → 時間欄位」，欄位型態用「邏輯型態」（整數、文字、布林…），
 * 這樣不管在 MySQL 或測試用的 SQLite 產生出來都一樣。
 *
 * @phpstan-type Physical array{engine: string, collation: string, columns: array<string, array{type: string, nullable: bool, default: ?string, extra: string}>, foreign: list<array{column: string, table: string, ref: string, on_delete: string}>, indexes: array<string, array{unique: bool, columns: list<string>}>}
 * @phpstan-type SchemaMap array<string, array{columns: list<array<string, mixed>>, foreign: array<string, array{table: string, column: string}>}>
 */
class GenerateSchemaDocs extends Command
{
    protected $signature = 'docs:schema {--check : 只檢查文件是不是最新，不寫檔}';

    protected $description = '依實際資料庫結構產生「資料庫命名規範與資料字典」（單一檔案）';

    // Laravel 內建的資料表，不列入文件。
    private const FRAMEWORK_TABLES = ['migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions', 'password_reset_tokens'];

    /**
     * 資料表：中文名稱、對照規格書、正規化檢查結論。
     * 順序就是文件裡的順序。
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    private array $tables = [
        'roles' => ['角色主檔（身分）', '第 7 章 roles', '符合 3NF。勾選的權限原本是 JSON 陣列（違反 1NF），已拆成 role_permissions。'],
        'role_permissions' => ['身分權限', '規格書未列（roles 權限描述的正規化結果）', '關聯表：一列一個權限；(role_id, permission) 唯一，身分刪除時一起刪除。'],
        'departments' => ['單位主檔', '第 7 章 departments', '符合 3NF。'],
        'import_batches' => ['批次匯入紀錄', '規格書未列（新增功能：批次匯入與整批復原）', '符合 3NF。設備與用戶以 import_batch_id 指向這裡，不另存清單；復原只標記 undone_at，不刪紀錄。'],
        'mail_settings' => ['寄信設定', '規格書未列（管理員在網頁設定寄信用的信箱帳號）', '符合 3NF。整張表只會有一筆資料；登入密碼加密後存放，畫面與操作紀錄都不顯示。'],
        'majors' => ['科系主檔', '規格書未列（空間申請的科系／年級／班級下拉選單）', '符合 3NF。每個科系各自設定年級數與每年級班數（五專、大學部共用）；班級名稱（簡稱＋年級＋班名，例如資一忠）由這三個值推得，不另存。'],
        'users' => ['使用者主檔', '第 7 章 users', '符合 3NF。一人一個身分（role_id），所屬單位以 department_id 關聯。'],
        'classrooms' => ['教室主檔', '第 7 章 classrooms', '大致符合 3NF，有兩個有意保留的例外：campus／building／floor 依規格書 4.1 直接存在教室上（同一大樓的多間教室會重複同一段文字）；reservation_status 是由「核心設備狀態」推得的快取旗標，由 DeviceStatusService 維護，避免每次列表都重算（是否保留屬待確認事項）。'],
        'device_categories' => ['設備類別', '第 7 章 device_categories', '符合 3NF。'],
        'devices' => ['設備主檔', '第 7 章 devices', '符合 3NF。類別與教室只存外鍵；brand／model 依規格書是文字欄位。'],
        'maintenance_items' => ['保養項目', '第 7 章 maintenance_items', '符合 3NF。適用設備類別以 device_category_id 關聯（原本是自由文字）。'],
        'maintenance_plans' => ['保養計畫', '第 7 章 maintenance_plans', '符合 3NF。針對「一台設備」或「一類設備」二選一，指定設備後類別由設備推得、不另存。next_due_date 是由 start_date 加週期推得的值，排程要用，所以保留。'],
        'maintenance_plan_items' => ['保養計畫明細', '第 7 章 maintenance_plan_items', '純關聯表（多對多），符合 2NF／3NF。'],
        'maintenance_orders' => ['保養工單', '第 7 章 maintenance_orders', '符合 3NF（設備與類別二選一，理由同保養計畫）。'],
        'maintenance_results' => ['保養結果', '第 7 章 maintenance_results', '符合 3NF。執行人以 executed_by（users.id）關聯，不再存姓名文字。ng_conversion_status 與 repair_request_id 有部分重疊（converted 代表已有報修單），因為還需要區分「待轉」與「不需轉」兩種沒有報修單的情況，所以保留。'],
        'repair_requests' => ['報修單', '第 7 章 repair_requests', '大致符合 3NF。維修人員只存 assigned_to（原本另存 assignee_note 文字，已移除）。device_note 是「沒有綁定設備」時的後備文字。設備地點以 classroom_id 關聯教室（原本自由輸入的 location 文字已移除，舊資料依教室代碼或名稱轉換）；它記的是「報修當時」的地點，設備之後搬到別的教室也不會改變，所以不是由設備推得的重複資料。'],
        'repair_logs' => ['維修紀錄', '第 7 章 repair_logs', '大致符合 3NF。total_hours 是由起訖時間推得的值，規格書要求存起來所以保留；parts_used_note 是自由文字備註，結構化的備品使用已改存 repair_part_usages（舊資料與額外說明仍可寫在這裡）。'],
        'repair_part_usages' => ['維修零件使用明細', '規格書未列（備品使用的結構化）', '符合 3NF。unit_price 是扣料當下那一批採購的單價（先進先出的成本快照），part_purchase_id 記錄扣的是哪一批；一次用料跨兩批時拆成兩列。舊資料沒有批次時 part_purchase_id 為空。'],
        'attachments' => ['附件資料', '第 7 章 attachments', '符合 3NF。用 Laravel 多型關聯（attachable_type + attachable_id）指向報修單、維修紀錄等，不加外鍵。'],
        'audit_logs' => ['操作日誌', '第 7 章 audit_logs', '日誌資料。changes 是 JSON：記錄「當時」的變更內容快照，不需要單獨查詢內容，所以刻意不拆成欄位（1NF 的有意例外）。'],
        'knowledge_base' => ['自助知識庫文章', '規格書未列（工作計畫指定模組）', '符合 3NF。category 是自由文字（沒有獨立的分類表）；值很少、也沒有跨表查詢需求，若日後分類變多再拆成獨立資料表。'],
        'parts' => ['零件庫存', '規格書未列（備品庫存模組）', '大致符合 3NF。part_code 是零件編號，可自訂、可修改（不可重複），未填時自動產生 PART-順序數字；其他資料表以 id 關聯零件，所以改編號不影響紀錄；單價不存在零件上，改存在每一筆採購批次。current_stock 等於各批次剩餘數量的加總，是為了列表與扣料檢查保留的快取值，只能由 PartStockService 更新（進貨、用料），不能手動修改。'],
        'part_purchases' => ['零件採購紀錄', '規格書未列（備品庫存模組：採購與先進先出成本）', '符合 3NF。每一筆是一個進貨批次；總價＝數量×單價，不另存。remaining_quantity 是這一批還沒被用掉的數量，先進先出扣料時遞減。發票以 attachments 多型關聯保存。'],
        'part_scraps' => ['零件報廢紀錄', '規格書未列（備品庫存模組：零件沒用就壞掉、過期或遺失時報廢）', '符合 3NF。一次報廢一筆；扣了哪幾個採購批次與當時單價記在 part_scrap_batches，報廢損失由明細計算，不另存。recorded_by 是登記的人。'],
        'part_scrap_batches' => ['零件報廢扣用批次', '規格書未列（報廢的先進先出明細，與用料明細同結構）', '符合 3NF。一次報廢跨兩個採購批次時拆成兩列，unit_price 是那一批的單價（成本快照）；取消報廢時依這裡把數量加回原批次。'],
        'maintenance_part_usages' => ['保養零件使用明細', '規格書未列（保養用料，與維修用料同結構）', '符合 3NF。unit_price 是扣料當下那一批採購的單價（先進先出的成本快照），一次用料跨兩批時拆成兩列。'],
        'classroom_reservations' => ['教室預約申請', '規格書未列（空間預約模組）', '符合 3NF：使用時間（日期、開始、結束）只記在 classroom_reservation_slots（一張申請可有多個時段），申請本身不另存。原本重複存放的 reservation_date、start_time、end_time 已依方案 A 移除（2026_11_05_010000），見 docs/待確認/預約時間重複儲存_劉家芸.md。'],
        'classroom_reservation_slots' => ['教室預約時段明細', '規格書未列（教室預約課表與多時段預約功能）', '一對多關聯明細表：每筆明細透過 classroom_reservation_id 關聯一筆預約申請；刪除預約申請時，外鍵會級聯刪除所屬時段。status 為這個時段的審核結果（可以逐節審核；申請勾了 is_all_or_nothing 時所有時段結果相同）；空白代表還沒審核（或更早的舊資料，跟著整筆申請），被拒絕時 rejection_reason 記錄原因。'],
        'ai_settings' => ['AI 預防保養參數', '規格書未列（AI 預測性維護）', '「參數名稱＋參數值」的設定表（key-value），參數會隨功能增減，所以刻意不為每個參數開一個欄位。'],
        'preventive_candidates' => ['預防保養候選', '規格書未列（AI 預測性維護）', '符合 3NF。explanation／features 是 JSON：當時評分的快照（日後改規則也要能重現當時結果），有意不拆。'],
    ];

    /**
     * 資料表命名拆解：[類型, 領域詞, 實體詞（複數）]。領域詞是「這張表屬於哪一塊業務」，空白代表核心主檔（單一名詞就夠清楚）。
     * 新增資料表時要在這裡補一列，並把新的領域詞加進 DOMAIN_WORDS（DatabaseNamingConventionTest 也用同一份清單檢查）。
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    private array $tableNaming = [
        'roles' => ['主檔', '', 'roles'],
        'role_permissions' => ['關聯明細', 'role', 'permissions'],
        'departments' => ['主檔', '', 'departments'],
        'mail_settings' => ['主檔', '', 'mail_settings'],
        'import_batches' => ['紀錄', 'import', 'batches'],
        'majors' => ['主檔', '', 'majors'],
        'users' => ['主檔', '', 'users'],
        'classrooms' => ['主檔', '', 'classrooms'],
        'device_categories' => ['主檔', 'device', 'categories'],
        'devices' => ['主檔', '', 'devices'],
        'maintenance_items' => ['主檔', 'maintenance', 'items'],
        'maintenance_plans' => ['主檔', 'maintenance', 'plans'],
        'maintenance_plan_items' => ['關聯明細', 'maintenance_plan', 'items'],
        'maintenance_orders' => ['單據', 'maintenance', 'orders'],
        'maintenance_results' => ['明細', 'maintenance', 'results'],
        'repair_requests' => ['單據', 'repair', 'requests'],
        'repair_logs' => ['紀錄', 'repair', 'logs'],
        'repair_part_usages' => ['明細', 'repair', 'part_usages'],
        'part_purchases' => ['單據', 'part', 'purchases'],
        'maintenance_part_usages' => ['明細', 'maintenance', 'part_usages'],
        'part_scraps' => ['單據', 'part', 'scraps'],
        'part_scrap_batches' => ['明細', 'part', 'scrap_batches'],
        'attachments' => ['附屬（多型）', '', 'attachments'],
        'audit_logs' => ['紀錄', 'audit', 'logs'],
        'knowledge_base' => ['主檔（例外）', '', 'knowledge_base'],
        'parts' => ['主檔', '', 'parts'],
        'classroom_reservations' => ['單據', 'classroom', 'reservations'],
        'classroom_reservation_slots' => ['關聯明細', 'classroom', 'reservation_slots'],
        'ai_settings' => ['設定', 'ai', 'settings'],
        'preventive_candidates' => ['單據', 'preventive', 'candidates'],
    ];

    /** 領域詞清單：資料表名稱的前半段只能用這些（每個詞的意思見文件 1.5 節）；新增領域詞要先加在這裡。 */
    public const DOMAIN_WORDS = [
        'ai' => 'AI 預測性維護', 'audit' => '稽核與操作日誌', 'classroom' => '教室與空間預約', 'device' => '設備', 'import' => '批次匯入', 'mail' => '寄信設定',
        'maintenance' => '定期保養', 'part' => '零件庫存', 'preventive' => '預防保養（AI 候選）', 'repair' => '報修與維修', 'role' => '身分與權限',
    ];

    /**
     * 欄位的中文說明：同名欄位共用，個別資料表不同的用「資料表.欄位」覆蓋。
     *
     * @var array<string, string>
     */
    private array $columnText = [
        'id' => '系統自動編號（主鍵）', 'created_at' => '建立時間', 'updated_at' => '最後修改時間', 'deleted_at' => '軟刪除時間（有值代表已停用／刪除，資料保留供履歷追溯）',
        'name' => '名稱', 'description' => '說明', 'is_active' => '是否啟用', 'status' => '狀態', 'title' => '標題', 'email' => '電子信箱', 'phone' => '電話',
        'role_id' => '所屬身分', 'department_id' => '所屬單位', 'account' => '登入帳號（唯一）', 'password' => '密碼（雜湊後儲存，不存明碼）', 'last_login_at' => '最後登入時間', 'temporary_password' => '忘記密碼寄出的臨時密碼（只存雜湊，用過即作廢）', 'temporary_password_expires_at' => '臨時密碼的有效期限（寄出後 30 分鐘）', 'is_password_change_required' => '是否一定要先設定新密碼（用臨時密碼登入後為是，設定完成才取消）',
        'remember_token' => '「記住我」登入憑證', 'email_verified_at' => 'Email 驗證時間',
        'role_code' => '身分代碼（程式判斷用，唯一）', 'is_system' => '是否系統內建身分（內建的不能刪除）', 'permission' => '權限代碼（格式「功能.動作」，清單見 PermissionCatalog）',
        'department_code' => '部門代碼（唯一）', 'major_code' => '科系代碼（選填、唯一）', 'host' => '寄信伺服器位址', 'smtp_account' => '寄信帳號（通常是信箱）', 'encryption' => '加密方式（tls／ssl／none）', 'from_address' => '寄件人信箱', 'from_label' => '寄件人顯示名稱', 'abbreviation' => '班級名稱用的簡稱（例如資→資一忠）', 'grade_count' => '年級數（各科系不同，1～9）', 'class_count' => '每個年級的班數（1～4，依序忠孝仁愛）', 'major_id' => '申請班級的科系（majors.id，舊資料為空）', 'class_grade' => '申請班級的年級', 'class_section' => '申請班級的班別（1忠 2孝 3仁 4愛）', 'campus' => '校區', 'building' => '大樓', 'floor' => '樓層', 'classroom_code' => '教室代碼（唯一）',
        'manager_id' => '教室管理人（users.id）', 'reservation_status' => '設備異常旗標（normal 正常／abnormal 有核心設備異常，異常時不開放預約）',
        'device_code' => '設備管制編號（唯一，QR Code 內容）', 'asset_code' => '資產編號', 'device_category_id' => '設備類別（device_categories.id）', 'brand' => '品牌／製造商', 'model' => '型號規格',
        'serial_number' => '序號', 'warranty_end_date' => '保固到期日', 'classroom_id' => '所在教室（classrooms.id）', 'is_core' => '是否核心設備（異常時整間教室標示異常）',
        'category' => '分類（自由文字）', 'symptom' => '常見故障現象', 'solution' => '自助排除步驟', 'is_published' => '是否上架（未上架僅知識庫管理者可見）', 'created_by' => '建立者（users.id）',
        'default_cycle_days' => '預設保養週期（天）', 'cycle_days' => '保養週期（天）', 'start_date' => '基準日期（排程計算起點）', 'next_due_date' => '下次到期日',
        'maintenance_plan_id' => '來源保養計畫', 'maintenance_item_id' => '保養項目', 'device_id' => '設備（devices.id）', 'source' => '來源', 'scheduled_date' => '預定保養日', 'maintenance_orders.started_at' => '按下「開始保養」的時間（空白＝還沒開始）',
        'maintenance_order_id' => '所屬保養工單', 'result' => '結果', 'executed_by' => '執行人（users.id，由登入帳號帶入）', 'executed_at' => '執行時間', 'note' => '備註（操作人員填寫）', 'ng_conversion_status' => 'NG 轉報修狀態',
        'repair_request_id' => '報修單（repair_requests.id）', 'impact_level' => '影響程度', 'is_class_affected' => '是否影響上課', 'rejection_reason' => '驗收退回原因',
        'requester_id' => '提出者（users.id）：報修人／預約申請人', 'device_note' => '設備說明（手動輸入、沒有綁設備時用）', 'assigned_to' => '指派的維修人員（users.id）', 'repair_requests.accepted_by' => '指定的驗收人（users.id）；空白＝由報修人本人驗收', 'scheduled_at' => '預計處理時間',
        'classroom_reservation_id' => '所屬預約申請（classroom_reservations.id）', 'period_number' => '課表節次（可為空）',
        'cause' => '故障原因', 'resolution' => '處置方式', 'parts_used_note' => '使用備品說明（文字備註）', 'started_at' => '起始時間', 'ended_at' => '結束時間', 'total_hours' => '總工時（系統計算）',
        'attachable_type' => '附件所屬資料類型（多型）', 'attachable_id' => '附件所屬資料編號（多型）', 'file_path' => '檔案儲存路徑（私有磁碟）', 'file_name' => '原始檔名（使用者上傳時的檔名）', 'mime_type' => '檔案類型', 'size_bytes' => '檔案大小（位元組）',
        'user_id' => '操作人（users.id）', 'action' => '操作動作', 'loggable_type' => '被操作資料類型（多型）', 'loggable_id' => '被操作資料編號（多型）', 'changes' => '變更內容（JSON 快照）',
        'part_code' => '零件編號（唯一，可自訂；未填時自動產生 PART-順序數字）', 'unit' => '單位（條、個、片…）', 'purchase_date' => '採購日期', 'vendor_name' => '廠商名稱', 'remaining_quantity' => '批次剩餘數量', 'part_purchase_id' => '扣用的採購批次（part_purchases.id）', 'maintenance_result_id' => '所屬保養結果', 'specification' => '規格', 'unit_price' => '單價', 'current_stock' => '目前庫存', 'safety_stock' => '安全庫存', 'repair_log_id' => '所屬維修紀錄', 'part_id' => '零件（parts.id）', 'quantity' => '使用數量',
        'scrap_date' => '報廢日期', 'scrap_time' => '報廢時間（同一天依時間先後扣批次）', 'reason' => '原因', 'recorded_by' => '登記人（users.id）', 'part_scrap_id' => '所屬報廢紀錄（part_scraps.id）',
        'class_name' => '班級名稱', 'reservation_date' => '預約日期', 'start_time' => '開始時間', 'end_time' => '結束時間', 'reviewed_by' => '審核人（users.id）', 'reviewed_at' => '審核時間', 'purpose' => '用途', 'is_all_or_nothing' => '其中一節無法借用就整筆不要（只能整筆同意或拒絕）',
        'key' => '參數名稱', 'value' => '參數值', 'risk_score' => '風險分數（0～1）', 'threshold' => '當時的門檻', 'algorithm' => '評分演算法版本', 'explanation' => '評分明細（JSON 快照）', 'features' => '輸入特徵（JSON 快照）',
        'decided_by' => '審核人（users.id）', 'decided_at' => '審核時間', 'decision_note' => '審核備註',
        'import_batch_id' => '由哪一次批次匯入建立（import_batches.id；手動新增的資料是空白）', 'view_count' => '查看次數（打開文章加 1）',
    ];

    /** @var array<string, string> 個別資料表裡意思不同的欄位 */
    private array $columnTextSpecific = [
        'part_scraps.quantity' => '報廢數量', 'part_scrap_batches.quantity' => '從這一批扣的數量', 'part_scrap_batches.unit_price' => '這一批的單價（報廢損失用）',
        'roles.name' => '身分名稱', 'classrooms.name' => '教室名稱', 'classrooms.type' => '教室類型', 'departments.name' => '單位名稱', 'maintenance_orders.assigned_to' => '負責人（users.id，保養管理者指派，沒指派是空白）', 'majors.name' => '科系名稱（唯一）', 'mail_settings.password' => '寄信密碼（加密後存放，畫面不顯示）', 'mail_settings.port' => '寄信連接埠（587 或 465）', 'mail_settings.is_enabled' => '是否啟用（啟用後全站寄信改用這組設定）', 'mail_settings.updated_by' => '最後修改者（users.id）', 'device_categories.name' => '類別名稱（唯一）', 'maintenance_items.name' => '項目名稱', 'maintenance_plans.name' => '計畫名稱', 'parts.name' => '零件名稱',
        'repair_requests.description' => '故障描述', 'repair_requests.title' => '報修標題', 'repair_requests.classroom_id' => '設備地點（classrooms.id，報修當時的教室）', 'maintenance_items.description' => '說明',
        'users.name' => '姓名', 'audit_logs.description' => '操作說明（寫入當下的文字快照，之後資料改名或刪除也不變）',
        'repair_requests.requester_id' => '報修人（users.id）', 'classroom_reservations.requester_id' => '預約申請人（users.id）', 'classroom_reservations.classroom_id' => '預約的教室（classrooms.id）',
        'repair_requests.rejection_reason' => '驗收退回原因（只保留最新一次）', 'classroom_reservations.rejection_reason' => '拒絕原因（被拒絕時填寫）', 'classroom_reservation_slots.rejection_reason' => '這個時段的拒絕原因（被拒絕時填寫）',
        'part_purchases.quantity' => '進貨數量', 'part_purchases.unit_price' => '進貨單價',
        'repair_part_usages.unit_price' => '扣料當下那一批的單價（成本快照）', 'maintenance_part_usages.unit_price' => '扣料當下那一批的單價（成本快照）',
        'repair_logs.started_at' => '開始維修的時間', 'repair_logs.ended_at' => '結束維修的時間',
        'maintenance_plan_items.maintenance_plan_id' => '所屬保養計畫（maintenance_plans.id）', 'maintenance_plan_items.maintenance_item_id' => '計畫包含的保養項目（maintenance_items.id）',
        'preventive_candidates.maintenance_order_id' => '核准後建立的保養工單（maintenance_orders.id）', 'maintenance_results.repair_request_id' => '由這次 NG 轉出的報修單（repair_requests.id）',
        'import_batches.import_type' => '匯入種類（devices 設備／users 用戶）', 'import_batches.name' => '匯入的檔案名稱（只是記錄，檔案確認後就刪除）', 'import_batches.imported_count' => '成功寫入幾筆',
        'import_batches.skipped_count' => '因為有錯誤而略過幾列', 'import_batches.created_by' => '執行匯入的人（users.id）', 'import_batches.undone_at' => '整批復原的時間（空白＝尚未復原）', 'import_batches.undone_by' => '執行復原的人（users.id）',
    ];

    /**
     * Laravel 內建資料表的欄位說明（「資料字典.md」的附錄用；業務資料表不放這裡）。
     *
     * @var array<string, string>
     */
    private array $frameworkText = [
        'migrations.id' => '流水號', 'migrations.migration' => '已執行的 migration 檔名', 'migrations.batch' => '執行批次',
        'sessions.id' => '工作階段代碼', 'sessions.user_id' => '登入的用戶（users.id，未登入為空白）', 'sessions.ip_address' => '來源 IP', 'sessions.user_agent' => '瀏覽器資訊', 'sessions.payload' => '工作階段內容（序列化）', 'sessions.last_activity' => '最後活動時間（Unix 時間戳記）',
        'cache.key' => '快取名稱', 'cache.value' => '快取內容', 'cache.expiration' => '到期時間（Unix 時間戳記）',
        'cache_locks.key' => '鎖的名稱', 'cache_locks.owner' => '持有者', 'cache_locks.expiration' => '到期時間（Unix 時間戳記）',
        'jobs.id' => '佇列工作編號', 'jobs.queue' => '佇列名稱', 'jobs.payload' => '工作內容', 'jobs.attempts' => '已嘗試次數', 'jobs.reserved_at' => '被取走處理的時間', 'jobs.available_at' => '可以開始處理的時間', 'jobs.created_at' => '建立時間',
        'job_batches.id' => '批次代碼', 'job_batches.name' => '批次名稱', 'job_batches.total_jobs' => '工作總數', 'job_batches.pending_jobs' => '尚未處理的數量', 'job_batches.failed_jobs' => '失敗的數量', 'job_batches.failed_job_ids' => '失敗工作的編號清單', 'job_batches.options' => '批次選項', 'job_batches.cancelled_at' => '取消時間', 'job_batches.created_at' => '建立時間', 'job_batches.finished_at' => '完成時間',
        'failed_jobs.id' => '流水號', 'failed_jobs.uuid' => '工作唯一代碼', 'failed_jobs.connection' => '佇列連線', 'failed_jobs.queue' => '佇列名稱', 'failed_jobs.payload' => '工作內容', 'failed_jobs.exception' => '失敗的錯誤訊息', 'failed_jobs.failed_at' => '失敗時間',
        'password_reset_tokens.email' => '申請重設的 Email', 'password_reset_tokens.token' => '重設憑證（雜湊）', 'password_reset_tokens.created_at' => '建立時間',
    ];

    /**
     * 代碼值：欄位 => [值 => 中文]。這些是資料庫裡真正存的值（小寫英文加底線），畫面顯示的中文走語言檔。
     *
     * @var array<string, array<string, string>>
     */
    private array $codes = [
        'repair_requests.status' => ['pending' => '新報修', 'assigned' => '已派工', 'in_progress' => '處理中', 'pending_review' => '待驗收', 'completed' => '已結案', 'aborted' => '已中止'],
        'repair_requests.impact_level' => ['low' => '輕微', 'medium' => '中等', 'high' => '嚴重'],
        'devices.status' => ['normal' => '正常', 'repairing' => '維修中', 'retired' => '已淘汰', 'disabled' => '停用'],
        'classrooms.reservation_status' => ['normal' => '正常（可預約）', 'abnormal' => '有核心設備異常（不開放預約）'],
        'classroom_reservations.status' => ['pending' => '待審核', 'approved' => '已核准（全部時段同意）', 'rejected' => '已駁回（全部時段拒絕）', 'partially_approved' => '部分核准（有同意也有拒絕的時段）'],
        'classroom_reservation_slots.status' => ['approved' => '同意', 'rejected' => '拒絕'],
        'maintenance_orders.status' => ['pending' => '待處理', 'in_progress' => '進行中', 'completed' => '已完成', 'aborted' => '已中止（設備淘汰）'],
        'maintenance_orders.source' => ['periodic' => '定期', 'ai' => 'AI 預防'],
        'maintenance_results.result' => ['ok' => '正常', 'ng' => '異常'],
        'maintenance_results.ng_conversion_status' => ['pending_handoff' => '待轉報修', 'not_applicable' => '不需轉報修', 'converted' => '已轉成報修單'],
        'preventive_candidates.status' => ['pending' => '待審核', 'approved' => '已核准建單', 'rejected' => '已駁回', 'auto_created' => '自動建單', 'duplicate' => '重複未建單'],
        'audit_logs.action' => ['created' => '新增', 'updated' => '修改', 'deleted' => '刪除', 'assigned' => '派工', 'reassigned' => '重新指派', 'status_changed' => '狀態變更', 'logged_in' => '登入', 'logged_out' => '登出', 'login_failed' => '登入失敗'],
    ];

    /** @var list<string> 在 SQLite 看起來是文字、但在 MySQL 是列舉的欄位（讓兩邊產生的文件一樣） */
    private array $enumColumns = ['classrooms.reservation_status', 'repair_requests.impact_level'];

    /** @var list<string> 在 SQLite 看起來是長文字、但在 MySQL 是 JSON 的欄位（同樣是為了讓兩邊產生的文件一樣） */
    private array $jsonColumns = ['audit_logs.changes', 'preventive_candidates.explanation', 'preventive_candidates.features'];

    /**
     * 「資料字典.md」的章節分組：模組名稱 => [一句話說明, [資料表…]]。順序就是文件裡的順序。
     * 新增資料表時要放進其中一個模組（沒放的會顯示「（未分組）」，SchemaDocsAreUpToDateTest 會失敗）。
     *
     * @var array<string, array{0: string, 1: list<string>}>
     */
    private array $modules = [
        '帳號與權限' => ['誰可以登入、屬於哪個單位、能做哪些事（身分像 Discord 身分組，勾選要開放的權限）', ['roles', 'role_permissions', 'users', 'departments']],
        '基本主檔' => ['學校的科系、教室、設備類別與設備', ['majors', 'classrooms', 'device_categories', 'devices']],
        '報修與維修' => ['報修、派工、維修、驗收的主流程，以及報修前先查的自助知識庫', ['repair_requests', 'repair_logs', 'repair_part_usages', 'knowledge_base']],
        '保養與 AI 預防保養' => ['定期保養的項目、計畫、工單、結果，以及 AI 預防保養的候選與參數', ['maintenance_items', 'maintenance_plans', 'maintenance_plan_items', 'maintenance_orders', 'maintenance_results', 'maintenance_part_usages', 'ai_settings', 'preventive_candidates']],
        '零件庫存' => ['零件、進貨批次（先進先出成本）、報廢', ['parts', 'part_purchases', 'part_scraps', 'part_scrap_batches']],
        '空間預約' => ['教師申請教室時段、逐節審核', ['classroom_reservations', 'classroom_reservation_slots']],
        '系統共用' => ['附件、操作日誌、批次匯入紀錄、寄信設定（各模組共用）', ['attachments', 'audit_logs', 'import_batches', 'mail_settings']],
    ];

    /**
     * 每張資料表「拿來做什麼」的一句話（資料字典用；正規化結論另見 $tables）。
     *
     * @var array<string, string>
     */
    private array $purposes = [
        'roles' => '身分。內建六個：admin 系統管理員、it_manager 設備管理員、technician 維修人員、teacher 教師、executive 主管、student 學生；每個用戶只有一個身分。',
        'role_permissions' => '每個身分勾選了哪些權限，一列一個權限代碼（權限清單在 PermissionCatalog）。',
        'users' => '系統帳號。停用或軟刪除後不能登入，但報修、保養等紀錄仍保留；含「忘記密碼」臨時密碼流程用的欄位。',
        'departments' => '單位（部門）清單，用戶與教室的「所屬單位」下拉選單來源。',
        'majors' => '科系，以及各科系的年級數與每年級班數；空間預約申請班級的下拉選單來源。',
        'classrooms' => '教室，含校區、樓層與管理人；有核心設備異常時 reservation_status 會變成 abnormal，教室暫停預約。',
        'device_categories' => '設備類別（投影機、電腦…）；設備、保養項目、保養計畫與保養工單都依類別分類。',
        'devices' => '設備。device_code（管制編號）就是 QR Code 的內容；有狀態、是否核心設備與保固日；停用以軟刪除處理，履歷保留。',
        'repair_requests' => '報修單，也是報修主流程的狀態機：新報修 → 已派工 → 處理中 → 待驗收 → 已結案（驗收不通過退回處理中；設備淘汰時進行中的單會變已中止）。',
        'repair_logs' => '維修人員每次處理的紀錄（原因、處置、起訖時間、工時）；驗收退回後會再填新的一筆，舊的不修改。',
        'repair_part_usages' => '維修用掉的零件、數量與「扣料當下那一批」的單價（先進先出成本快照）。',
        'knowledge_base' => '自助排除知識庫文章（常見故障與處理步驟），報修前先查；有上架旗標與查看次數。',
        'maintenance_items' => '保養項目（要檢查什麼），可指定適用的設備類別。',
        'maintenance_plans' => '保養計畫：針對一台設備或一類設備，設定保養週期與下次到期日；排程依它自動產生工單。',
        'maintenance_plan_items' => '保養計畫包含哪些保養項目（多對多的中間表）。',
        'maintenance_orders' => '保養工單，來源是定期計畫或 AI 預防保養；狀態：待處理 → 進行中 → 已完成（設備淘汰時未完成的會變已中止）。取消用軟刪除。',
        'maintenance_results' => '保養結果（OK／NG），與工單一對一；NG 可以轉成一張報修單。',
        'maintenance_part_usages' => '保養用掉的零件與當時單價，結構與維修用料相同。',
        'ai_settings' => 'AI 預防保養的參數（「參數名稱＋參數值」），例如門檻、去重天數、是否需主管審核。',
        'preventive_candidates' => 'AI 掃描出的預防保養候選（含評分明細快照）；主管核准後才建立 AI 來源的保養工單。',
        'parts' => '零件主檔：編號、單位、安全庫存與目前庫存（目前庫存是各批次剩餘數量的加總，只能由庫存服務更新）。',
        'part_purchases' => '零件進貨批次，每一筆是一個批次；用料與報廢依先進先出從最早、還有剩的批次扣。',
        'part_scraps' => '零件報廢單（損壞、過期、遺失）。',
        'part_scrap_batches' => '報廢扣了哪幾個進貨批次與當時單價（取消報廢時依它加回庫存）。',
        'classroom_reservations' => '空間預約申請（誰、哪間教室、哪個班級、用途）；整筆申請的狀態由底下各時段的審核結果決定。',
        'classroom_reservation_slots' => '預約申請的每一個時段（日期與起訖時間），可以逐節同意或拒絕；使用時間只記在這裡。',
        'attachments' => '附件（報修與維修照片、採購發票），用多型關聯掛在各種資料底下；檔案存在私有磁碟，只能透過網站依權限下載。',
        'audit_logs' => '操作日誌：誰、何時、對哪筆資料做了什麼；說明文字是寫入當下的快照。',
        'import_batches' => '批次匯入（設備、用戶）的紀錄；設備與用戶以 import_batch_id 指回來，匯錯時可以整批復原。',
        'mail_settings' => '寄信用的 SMTP 設定，整張表只有一筆；密碼加密存放。',
    ];

    /** @var array<string, string> Laravel 內建資料表的用途（資料字典附錄用） */
    private array $frameworkPurposes = [
        'migrations' => '記錄哪些 migration 已經執行過（php artisan migrate 靠它判斷要不要再跑）。',
        'sessions' => '登入狀態（SESSION_DRIVER=database）；帳號被停用時會刪掉他的工作階段把人登出。',
        'cache' => '快取資料（CACHE_STORE=database），例如排程心跳時間。',
        'cache_locks' => '快取鎖（避免排程等工作被重複執行）。',
        'jobs' => 'Laravel 佇列的待處理工作；目前系統的寄信是即時寄出，沒有使用佇列。',
        'job_batches' => 'Laravel 佇列的批次工作紀錄；目前沒有使用。',
        'failed_jobs' => 'Laravel 佇列失敗的工作；目前沒有使用。',
        'password_reset_tokens' => 'Laravel 預設的重設密碼憑證表；本系統的「忘記密碼」改用臨時密碼（存在 users），所以這張表沒有使用。',
    ];

    /**
     * 例外：不照規則取名的欄位與理由。
     *
     * @var list<array{0: string, 1: string, 2: string}>
     */
    private array $exceptions = [
        ['資料表 knowledge_base', '單數', '工作計畫與規格文件固定使用 knowledge_base 這個名稱，不改。'],
        ['classroom_reservations.class_name', '名稱欄位不叫 name', '它是「預約的班級名稱」（例如一年一班），班級不是一張資料表、也不是這筆預約本身，所以用 <名詞>_name；規則：<名詞>_name 只用在「另一個沒有自己資料表的東西」的名稱。'],
        ['attachments.file_name', '名稱欄位不叫 name', '附件記錄的就是一個檔案，檔名與 file_path（路徑）成對，也沿用規格書 attachments.file_name 的名稱。'],
        ['devices.asset_code', '代碼前綴不是自己的實體（device_）', '資產編號是設備身上「另一種編號」（學校財產編號），前綴用它所屬的東西 asset；設備自己的代碼仍是 device_code。'],
        ['audit_logs.user_id', '指向使用者卻不叫 requester_id／*_by', '它是「操作人」，是日誌的固定欄位，名稱沿用規格書 audit_logs.user_id。'],
        ['audit_logs.action 的值 login_failed', '不是 <動詞>_ed 的形式', '「登入失敗」本來就是一個名詞片語，保持 login_failed；其餘動作值都是過去式（created、logged_in、logged_out…）。'],
        ['主鍵一律叫 id', '規格書第 8 章寫 user_id、device_id…', '彭仕衡決定保留 Laravel 慣例，主鍵一律 id；外鍵才用 <表單數>_id（見第 8、9 節）。'],
    ];

    /** @var array<string, string> 邏輯型態 => 說明 */
    private array $logicalTypeNames = [
        'integer' => '整數', 'string' => '文字', 'text' => '長文字', 'boolean' => '布林', 'date' => '日期', 'datetime' => '日期時間', 'time' => '時刻', 'decimal' => '小數', 'json' => 'JSON', 'enum' => '列舉',
    ];

    public function handle(): int
    {
        $schema = $this->readSchema();

        $files = [
            base_path('docs/資料庫命名規範與資料字典.md') => $this->document($schema),
        ];

        // 實體資料字典要讀 MySQL 的 information_schema；測試用的 SQLite 沒有這些資訊，就略過（CI 的 MySQL 任務會檢查它）。
        if (DB::getDriverName() === 'mysql') {
            $files[base_path('docs/資料字典.md')] = $this->physicalDocument($schema);
        } else {
            $this->line('（目前不是 MySQL，略過「資料字典.md」；它只在連到 MySQL 時產生與檢查。）');
        }

        // 新資料表一定要放進 $modules 分組，並補上 $purposes 的用途，否則資料字典會有沒說明的表（和資料庫種類無關，SQLite 測試也會擋）。
        $missing = array_unique(array_merge(
            array_diff(array_keys($schema), array_merge(...array_column($this->modules, 1))),
            array_diff(array_keys($schema), array_keys($this->purposes)),
        ));
        if ($missing) {
            $this->error('這些資料表還沒放進模組分組或沒有用途說明，請到 GenerateSchemaDocs 的 $modules、$purposes 補上：' . implode('、', $missing));

            return self::FAILURE;
        }

        if ($this->option('check')) {
            $stale = array_keys(array_filter($files, fn ($content, $path) => ! is_file($path) || file_get_contents($path) !== $content, ARRAY_FILTER_USE_BOTH));

            if ($stale) {
                $this->error('這些文件不是最新的，請執行 php artisan docs:schema：' . implode('、', array_map('basename', $stale)));

                return self::FAILURE;
            }

            $this->info('文件都是最新的。');

            return self::SUCCESS;
        }

        foreach ($files as $path => $content) {
            file_put_contents($path, $content);
            $this->info('已產生 ' . basename($path));
        }

        return self::SUCCESS;
    }

    // ------------------------------------------------------------------ 讀取資料庫結構

    /**
     * @return array<string, array{columns: list<array<string, mixed>>, foreign: array<string, array{table: string, column: string}>}>
     */
    private function readSchema(): array
    {
        $existing = array_map(fn ($table) => $table['name'], Schema::getTables());
        $names = array_values(array_filter($existing, fn ($name) => ! in_array($name, self::FRAMEWORK_TABLES, true)));
        // 文件順序：先照 $tables 的順序，沒列到的資料表（新增的）排在後面。
        usort($names, function ($a, $b) {
            $order = array_flip(array_keys($this->tables));

            return ($order[$a] ?? 999) <=> ($order[$b] ?? 999) ?: strcmp($a, $b);
        });

        $schema = [];
        foreach ($names as $table) {
            $foreign = [];
            foreach (Schema::getForeignKeys($table) as $fk) {
                foreach ($fk['columns'] as $i => $column) {
                    $foreign[$column] = ['table' => $fk['foreign_table'], 'column' => $fk['foreign_columns'][$i] ?? 'id'];
                }
            }

            $unique = [];
            foreach (Schema::getIndexes($table) as $index) {
                if ($index['unique'] && ! $index['primary'] && count($index['columns']) === 1) {
                    $unique[$index['columns'][0]] = true;
                }
            }

            $columns = [];
            foreach (Schema::getColumns($table) as $col) {
                $key = "{$table}.{$col['name']}";
                $logical = $this->logicalType($col, $key);
                $columns[] = [
                    'name' => $col['name'],
                    'logical' => $logical,
                    'nullable' => $col['nullable'],
                    'unique' => isset($unique[$col['name']]),
                    'fk' => $foreign[$col['name']] ?? null,
                    'category' => $this->category($table, $col['name'], $logical, $foreign[$col['name']] ?? null),
                ];
            }

            // 固定順序：id → 關聯（外鍵）→ 一般欄位 → created_at／updated_at／deleted_at；同一組內照字母排。
            usort($columns, function ($a, $b) {
                $rank = fn ($c) => $c['name'] === 'id' ? 0 : ($c['fk'] ? 1 : (in_array($c['name'], ['created_at', 'updated_at', 'deleted_at'], true) ? 3 : 2));

                return $rank($a) <=> $rank($b) ?: ($rank($a) === 3 ? array_search($a['name'], ['created_at', 'updated_at', 'deleted_at']) <=> array_search($b['name'], ['created_at', 'updated_at', 'deleted_at']) : strcmp($a['name'], $b['name']));
            });

            $schema[$table] = ['columns' => $columns, 'foreign' => $foreign];
        }

        return $schema;
    }

    private function logicalType(array $col, string $key): string
    {
        if (in_array($key, $this->enumColumns, true)) {
            return 'enum';
        }

        if (in_array($key, $this->jsonColumns, true)) {
            return 'json';
        }

        $type = strtolower($col['type']);
        $name = strtolower($col['type_name']);

        return match (true) {
            str_contains($type, 'tinyint(1)') || $name === 'boolean' => 'boolean',
            in_array($name, ['bigint', 'integer', 'int', 'smallint', 'mediumint', 'tinyint'], true) => 'integer',
            in_array($name, ['varchar', 'char', 'string'], true) => 'string',
            in_array($name, ['text', 'mediumtext', 'longtext', 'tinytext'], true) => 'text',
            in_array($name, ['timestamp', 'datetime'], true) => 'datetime',
            $name === 'date' => 'date',
            $name === 'time' => 'time',
            in_array($name, ['decimal', 'float', 'double', 'numeric'], true) => 'decimal',
            $name === 'json' => 'json',
            $name === 'enum' => 'enum',
            default => 'string',
        };
    }

    /** 這個欄位屬於哪一類命名（對應命名規範的規則編號）。 */
    private function category(string $table, string $name, string $logical, ?array $fk): string
    {
        return match (true) {
            $name === 'id' => 'PK',
            $fk !== null && $fk['table'] === 'users' => 'FK-人',
            $fk !== null => 'FK-表',
            in_array($name, ['attachable_type', 'attachable_id', 'loggable_type', 'loggable_id'], true) => '多型關聯',
            $logical === 'boolean' => '布林',
            $logical === 'datetime' => '時間',
            $logical === 'date' => '日期',
            $logical === 'time' => '時刻',
            $logical === 'json' => 'JSON',
            $name === 'status' || str_ends_with($name, '_status') => '狀態',
            str_ends_with($name, '_code') => '代碼',
            $name === 'name' || str_ends_with($name, '_name') => '名稱',
            $name === 'title' => '標題',
            in_array($name, ['description'], true) || $name === 'note' || str_ends_with($name, '_note') || str_ends_with($name, '_reason') => '文字說明',
            preg_match('/(_days|_hours|_stock|_price|_bytes|_score)$/', $name) || in_array($name, ['quantity', 'threshold'], true) => '數量／金額',
            default => '一般',
        };
    }

    /**
     * 規格書沒有列出的資料表（$tables 裡「規格書對照」欄以「規格書未列」開頭的），用在第 8 節。
     *
     * @param  SchemaMap  $schema
     */
    private function tablesNotInSpec(array $schema): string
    {
        $names = array_filter(array_keys($schema), fn ($table) => str_starts_with($this->tables[$table][1] ?? '規格書未列', '規格書未列'));

        return implode('、', array_map(fn ($table) => "`{$table}`", $names));
    }

    private function describe(string $table, string $column): string
    {
        // 先找「資料表.欄位」的專屬說明（兩個陣列都可能放），再用同名欄位共用的說明，最後是 Laravel 內建資料表的說明。
        return $this->columnTextSpecific["{$table}.{$column}"]
            ?? $this->columnText["{$table}.{$column}"]
            ?? $this->frameworkText["{$table}.{$column}"]
            ?? $this->columnText[$column]
            ?? '';
    }

    /** 狀態、類型欄位的值域文字：「pending 新報修／assigned 已派工…」。 */
    private function domain(string $table, string $column): string
    {
        $values = $this->codes["{$table}.{$column}"] ?? null;

        return $values ? implode('／', array_map(fn ($code, $zh) => "{$code} {$zh}", array_keys($values), $values)) : '';
    }

    private function typeLabel(array $column): string
    {
        return $this->logicalTypeNames[$column['logical']] ?? $column['logical'];
    }

    // ------------------------------------------------------------------ 產生文件

    private function document(array $schema): string
    {
        $out = [];
        $out[] = '# 資料庫命名規範與資料字典';
        $out[] = '';
        $out[] = '> 這份是資料庫的「命名規範與邏輯字典」：命名邏輯、代碼值、例外、正規化結論、資料表一覽、ERD、完整名稱清單，以及和規格書的差異都在這裡；';
        $out[] = '> 每個欄位在 MySQL 裡的實際型態、長度、預設值與刪除規則，見同資料夾的《資料字典》。';
        $out[] = '> 內容是從實際的資料庫結構**自動產生**的（`php artisan docs:schema`）；資料庫異動請改 migration，再重新產生。';
        $out[] = '> 命名規則由 `DatabaseNamingConventionTest` 自動檢查、文件過期由 `SchemaDocsAreUpToDateTest` 檢查，不合規則或忘了更新都會測試失敗。';
        $out[] = '';
        $out[] = '| 章節 | 內容 |';
        $out[] = '|---|---|';
        $out[] = '| 1 | 取名邏輯（設計原則、欄位 12 條規則、資料表 8 條規則、取名步驟、固定詞彙表） |';
        $out[] = '| 2 | 代碼值清單（資料庫裡真正存的值） |';
        $out[] = '| 3 | 例外清單與理由 |';
        $out[] = '| 4 | 正規化檢查結果 |';
        $out[] = '| 5 | 資料表一覽 |';
        $out[] = '| 6 | ERD |';
        $out[] = '| 7 | 完整名稱清單與資料字典（每張表的每個欄位） |';
        $out[] = '| 8 | 與規格書第 8 章資料字典的差異 |';
        $out[] = '| 9 | 定案紀錄（決定原則、資料表名稱、欄位與資料值改名對照） |';
        $out[] = '';
        $out[] = '## 1. 取名邏輯';
        $out[] = '';
        $out[] = '### 1.1 設計原則';
        $out[] = '';
        $out[] = '1. **看名字就知道是什麼**：不用縮寫，不用同義詞混著用（同一件事全專案只有一種叫法）。';
        $out[] = '2. **由型態決定結尾**：布林用 `is_`／`has_` 開頭，時間用 `_at`，日期用 `_date`，時刻用 `_time`；看到結尾就知道怎麼用。';
        $out[] = '3. **由用途決定名詞**：名稱 `name`、標題 `title`、說明 `description`、備註 `note`、原因 `reason`、狀態 `status`、代碼 `<實體>_code`。';
        $out[] = '4. **關聯一律有外鍵，名字寫出指向誰**：`device_id` 指向 `devices`；指向人的有固定寫法（見規則 N4）。';
        $out[] = '5. **規格書已有的名稱優先沿用**（例外與理由列在第 3 節）；規格書沒有的，才依這份規則取名。';
        $out[] = '6. 全部小寫英文、單字用底線分開（snake_case）；中文只出現在說明與畫面，不出現在資料表與欄位名稱。';
        $out[] = '';
        $out[] = '### 1.2 規則表';
        $out[] = '';
        $out[] = '| 編號 | 對象 | 規則 | 正確範例 | 錯誤範例 |';
        $out[] = '|---|---|---|---|---|';
        $out[] = '| N1 | 資料表 | 小寫英文、底線分隔、**複數名詞**；多對多的關聯表用「兩邊的名詞」；每張表都有 `id`、`created_at`、`updated_at`（需保留履歷的主檔再加 `deleted_at`） | `devices`、`maintenance_plan_items`、`role_permissions` | `Device`、`repairRequest`、`device_list` |';
        $out[] = '| N2 | 主鍵 | 單一欄位，一律叫 `id` | `devices.id` | `device_id` 當主鍵 |';
        $out[] = '| N3 | 指向其他資料表的欄位 | `<被指向的資料表（單數）>_id`，並且一定要有外鍵約束 | `device_category_id` → `device_categories` | `category`（存文字）、`cat_id` |';
        $out[] = '| N4 | 指向「人」（users）的欄位 | 只有四種寫法：**提出者** `requester_id`；**管理人** `manager_id`；**被指派者** `assigned_to`；**做了某個動作的人** `<動詞過去分詞>_by`（`created_by`、`reviewed_by`、`executed_by`、`decided_by`）；日誌的**操作人**固定叫 `user_id` | `repair_requests.requester_id`、`classrooms.manager_id` | `reporter_id`、`username`（欄位名稱不代表帳號用途時）、`technician`（存姓名） |';
        $out[] = '| N5 | 布林 | `is_` 或 `has_` 開頭 | `is_active`、`is_core`、`is_published` | `active`、`enabled` |';
        $out[] = '| N6 | 時間 | 日期加時刻用 `_at`；只有日期用 `_date`；只有時刻用 `_time`；期間用 `_days`／`_hours` | `reviewed_at`、`reservation_date`、`start_time`、`cycle_days` | `review_time`（其實是日期時間）、`date1` |';
        $out[] = '| N7 | 狀態 | 狀態機叫 `status`；某一方面的狀態叫 `<方面>_status`；值是小寫英文加底線（過去分詞或形容詞） | `status`、`reservation_status`、`ng_conversion_status` | `state`、`flag`、`statusCode` |';
        $out[] = '| N8 | 代碼與名稱 | 給人看的業務代碼叫 `<實體>_code`（會印在標籤、表單、網址上，所以保留實體名稱）；稱呼叫 `name`（`<名詞>_name` 只用在「沒有自己資料表的另一個東西」的名稱，例如預約的 `class_name`）；文件類紀錄的標題叫 `title` | `device_code`、`classroom_code`、`role_code`、`name`、`title` | `code`（單獨一個）、`slug`、`room_no`、`caption` |';
        $out[] = '| N9 | 說明、備註、原因 | 實體本身的說明叫 `description`；操作人員填的備註用**單數** `note`（針對某件事的備註叫 `<事>_note`）；理由叫 `<事>_reason` | `description`、`note`、`decision_note`、`rejection_reason` | `notes`、`remarks`、`comment` |';
        $out[] = '| N10 | 數量與金額 | 用「單位」當結尾：`_days`、`_hours`、`_stock`、`_price`、`_bytes`、`quantity` | `cycle_days`、`unit_price`、`current_stock` | `days`（不知道是什麼）、`price1` |';
        $out[] = '| N11 | 多型關聯 | `<名詞>_type` ＋ `<名詞>_id`，不加外鍵 | `attachable_type`／`attachable_id` | — |';
        $out[] = '| N12 | JSON 欄位 | 只用在「當時的快照」或「不會單獨查詢的內容」，名稱用名詞；能拆成欄位或資料表的就不要用 JSON | `changes`、`explanation`、`features` | 用 JSON 存一串權限（已拆成 `role_permissions`） |';
        $out[] = '';
        $out[] = '### 1.3 替新欄位取名的步驟';
        $out[] = '';
        $out[] = '1. **它存的是什麼型態？** 布林 → N5；日期時間 → N6；狀態 → N7；數量金額 → N10。型態決定結尾，先定結尾。';
        $out[] = '2. **它是關聯嗎？** 指向別的資料表 → N3，一定要加外鍵；指向人 → N4（提出者／管理人／被指派者／做了動作的人，四選一）。';
        $out[] = '3. **它是名稱、代碼、標題、說明、備註、原因的哪一種？** 對照 N8、N9，用固定的那個字。';
        $out[] = '4. **這個值能由別的欄位算出來嗎？** 能的話不要另存（第三正規化）；真的要存（為了效能或規格書要求），在這份文件第 4 節寫明理由。';
        $out[] = '5. **一個欄位只放一個值嗎？** 需要放「很多個」的話，另開資料表，不要用逗號或 JSON（第一正規化）。';
        $out[] = '6. 規格書第 8 章已經有這個欄位的話，先沿用它的名稱；不合規則就記在第 3 節的例外清單。';
        $out[] = '7. 寫完後執行 `php artisan test --filter=DatabaseNamingConventionTest`，再執行 `php artisan docs:schema` 更新文件。';
        $out[] = '';
        $out[] = '### 1.4 詞彙表（固定用字）';
        $out[] = '';
        $out[] = '| 概念 | 固定用字 | 不要用 |';
        $out[] = '|---|---|---|';
        foreach ([
            ['提出報修／預約的人', 'requester', 'reporter、applicant、user'],
            ['被指派處理的人', 'assigned_to（技術人員）', 'assignee_note、technician（文字）'],
            ['建立資料的人', 'created_by', 'author、owner'],
            ['審核的人', 'reviewed_by（預約）／decided_by（AI 候選）', 'approver'],
            ['保養的執行人', 'executed_by', 'operator（文字姓名）'],
            ['設備類別', 'device_category', 'category（單獨使用）、type'],
            ['啟用／停用', 'is_active', 'enabled、status = active'],
            ['軟刪除（保留履歷的刪除）', 'deleted_at', 'is_deleted'],
            ['備註', 'note', 'notes、remark、comment'],
            ['說明', 'description', 'desc、detail、memo'],
            ['代碼', '<實體>_code（device_code、classroom_code、role_code）', 'code、no、number、slug'],
            ['登入帳號', 'account', 'username、login_name'],
            ['教室', 'classroom（欄位與資料表都用 classroom，不用 room）', 'room'],
            ['名稱', 'name', 'title（只用於文件類紀錄）、label、room_name'],
            ['工時', 'total_hours', 'labor_time、duration'],
            ['週期', 'cycle_days', 'period、interval'],
            ['序號（第幾個）', '<名詞>_number（設備序號 serial_number、課表節次 period_number）；寫完整單字，不縮寫', '<名詞>_no、<名詞>_num、seq'],
        ] as $row) {
            $out[] = '| ' . implode(' | ', $row) . ' |';
        }
        $out[] = '';
        $out[] = '### 1.5 資料表命名邏輯';
        $out[] = '';
        $out[] = '資料表名稱和欄位一樣，用「公式」組出來，看名稱就知道它屬於哪一塊業務、是哪一種表：';
        $out[] = '';
        $out[] = '```';
        $out[] = '資料表名稱 ＝ ［領域詞］＿實體詞（複數）';
        $out[] = '```';
        $out[] = '';
        $out[] = '| 編號 | 規則 | 說明 | 範例 |';
        $out[] = '|---|---|---|---|';
        $out[] = '| T1 | 小寫英文、單字用底線、**複數** | 表裡裝的是「很多筆」，所以用複數 | `devices`、`repair_requests` |';
        $out[] = '| T2 | 核心主檔只用單一名詞 | 大家都知道它是什麼，不需要領域詞 | `users`、`devices`、`classrooms`、`parts` |';
        $out[] = '| T3 | 其他表用「領域詞＿實體詞」 | 領域詞放前面，同一塊業務的表在清單裡會排在一起 | `maintenance_orders`、`repair_logs`、`classroom_reservations` |';
        $out[] = '| T4 | 領域詞只能用固定清單 | 避免同一塊業務出現兩種前綴；新增領域詞要先加進清單（測試會擋） | 見下表 |';
        $out[] = '| T5 | 明細或關聯表：上層的單數＋下層的複數 | 讓人從名稱看出「誰底下的什麼」 | `maintenance_plan_items`（計畫底下的項目）、`role_permissions`（身分底下的權限） |';
        $out[] = '| T6 | 結尾詞表示類型 | 紀錄／日誌用 `_logs`，設定用 `_settings`，單據用 `_requests`／`_orders`／`_reservations`，候選用 `_candidates` | `audit_logs`、`ai_settings` |';
        $out[] = '| T7 | 表名用詞和欄位用詞一致 | 表叫 `device_categories`，指向它的欄位就叫 `device_category_id`，同一概念不換說法 | `devices.device_category_id` |';
        $out[] = '| T8 | 不加無意義的前綴與縮寫 | 不用 `tbl_`、`t_`、模組縮寫 | `devices`（不是 `tbl_dev`） |';
        $out[] = '';
        $out[] = '領域詞清單（T4）：';
        $out[] = '';
        $out[] = '| 領域詞 | 代表的業務 | 目前使用它的資料表 |';
        $out[] = '|---|---|---|';
        foreach (self::DOMAIN_WORDS as $word => $meaning) {
            $used = [];
            foreach ($schema as $table => $_) {
                [, $domain] = $this->tableNaming[$table] ?? ['', '', ''];
                if ($domain === $word || str_starts_with($domain, $word . '_')) {
                    $used[] = '`' . $table . '`';
                }
            }
            $out[] = "| `{$word}` | {$meaning} | " . implode('、', $used) . ' |';
        }
        $out[] = '';
        $out[] = '資料表的類型（對應 T6 的結尾詞）：';
        $out[] = '';
        $out[] = '| 類型 | 意思 | 例子 |';
        $out[] = '|---|---|---|';
        $out[] = '| 主檔 | 長期存在、被別的資料引用的基本資料 | `devices`、`classrooms`、`roles` |';
        $out[] = '| 單據 | 一筆業務事件，會經過狀態變化 | `repair_requests`、`maintenance_orders`、`classroom_reservations` |';
        $out[] = '| 明細 | 掛在某張單據或主檔底下的細項、結果 | `maintenance_results`、`repair_part_usages` |';
        $out[] = '| 關聯明細 | 兩邊多對多的中間表，或「一對多、一個值一列」的拆表（第一正規化） | `maintenance_plan_items`、`role_permissions` |';
        $out[] = '| 紀錄 | 只新增、不修改的歷史（稽核、處理過程） | `audit_logs`、`repair_logs` |';
        $out[] = '| 設定 | 系統參數 | `ai_settings` |';
        $out[] = '| 附屬（多型） | 可以掛在多種資料底下的附件 | `attachments` |';
        $out[] = '';
        $out[] = '每張資料表怎麼拆解、屬於哪一類，見第 5 節的資料表一覽。';
        $out[] = '';
        $out[] = '## 2. 代碼值清單（資料庫裡真正存的值）';
        $out[] = '';
        $out[] = '代碼值一律是**小寫英文加底線**，狀態用形容詞或過去分詞；畫面顯示的中文走語言檔（`lang/`），不存進資料庫。';
        $out[] = '';
        $out[] = '| 資料表.欄位 | 值 | 中文 |';
        $out[] = '|---|---|---|';
        foreach ($this->codes as $key => $values) {
            foreach ($values as $code => $zh) {
                $out[] = "| `{$key}` | `{$code}` | {$zh} |";
            }
        }
        $out[] = '';
        $out[] = '## 3. 例外清單（不照規則、但有理由）';
        $out[] = '';
        $out[] = '| 對象 | 不符合的地方 | 理由 |';
        $out[] = '|---|---|---|';
        foreach ($this->exceptions as $row) {
            $out[] = '| ' . implode(' | ', $row) . ' |';
        }
        $out[] = '';
        $out[] = '例外清單同時寫在 `DatabaseNamingConventionTest` 裡；新增例外要兩邊一起改。';
        $out[] = '';
        $out[] = '## 4. 正規化檢查結果';
        $out[] = '';
        $out[] = '檢查標準：第一正規化（一個欄位一個值）、第二正規化（沒有只依賴一部分主鍵的欄位）、第三正規化（沒有可以由別的欄位推出來的欄位）。';
        $out[] = '';
        $out[] = '| 資料表 | 中文名稱 | 結論 |';
        $out[] = '|---|---|---|';
        foreach ($schema as $table => $_) {
            [$zh, , $verdict] = $this->tables[$table] ?? [$table, '', '（尚未補上結論，請到 GenerateSchemaDocs 補）'];
            $out[] = "| `{$table}` | {$zh} | {$verdict} |";
        }
        $out[] = '';
        $out[] = '### 本次整理做過的正規化與統一名稱';
        $out[] = '';
        $out[] = '| 類型 | 內容 |';
        $out[] = '|---|---|';
        foreach ([
            ['1NF', '`roles.permissions`（JSON 陣列）拆成 `role_permissions`（一列一個權限，身分與權限代碼唯一）。'],
            ['3NF', '保養計畫、保養工單指定了設備（`device_id`）後，不再另存 `device_category_id`（由設備推得，儲存時自動清除）。'],
            ['3NF／外鍵', '保養項目、計畫、工單的設備類別：自由文字 → `device_category_id`；保養執行人：文字姓名 → `executed_by`（users.id）；報修單的 `assignee_note` 文字移除，只留 `assigned_to`。'],
            ['完整性', '補上 4 個缺少的外鍵約束（保養工單／計畫的 `device_id`、保養結果的 `repair_request_id`、知識庫的 `created_by`）。'],
            ['名稱統一', '`reporter_id`、`user_id`（預約）→ `requester_id`；`departments.code` → `department_code`；`maintenance_results.notes` → `note`；`repair_part_usage` → `repair_part_usages`；`classroom_reservation_slots.period_no` → `period_number`（序號寫完整單字，與 `serial_number` 一致）。'],
        ] as $row) {
            $out[] = '| ' . implode(' | ', $row) . ' |';
        }
        $out[] = '';
        $out[] = '## 5. 資料表一覽';
        $out[] = '';
        $out[] = '| 資料表 | 中文名稱 | 類型 | 名稱拆解（領域詞＋實體詞） | 欄位數 | 規格書對照 |';
        $out[] = '|---|---|---|---|---|---|';
        foreach ($schema as $table => $info) {
            [$zh, $spec] = $this->tables[$table] ?? [$table, ''];
            [$type, $domain, $entity] = $this->tableNaming[$table] ?? ['（未分類）', '', $table];
            $breakdown = $domain === '' ? "（核心主檔）＋ `{$entity}`" : "`{$domain}` ＋ `{$entity}`";
            $out[] = "| `{$table}` | {$zh} | {$type} | {$breakdown} | " . count($info['columns']) . " | {$spec} |";
        }
        $out[] = '';
        $out[] = '## 6. ERD（資料表關聯）';
        $out[] = '';
        $out[] = '```mermaid';
        $out[] = 'erDiagram';
        $lines = [];
        foreach ($schema as $table => $info) {
            foreach ($info['foreign'] as $column => $target) {
                $lines[] = '    ' . $target['table'] . ' ||--o{ ' . $table . ' : "' . $column . '"';
            }
        }
        sort($lines);
        $out = array_merge($out, $lines);
        $out[] = '```';
        $out[] = '';
        $out[] = '## 7. 完整名稱清單與資料字典';
        $out[] = '';
        $out[] = '欄位順序固定：編號 → 關聯（外鍵）→ 一般欄位 → 時間欄位。「類別」欄是這個名稱適用的規則（對照第 1.2 節）；型態是邏輯型態（整數、文字、布林…），不隨資料庫軟體而變。';
        $out[] = '';
        $out[] = '| 類別 | 適用規則 |';
        $out[] = '|---|---|';
        foreach ([
            ['PK', 'N2'], ['FK-表', 'N3'], ['FK-人', 'N4'], ['布林', 'N5'], ['時間／日期／時刻', 'N6'], ['狀態', 'N7'], ['代碼／名稱／標題', 'N8'], ['文字說明', 'N9'], ['數量／金額', 'N10'], ['多型關聯', 'N11'], ['JSON', 'N12'], ['一般', '無特別規則（名詞，小寫底線）'],
        ] as $row) {
            $out[] = '| ' . implode(' | ', $row) . ' |';
        }

        foreach ($schema as $table => $info) {
            [$zh] = $this->tables[$table] ?? [$table];
            $out[] = '';
            $out[] = "### {$table}　{$zh}";
            $out[] = '';
            $out[] = '| 欄位名稱 | 類別 | 型態 | 必填 | 唯一 | 外鍵 | 說明 |';
            $out[] = '|---|---|---|---|---|---|---|';
            foreach ($info['columns'] as $column) {
                $required = ! $column['nullable'] && $column['name'] !== 'id' ? 'Y' : '';
                $domain = $this->domain($table, $column['name']);
                $out[] = '| `' . $column['name'] . '` | ' . $column['category'] . ' | ' . $this->typeLabel($column) . ' | ' . $required . ' | ' . ($column['unique'] ? 'Y' : '')
                    . ' | ' . ($column['fk'] ? '`' . $column['fk']['table'] . '.' . $column['fk']['column'] . '`' : '') . ' | ' . $this->describe($table, $column['name']) . ($domain ? "（值：{$domain}）" : '') . ' |';
            }
        }
        $out[] = '';

        $out[] = '## 8. 與規格書第 8 章資料字典的差異';
        $out[] = '';
        $out[] = '規格書第 8 章的欄位名稱採「`表名_id` 當主鍵」的寫法；專案用的是 Laravel 慣例（主鍵一律叫 `id`）。';
        $out[] = '彭仕衡決定（2026-10-15）**保留 Laravel 慣例**，只統一專案內部不一致的地方，所以下面這些差異是**刻意保留的**，功能與規格書一致，只是名稱不同。';
        $out[] = '若指導老師要求逐字對齊規格書，要改 migration 與 Model 的 `$primaryKey`，所有外鍵欄位與四位組員的分支都要同步修改，請先全組討論。';
        $out[] = '';
        $out[] = '| 規格書（第 8 章） | 專案實際 | 備註 |';
        $out[] = '|---|---|---|';
        foreach ([
            ['每張表的主鍵 `role_id`、`user_id`、`device_id`…', '全部是 `id`', 'Laravel 慣例'],
            ['`users.account`、`users.password_hash`', '`users.account`、`users.password`', '帳號名稱已對齊；密碼欄位沿用 Laravel 的 password，同樣是雜湊儲存'],
            ['`users.status`（active／inactive）', '`users.is_active`（布林）', '同義'],
            ['`roles` 的權限描述', '`role_permissions`（一列一個權限）', '正規化後的結果，規格書只有文字描述'],
            ['`departments.department_name`', '`departments.name`、`department_code`', '同義'],
            ['`classrooms.room_no`、`room_name`、`room_type`、`status`（active／inactive／repair）', '`classroom_code`、`name`、`type`、`is_active` ＋ `reservation_status`', '依命名規則取名（代碼用 <實體>_code）；「整修中」另以設備異常旗標表示'],
            ['`devices.asset_no`、`serial_no`、`manufacturer`、`warranty_end`', '`asset_code`、`serial_number`、`brand`、`warranty_end_date`', '同義；依命名規則取名（代碼 _code、日期 _date）'],
            ['`devices.status`（normal／idle／repairing／disabled／scrapped）', 'normal／repairing／retired／disabled', '設備狀態完整列舉屬待確認事項，未擅自增減'],
            ['`repair_requests.requester_id`、`affect_class`', '`requester_id`、`is_class_affected`', '提出者已對齊；布林欄位依規則加 is_'],
            ['`repair_requests.repair_no`、`fault_type`、`priority`', '尚無', '規格書欄位尚未實作'],
            ['報修狀態 NEW／ASSIGNED／IN_PROGRESS／WAIT_VERIFY／CLOSED／REOPEN／REJECTED', 'pending／assigned／in_progress／pending_review／completed', '另有「驗收退回回到處理中」；REOPEN、REJECTED 未實作'],
            ['`maintenance_items.category_id`', '`device_category_id`', '已對齊'],
            ['`maintenance_results.filled_by`', '`executed_by`（users.id）', '已改成外鍵'],
            ['`attachments.ref_type`／`ref_id`／`file_name`／`file_path`／`size_kb`／`uploaded_by`', '`attachable_type`／`attachable_id`／`file_name`／`file_path`／`size_bytes`，無 `uploaded_by`', '檔名與路徑已對齊；來源用 Laravel 多型關聯；大小存位元組'],
            ['`audit_logs.table_name`／`record_id`／`ip_address`', '`loggable_type`／`loggable_id`，無 `ip_address`', 'Laravel 多型關聯'],
            ['規格書第 7 章列出的資料表以外', '另有 ' . $this->tablesNotInSpec($schema), '工作計畫指定的模組與後來新增的功能'],
        ] as $row) {
            $out[] = '| ' . implode(' | ', $row) . ' |';
        }

        $out[] = '';
        $out[] = '## 9. 定案紀錄（名稱怎麼決定的、改了什麼）';
        $out[] = '';
        $out[] = '2026-10-25 由彭仕衡授權、依本文件的取名邏輯一次定案；所有名稱都已經套用到資料庫、程式、畫面、種子資料與測試。';
        $out[] = '';
        $out[] = '### 9.1 決定原則（規格書和取名規則衝突時怎麼辦）';
        $out[] = '';
        $out[] = '| 情況 | 決定 |';
        $out[] = '|---|---|';
        $out[] = '| 規格書第 7 章列出的資料表名稱 | 全部沿用，不改 |';
        $out[] = '| 規格書第 8 章的欄位名稱，意思相同且符合取名規則 | 沿用規格書的名稱（`account`、`file_name`、`file_path`、`mime_type`、`requester_id`…） |';
        $out[] = '| 規格書的欄位名稱違反取名規則（`room_no` 不是 `_code`、`warranty_end` 沒有 `_date`、`affect_class` 沒有 `is_`） | 依取名規則重新取名，差異記在第 8 節 |';
        $out[] = '| 規格書沒有的資料表與欄位（知識庫、零件、預約、AI） | 依取名規則取名 |';
        $out[] = '| 主鍵 | 一律 `id`（Laravel 慣例，彭仕衡決定）；外鍵才用 `<表單數>_id` |';
        $out[] = '| 工作計畫與規格文件已經固定的名稱（`knowledge_base`） | 不改，列為例外 |';
        $out[] = '';
        $out[] = '### 9.2 資料表名稱：' . count($schema) . ' 張全部維持';
        $out[] = '';
        $out[] = '逐張對照第 1.5 節的資料表公式後，沒有任何一張需要改名：';
        $out[] = '';
        $out[] = '| 資料表 | 決定 | 理由 |';
        $out[] = '|---|---|---|';
        $out[] = '| `repair_part_usages` | 維持 | 工作計畫稱 `repair_part_usage`（單數）；依規則 T1 統一複數，並定案為「報修（repair）領域的零件使用（part_usages）」，不再改成 `repair_log_parts`，避免和隊友分支再次衝突。 |';
        $out[] = '| `maintenance_results` | 維持 | 規格書第 7 章的名稱；它是保養工單的結果，一對一，不需要 `maintenance_order_` 前綴。 |';
        $out[] = '| `knowledge_base` | 維持（例外） | 工作計畫與規格文件固定使用的名稱。 |';
        $out[] = '| 其餘 ' . (count($schema) - 3) . ' 張 | 維持 | 已符合「［領域詞］＿實體詞（複數）」。 |';
        $out[] = '';
        $out[] = '### 9.3 欄位與資料值的改名對照（全部已套用）';
        $out[] = '';
        $out[] = '| 範圍 | 舊名稱 | 新名稱 | 為什麼 |';
        $out[] = '|---|---|---|---|';
        foreach ([
            ['roles', 'permissions（JSON 陣列）', '資料表 `role_permissions`', '第一正規化：一格只放一個值'],
            ['classroom_reservation_slots', 'period_no', 'period_number', '序號一律寫完整單字 <名詞>_number'],
            ['maintenance_*', 'category／device_category（文字）', 'device_category_id（外鍵）', '關聯用外鍵，不存文字'],
            ['maintenance_results', 'executed_by（姓名文字）', 'executed_by（users.id 外鍵）', '指向人的欄位一律是外鍵'],
            ['maintenance_results', 'notes', 'note', '備註一律單數 note'],
            ['repair_requests', 'assignee_note（文字）', '（移除，只留 assigned_to）', '第三正規化：不存兩份'],
            ['repair_requests', 'reporter_id', 'requester_id', '「提出者」全專案只有一種叫法'],
            ['classroom_reservations', 'user_id', 'requester_id', '同上'],
            ['departments', 'code', 'department_code', '業務代碼一律 <實體>_code'],
            ['roles', 'slug', 'role_code', '同上'],
            ['classrooms', 'room_code', 'classroom_code', '同上；教室一律叫 classroom，不用 room'],
            ['classrooms', 'room_name', 'name', '稱呼一律 name'],
            ['classrooms', 'room_type', 'type', '屬性名稱不重複實體名稱'],
            ['users', 'username', 'account', '對齊規格書 users.account'],
            ['devices', 'warranty_until', 'warranty_end_date', '日期欄位用 _date 結尾'],
            ['repair_requests', 'affects_class', 'is_class_affected', '布林欄位用 is_ 開頭'],
            ['attachments', 'original_name', 'file_name', '對齊規格書 attachments.file_name'],
            ['attachments', 'disk_path', 'file_path', '對齊規格書 attachments.file_path'],
            ['repair_part_usage（資料表）', 'repair_part_usage', 'repair_part_usages', '資料表一律複數'],
            ['audit_logs.action 的值', 'login、logout', 'logged_in、logged_out', '動作值一律用過去式'],
        ] as $row) {
            $out[] = '| ' . implode(' | ', $row) . ' |';
        }
        $out[] = '';
        $out[] = '轉換用的 migration：`2026_10_15_100000`（外鍵、重複欄位、requester_id、資料表複數）、`2026_10_22_000001`（權限拆表）、`2026_10_22_000002`（可推導的類別）、`2026_10_22_000003`（department_code、note）、`2026_10_25_000001`（本次最後一批改名與動作值）。舊資料全部轉換、不遺失，每一支都可以退回。';
        return implode("\n", $out) . "\n";
    }

    // ------------------------------------------------------------------ 實體資料字典（資料字典.md，需要 MySQL）

    /**
     * 從 MySQL 的 information_schema 讀出「實際」的欄位型態、預設值、外鍵刪除規則與索引。
     * 文件是用這份資料產生的，所以只有連到 MySQL 時才會產生／檢查（SQLite 沒有這些資訊）。
     *
     * @return array<string, Physical>
     */
    private function readPhysical(): array
    {
        $database = DB::connection()->getDatabaseName();
        $info = [];

        foreach (DB::select('SELECT TABLE_NAME AS t, ENGINE AS e, TABLE_COLLATION AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ? ORDER BY TABLE_NAME', [$database, 'BASE TABLE']) as $row) {
            $info[$row->t] = ['engine' => (string) $row->e, 'collation' => (string) $row->c, 'columns' => [], 'foreign' => [], 'indexes' => []];
        }

        foreach (DB::select('SELECT TABLE_NAME AS t, COLUMN_NAME AS c, COLUMN_TYPE AS ty, IS_NULLABLE AS n, COLUMN_DEFAULT AS d, EXTRA AS x FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, ORDINAL_POSITION', [$database]) as $row) {
            $info[$row->t]['columns'][$row->c] = ['type' => (string) $row->ty, 'nullable' => $row->n === 'YES', 'default' => $row->d === null ? null : (string) $row->d, 'extra' => (string) $row->x];
        }

        foreach (DB::select('SELECT k.TABLE_NAME AS t, k.COLUMN_NAME AS c, k.REFERENCED_TABLE_NAME AS rt, k.REFERENCED_COLUMN_NAME AS rc, r.DELETE_RULE AS dr FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.TABLE_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.TABLE_NAME = k.TABLE_NAME WHERE k.TABLE_SCHEMA = ? AND k.REFERENCED_TABLE_NAME IS NOT NULL ORDER BY k.TABLE_NAME, k.COLUMN_NAME', [$database]) as $row) {
            $info[$row->t]['foreign'][] = ['column' => $row->c, 'table' => $row->rt, 'ref' => $row->rc, 'on_delete' => $row->dr];
        }

        foreach (DB::select('SELECT TABLE_NAME AS t, INDEX_NAME AS i, NON_UNIQUE AS nu, COLUMN_NAME AS c FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX', [$database]) as $row) {
            $info[$row->t]['indexes'][$row->i]['unique'] = ! $row->nu;
            $info[$row->t]['indexes'][$row->i]['columns'][] = $row->c;
        }

        return $info;
    }

    /** 外鍵「刪除上一層資料時」的規則，翻成白話。 */
    private function deleteRule(string $rule): string
    {
        return match ($rule) {
            'CASCADE' => '連帶刪除',
            'SET NULL' => '設為空白',
            default => '禁止刪除',
        };
    }

    /**
     * 這個欄位的「約束」欄文字：主鍵、唯一、外鍵（含刪除規則）、組合唯一。
     *
     * @param  Physical  $physical
     */
    private function constraintText(string $column, array $physical): string
    {
        $parts = [];
        $indexes = $physical['indexes'];

        if (($indexes['PRIMARY']['columns'] ?? []) === [$column]) {
            $parts[] = 'PK';
        }

        foreach ($indexes as $name => $index) {
            if ($name === 'PRIMARY' || ! $index['unique'] || ! in_array($column, $index['columns'], true)) {
                continue;
            }
            $parts[] = count($index['columns']) === 1 ? 'UNI' : '組合唯一(' . implode(', ', $index['columns']) . ')';
        }

        foreach ($physical['foreign'] as $fk) {
            if ($fk['column'] === $column) {
                $parts[] = "FK → `{$fk['table']}.{$fk['ref']}`（刪除時{$this->deleteRule($fk['on_delete'])}）";
            }
        }

        return implode('<br>', $parts);
    }

    /**
     * 這張表「額外」的索引（主鍵、單欄唯一、外鍵自動建的索引都不重複列）。
     *
     * @param  Physical  $physical
     * @return list<string>
     */
    private function extraIndexes(array $physical): array
    {
        $foreignColumns = array_column($physical['foreign'], 'column');
        $lines = [];

        foreach ($physical['indexes'] as $name => $index) {
            if ($name === 'PRIMARY' || ($index['unique'] && count($index['columns']) === 1)) {
                continue;
            }
            if (! $index['unique'] && count($index['columns']) === 1 && in_array($index['columns'][0], $foreignColumns, true)) {
                continue;
            }
            $lines[] = ($index['unique'] ? '組合唯一 ' : '') . '`' . implode('`＋`', $index['columns']) . '`';
        }

        sort($lines);

        return $lines;
    }

    /**
     * 預設值顯示：沒有預設值留白；字串去掉多餘的引號。
     *
     * @param  array{type: string, nullable: bool, default: ?string, extra: string}  $column
     */
    private function defaultText(array $column): string
    {
        if ($column['default'] === null) {
            return str_contains($column['extra'], 'auto_increment') ? '自動編號' : '';
        }

        return '`' . trim($column['default'], "'") . '`';
    }

    /**
     * 一張資料表的欄位表（含說明）。
     *
     * @param  Physical  $physical
     * @param  list<array<string, mixed>>|null  $logicalColumns
     * @return list<string>
     */
    private function physicalTable(string $table, array $physical, ?array $logicalColumns): array
    {
        // 順序：有邏輯欄位順序（業務資料表）就照「編號 → 關聯 → 一般 → 時間」，沒有（Laravel 內建表）就照資料庫裡的順序。
        $names = $logicalColumns !== null ? array_column($logicalColumns, 'name') : array_keys($physical['columns']);

        $out = [];
        $out[] = '| 欄位 | 型態 | 允許空白 | 預設值 | 約束 | 說明 |';
        $out[] = '|---|---|---|---|---|---|';
        foreach ($names as $name) {
            $column = $physical['columns'][$name];
            $domain = $this->domain($table, $name);
            $description = $this->describe($table, $name) . ($domain ? "（值：{$domain}）" : '');
            $out[] = '| `' . $name . '` | `' . $column['type'] . '` | ' . ($column['nullable'] ? 'Y' : '') . ' | ' . $this->defaultText($column) . ' | ' . $this->constraintText($name, $physical) . ' | ' . $description . ' |';
        }

        $indexes = $this->extraIndexes($physical);
        if ($indexes) {
            $out[] = '';
            $out[] = '其他索引：' . implode('；', $indexes);
        }

        return $out;
    }

    /**
     * 一個模組內的資料表關聯圖（不畫指向 users 的線，人員欄位見欄位表的外鍵）。
     *
     * @param  list<string>  $tables
     * @param  array<string, Physical>  $physicalAll
     * @return list<string>
     */
    private function moduleErd(array $tables, array $physicalAll): array
    {
        $lines = [];
        foreach ($tables as $table) {
            foreach ($physicalAll[$table]['foreign'] as $fk) {
                if ($fk['table'] !== 'users') {
                    $lines[] = '    ' . $fk['table'] . ' ||--o{ ' . $table . ' : "' . $fk['column'] . '"';
                }
            }
        }
        $lines = array_values(array_unique($lines));
        sort($lines);

        return $lines ? array_merge(['```mermaid', 'erDiagram'], $lines, ['```']) : [];
    }

    /**
     * 資料字典（實體欄位版）：依業務模組分章，每張表有用途、欄位型態、預設值、約束與說明。
     *
     * @param  SchemaMap  $schema
     */
    private function physicalDocument(array $schema): string
    {
        $physical = $this->readPhysical();
        $framework = array_values(array_filter(array_keys($physical), fn ($t) => in_array($t, self::FRAMEWORK_TABLES, true)));
        sort($framework);

        $businessTables = array_keys($schema);
        $columnCount = array_sum(array_map(fn ($t) => count($physical[$t]['columns']), $businessTables));
        $foreignCount = array_sum(array_map(fn ($t) => count($physical[$t]['foreign']), $businessTables));
        $grouped = array_merge(...array_map(fn ($m) => $m[1], array_values($this->modules)));
        $ungrouped = array_values(array_diff($businessTables, $grouped));
        $first = $businessTables[0];

        $out = [];
        $out[] = '# 資料字典';
        $out[] = '';
        $out[] = '> 每一張資料表的**實際**欄位型態、長度、是否可空白、預設值、主鍵／唯一／外鍵（含刪除規則）與索引。';
        $out[] = '> 內容從 MySQL 的 `information_schema` **自動產生**（`php artisan docs:schema`，需連到 MySQL）；資料庫異動請改 migration 再重新產生。';
        $out[] = '> 文件過期時，GitHub 上的「MySQL 資料庫檢查」會失敗（`php artisan docs:schema --check`）。';
        $out[] = '> 命名規則、正規化結論與和規格書的差異，見同資料夾的《資料庫命名規範與資料字典》；系統整體設計見《系統架構》。';
        $out[] = '';
        $out[] = '## 1. 概況';
        $out[] = '';
        $out[] = '| 項目 | 內容 |';
        $out[] = '|---|---|';
        $out[] = '| 資料庫 | MySQL 8.4（' . $physical[$first]['engine'] . '，定序 ' . $physical[$first]['collation'] . '） |';
        $out[] = '| 業務資料表 | ' . count($businessTables) . ' 張（另有 Laravel 內建 ' . count($framework) . ' 張，見附錄） |';
        $out[] = '| 欄位總數 | ' . $columnCount . '（業務資料表） |';
        $out[] = '| 外鍵約束 | ' . $foreignCount . ' 條（見第 4 節） |';
        $out[] = '| 主鍵 | 一律是 `id`（自動編號的大整數） |';
        $out[] = '| 時間 | 系統時區 Asia/Taipei；`created_at`／`updated_at` 由 Laravel 自動維護 |';
        $out[] = '| 軟刪除 | 有 `deleted_at` 的資料表（用戶、教室、設備、報修單、保養工單）刪除只是填上時間，資料保留供履歷追溯 |';
        $out[] = '';
        $out[] = '### 怎麼讀欄位表';
        $out[] = '';
        $out[] = '| 欄位 | 意思 |';
        $out[] = '|---|---|';
        $out[] = '| 型態 | MySQL 實際型態。`bigint unsigned`＝大整數（不能是負數）；`varchar(255)`＝最長 255 字的文字；`tinyint(1)`＝布林（0／1）；`timestamp`＝日期時間；`text`／`json` 見字面 |';
        $out[] = '| 允許空白 | Y＝可以沒有值（NULL）；空白＝一定要有值 |';
        $out[] = '| 預設值 | 新增資料沒有指定時，資料庫自動填的值；空白＝沒有預設值 |';
        $out[] = '| 約束 | PK 主鍵；UNI 不可重複；FK 外鍵（指向哪張表、被指的那筆刪掉時怎麼辦）；組合唯一＝幾個欄位合起來不可重複 |';
        $out[] = '| 刪除規則 | **連帶刪除**＝上層刪了，這筆跟著刪；**設為空白**＝上層刪了，這個欄位變空白、這筆保留；**禁止刪除**＝還有資料指向它時，上層不能刪 |';
        $out[] = '';
        $out[] = '## 2. 資料表分組總覽';
        $out[] = '';
        $out[] = '| 模組 | 資料表 | 中文名稱 | 用途 |';
        $out[] = '|---|---|---|---|';
        foreach ($this->modules as $module => [, $tables]) {
            $tables = array_values(array_filter($tables, fn ($t) => isset($schema[$t])));
            foreach ($tables as $i => $table) {
                $out[] = '| ' . ($i === 0 ? "**{$module}**" : '') . ' | `' . $table . '` | ' . ($this->tables[$table][0] ?? $table) . ' | ' . ($this->purposes[$table] ?? '') . ' |';
            }
        }
        foreach ($ungrouped as $table) {
            $out[] = '| （未分組） | `' . $table . '` | ' . ($this->tables[$table][0] ?? $table) . ' | ' . ($this->purposes[$table] ?? '') . ' |';
        }

        $out[] = '';
        $out[] = '## 3. 各模組的資料字典';

        $number = 0;
        foreach ($this->modules as $module => [$intro, $tables]) {
            $tables = array_values(array_filter($tables, fn ($t) => isset($schema[$t])));
            if (! $tables) {
                continue;
            }
            $number++;
            $out[] = '';
            $out[] = "### 3.{$number} {$module}";
            $out[] = '';
            $out[] = $intro . '。';
            $erd = $this->moduleErd($tables, $physical);
            if ($erd) {
                $out[] = '';
                $out = array_merge($out, $erd);
                $out[] = '';
                $out[] = '（圖中不畫指向 `users` 的線；提出者、指派者、審核人等人員欄位都在欄位表的外鍵裡。）';
            }
            foreach ($tables as $table) {
                $out[] = '';
                $out[] = "#### {$table}　" . ($this->tables[$table][0] ?? $table);
                $out[] = '';
                $out[] = '**用途**：' . ($this->purposes[$table] ?? '');
                $out[] = '';
                $out = array_merge($out, $this->physicalTable($table, $physical[$table], $schema[$table]['columns']));
            }
        }

        foreach ($ungrouped as $table) {
            $out[] = '';
            $out[] = "#### {$table}　（未分組）";
            $out[] = '';
            $out = array_merge($out, $this->physicalTable($table, $physical[$table], $schema[$table]['columns']));
        }

        $out[] = '';
        $out[] = '## 4. 外鍵與刪除規則一覽';
        $out[] = '';
        $out[] = '共 ' . $foreignCount . ' 條。「連帶刪除」的只有「明細跟著主檔」這類（例如刪預約申請，底下的時段一起刪）；指向人與主檔的外鍵多半是「設為空白」，讓歷史資料不會因為帳號或設備被移除而消失。';
        $out[] = '';
        $out[] = '| 資料表 | 欄位 | 指向 | 刪除上層時 |';
        $out[] = '|---|---|---|---|';
        foreach ($businessTables as $table) {
            foreach ($physical[$table]['foreign'] as $fk) {
                $out[] = "| `{$table}` | `{$fk['column']}` | `{$fk['table']}.{$fk['ref']}` | {$this->deleteRule($fk['on_delete'])} |";
            }
        }

        $out[] = '';
        $out[] = '## 5. 代碼值（資料庫裡真正存的值）';
        $out[] = '';
        $out[] = '| 資料表.欄位 | 值 | 中文 |';
        $out[] = '|---|---|---|';
        foreach ($this->codes as $key => $values) {
            foreach ($values as $code => $zh) {
                $out[] = "| `{$key}` | `{$code}` | {$zh} |";
            }
        }
        $out[] = '';
        $out[] = '代碼值一律是小寫英文加底線；畫面顯示的中文走語言檔（`lang/`），不存進資料庫。權限代碼（`role_permissions.permission`）的完整清單在 `app/Support/PermissionCatalog.php`。';

        $out[] = '';
        $out[] = '## 附錄　Laravel 內建資料表（' . count($framework) . ' 張）';
        $out[] = '';
        $out[] = '框架自己用的表，不是業務資料；列在這裡是為了讓資料字典完整。';
        foreach ($framework as $table) {
            $out[] = '';
            $out[] = "#### {$table}";
            $out[] = '';
            $out[] = '**用途**：' . ($this->frameworkPurposes[$table] ?? '');
            $out[] = '';
            $out = array_merge($out, $this->physicalTable($table, $physical[$table], null));
        }

        return implode("\n", $out) . "\n";
    }
}
