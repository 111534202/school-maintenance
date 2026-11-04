<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * 從「實際的資料庫結構」產生一份文件 docs/資料庫命名規範與資料字典.md（命名邏輯、代碼值、例外、正規化結論、
 * 資料表一覽、ERD、完整名稱清單與資料字典、與規格書的差異），確保文件和資料庫永遠一致。
 * 用法：
 *   php artisan docs:schema           重新產生（資料表或欄位改了以後執行）
 *   php artisan docs:schema --check   只檢查文件是不是最新（不寫檔；測試會用它，文件過期就失敗）
 * 欄位的中文說明、資料表的中文名稱與正規化結論是這個檔案裡的文字（下面的 $columnText、$tables），新增欄位時請一併補上。
 * 欄位順序固定成「編號 → 關聯 → 一般欄位 → 時間欄位」，欄位型態用「邏輯型態」（整數、文字、布林…），
 * 這樣不管在 MySQL 或測試用的 SQLite 產生出來都一樣。
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
        'users' => ['使用者主檔', '第 7 章 users', '符合 3NF。一人一個身分（role_id），所屬單位以 department_id 關聯。'],
        'classrooms' => ['教室主檔', '第 7 章 classrooms', '大致符合 3NF，有兩個有意保留的例外：campus／building／floor 依規格書 4.1 直接存在教室上（同一大樓的多間教室會重複同一段文字）；reservation_status 是由「核心設備狀態」推得的快取旗標，由 DeviceStatusService 維護，避免每次列表都重算（是否保留屬待確認事項）。'],
        'device_categories' => ['設備類別', '第 7 章 device_categories', '符合 3NF。'],
        'devices' => ['設備主檔', '第 7 章 devices', '符合 3NF。類別與教室只存外鍵；brand／model 依規格書是文字欄位。'],
        'maintenance_items' => ['保養項目', '第 7 章 maintenance_items', '符合 3NF。適用設備類別以 device_category_id 關聯（原本是自由文字）。'],
        'maintenance_plans' => ['保養計畫', '第 7 章 maintenance_plans', '符合 3NF。針對「一台設備」或「一類設備」二選一，指定設備後類別由設備推得、不另存。next_due_date 是由 start_date 加週期推得的值，排程要用，所以保留。'],
        'maintenance_plan_items' => ['保養計畫明細', '第 7 章 maintenance_plan_items', '純關聯表（多對多），符合 2NF／3NF。'],
        'maintenance_orders' => ['保養工單', '第 7 章 maintenance_orders', '符合 3NF（設備與類別二選一，理由同保養計畫）。'],
        'maintenance_results' => ['保養結果', '第 7 章 maintenance_results', '符合 3NF。執行人以 executed_by（users.id）關聯，不再存姓名文字。ng_conversion_status 與 repair_request_id 有部分重疊（converted 代表已有報修單），因為還需要區分「待轉」與「不需轉」兩種沒有報修單的情況，所以保留。'],
        'repair_requests' => ['報修單', '第 7 章 repair_requests', '大致符合 3NF。維修人員只存 assigned_to（原本另存 assignee_note 文字，已移除）。device_note／location 是「沒有綁定設備」時的後備文字，有綁設備時可由設備與教室推得（保留為歷史與手動輸入用）。'],
        'repair_logs' => ['維修紀錄', '第 7 章 repair_logs', '大致符合 3NF。total_hours 是由起訖時間推得的值，規格書要求存起來所以保留；parts_used_note 是自由文字備註，結構化的備品使用已改存 repair_part_usages（舊資料與額外說明仍可寫在這裡）。'],
        'repair_part_usages' => ['維修零件使用明細', '規格書未列（備品使用的結構化）', '符合 3NF。unit_price 是「使用當下的單價快照」，刻意和 parts.unit_price 分開存（零件日後調價不影響歷史成本），不算重複。'],
        'attachments' => ['附件資料', '第 7 章 attachments', '符合 3NF。用 Laravel 多型關聯（attachable_type + attachable_id）指向報修單、維修紀錄等，不加外鍵。'],
        'audit_logs' => ['操作日誌', '第 7 章 audit_logs', '日誌資料。changes 是 JSON：記錄「當時」的變更內容快照，不需要單獨查詢內容，所以刻意不拆成欄位（1NF 的有意例外）。'],
        'knowledge_base' => ['自助知識庫文章', '規格書未列（工作計畫指定模組）', '符合 3NF。category 是自由文字（沒有獨立的分類表）；值很少、也沒有跨表查詢需求，若日後分類變多再拆成獨立資料表。'],
        'parts' => ['零件庫存', '規格書未列（備品庫存模組）', '符合 3NF。'],
        'classroom_reservations' => ['教室預約申請', '規格書未列（空間預約模組）', '符合 3NF。'],
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
        'attachments' => ['附屬（多型）', '', 'attachments'],
        'audit_logs' => ['紀錄', 'audit', 'logs'],
        'knowledge_base' => ['主檔（例外）', '', 'knowledge_base'],
        'parts' => ['主檔', '', 'parts'],
        'classroom_reservations' => ['單據', 'classroom', 'reservations'],
        'ai_settings' => ['設定', 'ai', 'settings'],
        'preventive_candidates' => ['單據', 'preventive', 'candidates'],
    ];

    /** 領域詞清單：資料表名稱的前半段只能用這些（每個詞的意思見文件 1.5 節）；新增領域詞要先加在這裡。 */
    public const DOMAIN_WORDS = [
        'ai' => 'AI 預測性維護', 'audit' => '稽核與操作日誌', 'classroom' => '教室與空間預約', 'device' => '設備',
        'maintenance' => '定期保養', 'preventive' => '預防保養（AI 候選）', 'repair' => '報修與維修', 'role' => '身分與權限',
    ];

    /**
     * 欄位的中文說明：同名欄位共用，個別資料表不同的用「資料表.欄位」覆蓋。
     *
     * @var array<string, string>
     */
    private array $columnText = [
        'id' => '系統自動編號（主鍵）', 'created_at' => '建立時間', 'updated_at' => '最後修改時間', 'deleted_at' => '軟刪除時間（有值代表已停用／刪除，資料保留供履歷追溯）',
        'name' => '名稱', 'description' => '說明', 'is_active' => '是否啟用', 'status' => '狀態', 'title' => '標題', 'email' => '電子信箱', 'phone' => '電話',
        'role_id' => '所屬身分', 'department_id' => '所屬單位', 'account' => '登入帳號（唯一）', 'password' => '密碼（雜湊後儲存，不存明碼）', 'last_login_at' => '最後登入時間',
        'remember_token' => '「記住我」登入憑證', 'email_verified_at' => 'Email 驗證時間',
        'role_code' => '身分代碼（程式判斷用，唯一）', 'is_system' => '是否系統內建身分（內建的不能刪除）', 'permission' => '權限代碼（格式「功能.動作」，清單見 PermissionCatalog）',
        'department_code' => '部門代碼（唯一）', 'campus' => '校區', 'building' => '大樓', 'floor' => '樓層', 'classroom_code' => '教室代碼（唯一）',
        'manager_id' => '教室管理人（users.id）', 'reservation_status' => '設備異常旗標（normal 正常／abnormal 有核心設備異常，異常時不開放預約）',
        'device_code' => '設備管制編號（唯一，QR Code 內容）', 'asset_code' => '資產編號', 'device_category_id' => '設備類別（device_categories.id）', 'brand' => '品牌／製造商', 'model' => '型號規格',
        'serial_number' => '序號', 'warranty_end_date' => '保固到期日', 'classroom_id' => '所在教室（classrooms.id）', 'is_core' => '是否核心設備（異常時整間教室標示異常）',
        'category' => '分類（自由文字）', 'symptom' => '常見故障現象', 'solution' => '自助排除步驟', 'is_published' => '是否上架（未上架僅知識庫管理者可見）', 'created_by' => '建立者（users.id）',
        'default_cycle_days' => '預設保養週期（天）', 'cycle_days' => '保養週期（天）', 'start_date' => '基準日期（排程計算起點）', 'next_due_date' => '下次到期日',
        'maintenance_plan_id' => '來源保養計畫', 'maintenance_item_id' => '保養項目', 'device_id' => '設備（devices.id）', 'source' => '來源', 'scheduled_date' => '預定保養日',
        'maintenance_order_id' => '所屬保養工單', 'result' => '結果', 'executed_by' => '執行人（users.id，由登入帳號帶入）', 'executed_at' => '執行時間', 'note' => '備註（操作人員填寫）', 'ng_conversion_status' => 'NG 轉報修狀態',
        'repair_request_id' => '報修單（repair_requests.id）', 'impact_level' => '影響程度', 'is_class_affected' => '是否影響上課', 'rejection_reason' => '驗收退回原因',
        'requester_id' => '提出者（users.id）：報修人／預約申請人', 'device_note' => '設備說明（手動輸入、沒有綁設備時用）', 'assigned_to' => '指派的維修人員（users.id）', 'scheduled_at' => '預計處理時間', 'location' => '地點（文字）',
        'cause' => '故障原因', 'resolution' => '處置方式', 'parts_used_note' => '使用備品說明（文字備註）', 'started_at' => '起始時間', 'ended_at' => '結束時間', 'total_hours' => '總工時（系統計算）',
        'attachable_type' => '附件所屬資料類型（多型）', 'attachable_id' => '附件所屬資料編號（多型）', 'file_path' => '檔案儲存路徑（私有磁碟）', 'file_name' => '原始檔名（使用者上傳時的檔名）', 'mime_type' => '檔案類型', 'size_bytes' => '檔案大小（位元組）',
        'user_id' => '操作人（users.id）', 'action' => '操作動作', 'loggable_type' => '被操作資料類型（多型）', 'loggable_id' => '被操作資料編號（多型）', 'changes' => '變更內容（JSON 快照）',
        'specification' => '規格', 'unit_price' => '單價', 'current_stock' => '目前庫存', 'safety_stock' => '安全庫存', 'repair_log_id' => '所屬維修紀錄', 'part_id' => '零件（parts.id）', 'quantity' => '使用數量',
        'class_name' => '班級名稱', 'reservation_date' => '預約日期', 'start_time' => '開始時間', 'end_time' => '結束時間', 'reviewed_by' => '審核人（users.id）', 'reviewed_at' => '審核時間', 'purpose' => '用途',
        'key' => '參數名稱', 'value' => '參數值', 'risk_score' => '風險分數（0～1）', 'threshold' => '當時的門檻', 'algorithm' => '評分演算法版本', 'explanation' => '評分明細（JSON 快照）', 'features' => '輸入特徵（JSON 快照）',
        'decided_by' => '審核人（users.id）', 'decided_at' => '審核時間', 'decision_note' => '審核備註',
    ];

    /** @var array<string, string> 個別資料表裡意思不同的欄位 */
    private array $columnTextSpecific = [
        'roles.name' => '身分名稱', 'classrooms.name' => '教室名稱', 'classrooms.type' => '教室類型', 'departments.name' => '單位名稱', 'device_categories.name' => '類別名稱（唯一）', 'maintenance_items.name' => '項目名稱', 'maintenance_plans.name' => '計畫名稱', 'parts.name' => '零件名稱',
        'repair_requests.description' => '故障描述', 'repair_requests.title' => '報修標題', 'maintenance_items.description' => '說明',
    ];

    /**
     * 代碼值：欄位 => [值 => 中文]。這些是資料庫裡真正存的值（小寫英文加底線），畫面顯示的中文走語言檔。
     *
     * @var array<string, array<string, string>>
     */
    private array $codes = [
        'repair_requests.status' => ['pending' => '新報修', 'assigned' => '已派工', 'in_progress' => '處理中', 'pending_review' => '待驗收', 'completed' => '已結案'],
        'repair_requests.impact_level' => ['low' => '輕微', 'medium' => '中等', 'high' => '嚴重'],
        'devices.status' => ['normal' => '正常', 'repairing' => '維修中', 'retired' => '已淘汰', 'disabled' => '停用'],
        'classrooms.reservation_status' => ['normal' => '正常（可預約）', 'abnormal' => '有核心設備異常（不開放預約）'],
        'classroom_reservations.status' => ['pending' => '待審核', 'approved' => '已核准', 'rejected' => '已駁回'],
        'maintenance_orders.status' => ['pending' => '待處理', 'in_progress' => '進行中', 'completed' => '已完成'],
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

    private function describe(string $table, string $column): string
    {
        return $this->columnTextSpecific["{$table}.{$column}"] ?? $this->columnText[$column] ?? '';
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
        $out[] = '> 這是**唯一一份**資料庫文件：命名邏輯、代碼值、例外、正規化結論、資料表一覽、ERD、完整名稱清單與資料字典，以及和規格書的差異都在這裡。';
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
            ['名稱統一', '`reporter_id`、`user_id`（預約）→ `requester_id`；`departments.code` → `department_code`；`maintenance_results.notes` → `note`；`repair_part_usage` → `repair_part_usages`。'],
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
            ['規格書第 7 章 15 張表以外', '另有 `knowledge_base`、`parts`、`repair_part_usages`、`classroom_reservations`、`ai_settings`、`preventive_candidates`', '工作計畫指定的模組'],
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
        $out[] = '### 9.2 資料表名稱：22 張全部維持';
        $out[] = '';
        $out[] = '逐張對照第 1.5 節的資料表公式後，沒有任何一張需要改名：';
        $out[] = '';
        $out[] = '| 資料表 | 決定 | 理由 |';
        $out[] = '|---|---|---|';
        $out[] = '| `repair_part_usages` | 維持 | 工作計畫稱 `repair_part_usage`（單數）；依規則 T1 統一複數，並定案為「報修（repair）領域的零件使用（part_usages）」，不再改成 `repair_log_parts`，避免和隊友分支再次衝突。 |';
        $out[] = '| `maintenance_results` | 維持 | 規格書第 7 章的名稱；它是保養工單的結果，一對一，不需要 `maintenance_order_` 前綴。 |';
        $out[] = '| `knowledge_base` | 維持（例外） | 工作計畫與規格文件固定使用的名稱。 |';
        $out[] = '| 其餘 19 張 | 維持 | 已符合「［領域詞］＿實體詞（複數）」。 |';
        $out[] = '';
        $out[] = '### 9.3 欄位與資料值的改名對照（全部已套用）';
        $out[] = '';
        $out[] = '| 範圍 | 舊名稱 | 新名稱 | 為什麼 |';
        $out[] = '|---|---|---|---|';
        foreach ([
            ['roles', 'permissions（JSON 陣列）', '資料表 `role_permissions`', '第一正規化：一格只放一個值'],
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
}
