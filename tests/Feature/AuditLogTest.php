<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\KnowledgeBase;
use App\Models\RepairRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/** 操作紀錄：各模組的操作都有寫入、說明看得懂、登入時間用台北時區。 */
class AuditLogTest extends TestCase
{
    // 每個測試開始前都重建一份乾淨的資料庫。
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    private User $admin;

    // 每個測試開始前先登入一位系統管理員，並記在 $this->admin。
    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->loginAsAnyUser();
    }

    // 輔助方法：取出某個事件最新的一筆操作紀錄。
    private function lastLog(string $action): ?AuditLog
    {
        return AuditLog::where('action', $action)->latest('id')->first();
    }

    // ---------- 時區與最後登入時間 ----------

    // 系統時區是台北（+08:00），最後登入時間與操作紀錄的時間才會是當地時間。
    public function test_application_uses_the_taipei_timezone(): void
    {
        $this->assertSame('Asia/Taipei', config('app.timezone'));
        $this->assertSame('+08:00', now()->format('P'));
    }

    // 登入後「最後登入時間」就是現在的台北時間，並顯示在用戶主檔列表。
    public function test_last_login_time_is_recorded_in_local_time_and_shown_in_the_user_list(): void
    {
        auth()->logout();
        $user = $this->makeTechnician();
        $user->update(['username' => 'timer', 'password' => 'pass-word-1']);

        $this->post(route('login'), ['login' => 'timer', 'password' => 'pass-word-1'])->assertRedirect('/dashboard');

        $loggedAt = $user->fresh()->last_login_at;
        $this->assertTrue($loggedAt->diffInSeconds(now(), true) < 5, '最後登入時間應該就是「現在」');
        $this->assertSame('+08:00', $loggedAt->format('P'));

        $this->actingAs($this->admin);
        $this->get(route('users.index'))->assertSee($loggedAt->format('Y-m-d H:i'));
    }

    // ---------- 登入／登出 ----------

    // 登入、登出、登入失敗都有記錄；失敗紀錄不會包含輸入的密碼。
    public function test_login_logout_and_failed_login_are_logged_without_the_password(): void
    {
        auth()->logout();
        $user = $this->makeTechnician();
        $user->update(['username' => 'audited', 'password' => 'right-pass-1', 'name' => '稽核對象']);

        $this->post(route('login'), ['login' => 'audited', 'password' => 'WRONG-secret-xyz']);
        $failed = $this->lastLog('login_failed');
        $this->assertNull($failed->user_id);
        $this->assertStringContainsString('audited', $failed->description);
        $this->assertStringNotContainsString('WRONG-secret-xyz', json_encode($failed->toArray()));

        $this->post(route('login'), ['login' => 'audited', 'password' => 'right-pass-1']);
        $this->assertSame('稽核對象 登入系統', $this->lastLog('login')->description);
        $this->assertSame($user->id, $this->lastLog('login')->user_id);

        $this->post(route('logout'));
        $this->assertSame('稽核對象 登出系統', $this->lastLog('logout')->description);
    }

    // ---------- 說明：寫入當下的快照 ----------

    // 操作說明自動產生（例如「刪除 用戶「王小明」」），而且之後那個人被徹底刪掉，紀錄上仍看得到當時的名字。
    public function test_description_is_generated_automatically_and_survives_the_subject_being_deleted(): void
    {
        $victim = $this->makeTechnician();
        $victim->update(['name' => '王小明', 'username' => 'wang']);

        $this->delete(route('users.destroy', $victim));

        $log = $this->lastLog('deleted');
        $this->assertSame('刪除 用戶「王小明」', $log->description);
        $this->assertSame($this->admin->id, $log->user_id);

        // 就算之後那個人被徹底刪掉或改名，操作紀錄上看到的還是當時的名字
        $victim->forceDelete();
        $this->get(route('audit-logs.index'))->assertSee('刪除 用戶「王小明」')->assertSee('刪除')->assertDontSee('>deleted<', false);
    }

    // ---------- 報修流程 ----------

    // 報修全流程（新增、派工、開始處理、填維修紀錄、驗收退回、結案）每一步都有留下中文說明的紀錄。
    public function test_the_whole_repair_flow_leaves_an_audit_trail(): void
    {
        Mail::fake();
        $technician = $this->makeTechnician();
        $technician->update(['name' => '陳大成']);

        $this->post(route('repairs.store'), [
            'title' => 'A101 投影機壞了', 'description' => '開不了機', 'impact_level' => 'high', 'affects_class' => '1',
        ]);
        $case = RepairRequest::firstWhere('title', 'A101 投影機壞了');
        $this->assertSame('新增報修單「A101 投影機壞了」', $this->lastLog('created')->description);

        $this->post(route('repairs.assign', $case), ['assigned_to' => $technician->id]);
        $assigned = $this->lastLog('assigned');
        $this->assertSame('派工給「陳大成」，報修單「A101 投影機壞了」', $assigned->description);
        $this->assertSame(['pending', 'assigned'], [$assigned->changes['from'], $assigned->changes['to']]);

        $this->post(route('repairs.start', $case));
        $this->assertSame('報修單「A101 投影機壞了」狀態：已派工 → 處理中', $this->lastLog('status_changed')->description);

        $this->post(route('repair-logs.store', $case), [
            'cause' => '燈泡壞了', 'resolution' => '換新燈泡',
            'started_at' => now()->subHours(2)->format('Y-m-d\TH:i'), 'ended_at' => now()->format('Y-m-d\TH:i'),
        ]);
        $repairLog = AuditLog::where('loggable_type', 'App\Models\RepairLog')->latest('id')->first();
        $this->assertStringContainsString('填寫維修紀錄', $repairLog->description);
        $this->assertStringContainsString('2.00 小時', $repairLog->description);

        $this->post(route('repairs.reject', $case), ['rejection_reason' => '還是會閃']);
        $rejected = $this->lastLog('rejected');
        $this->assertSame('驗收退回報修單「A101 投影機壞了」', $rejected->description);
        $this->assertSame('還是會閃', $rejected->changes['reason']);

        $case->update(['status' => 'pending_review']);
        $this->post(route('repairs.complete', $case));
        $this->assertSame('報修單「A101 投影機壞了」狀態：待驗收 → 已結案', $this->lastLog('status_changed')->description);

        $this->assertSame($this->admin->id, $rejected->user_id);
    }

    // 重新指派會記下新的維修人員。
    public function test_reassigning_is_logged_with_the_new_technician(): void
    {
        Mail::fake();
        $new = $this->makeTechnician();
        $new->update(['name' => '新維修員']);
        $case = RepairRequest::factory()->create(['status' => 'assigned', 'title' => '冷氣不冷']);

        $this->post(route('repairs.reassign', $case), ['assigned_to' => $new->id]);

        $this->assertSame('重新指派給「新維修員」，報修單「冷氣不冷」', $this->lastLog('reassigned')->description);
    }

    // ---------- 知識庫 ----------

    // 知識庫文章的新增、修改、刪除都有記錄。
    public function test_knowledge_base_create_update_delete_are_logged(): void
    {
        $this->post(route('knowledge-base.store'), [
            'title' => '投影機沒畫面', 'symptom' => '黑畫面', 'solution' => '檢查線材', 'is_published' => '1',
        ]);
        $entry = KnowledgeBase::firstWhere('title', '投影機沒畫面');
        $this->assertSame('新增 知識庫文章「投影機沒畫面」', $this->lastLog('created')->description);

        $this->put(route('knowledge-base.update', $entry), [
            'title' => '投影機沒畫面（改）', 'symptom' => '黑畫面', 'solution' => '檢查線材', 'is_published' => '0',
        ]);
        $this->assertSame('修改 知識庫文章「投影機沒畫面（改）」', $this->lastLog('updated')->description);

        $this->delete(route('knowledge-base.destroy', $entry));
        $this->assertSame('刪除 知識庫文章「投影機沒畫面（改）」', $this->lastLog('deleted')->description);
    }

    // ---------- 操作紀錄頁 ----------

    // 操作紀錄頁顯示中文事件名稱，並可依日期範圍與關鍵字篩選。
    public function test_audit_page_shows_chinese_labels_and_filters_by_date_and_keyword(): void
    {
        AuditLogger::log('created', $this->makeTechnician(), [], '新增 用戶「甲」');
        $old = AuditLogger::log('deleted', null, [], '刪除 用戶「乙」');
        $old->forceFill(['created_at' => now()->subDays(10)])->save();

        $page = $this->get(route('audit-logs.index'));
        $page->assertOk()->assertSee('新增 用戶「甲」')->assertSee('刪除 用戶「乙」')->assertSee('新增')->assertSee('系統');

        $this->get(route('audit-logs.index', ['date_from' => now()->subDays(2)->toDateString()]))
            ->assertSee('新增 用戶「甲」')->assertDontSee('刪除 用戶「乙」');

        $this->get(route('audit-logs.index', ['date_to' => now()->subDays(5)->toDateString()]))
            ->assertSee('刪除 用戶「乙」')->assertDontSee('新增 用戶「甲」');

        $this->get(route('audit-logs.index', ['keyword' => '乙']))
            ->assertSee('刪除 用戶「乙」')->assertDontSee('新增 用戶「甲」');
    }

    // 程式裡用到的每個事件與對象類型都有中英文名稱（新增事件卻忘了補翻譯會失敗提醒）。
    public function test_every_audit_action_and_type_used_in_the_code_has_a_label_in_both_languages(): void
    {
        $actions = ['created', 'updated', 'deleted', 'restored', 'status_changed', 'password_reset', 'core_flag_changed',
            'login', 'logout', 'login_failed', 'assigned', 'reassigned', 'rejected'];
        $types = ['User', 'Role', 'Department', 'Classroom', 'Device', 'DeviceCategory', 'RepairRequest', 'RepairLog', 'KnowledgeBase'];

        foreach (['zh_TW', 'en'] as $locale) {
            app()->setLocale($locale);
            foreach ($actions as $action) {
                $this->assertNotSame("audit.actions.$action", __("audit.actions.$action"), "[$locale] 缺少事件翻譯：$action");
            }
            foreach ($types as $type) {
                $this->assertNotSame("audit.types.$type", __("audit.types.$type"), "[$locale] 缺少對象類型翻譯：$type");
            }
        }
        app()->setLocale('zh_TW');
    }
}
