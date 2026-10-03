<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\KnowledgeBase;
use App\Models\RepairRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 四項安全與權限修正的測試：
 * 1. 登入失敗次數限制；
 * 2. 帳號被停用後，下一個請求就會被強制登出（包含靠「記住我」自動登入的情況）；
 * 3. 確認視窗裡的名稱不能跳出 JavaScript 字串（防止注入程式）；
 * 4. 報修單的歸屬權限：誰看得到、誰能處理、誰能驗收（規則見 App\Policies\RepairRequestPolicy）。
 */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    /** 輔助方法：建立一位可用帳號名稱與固定密碼登入的維修人員。 */
    private function makeLoginUser(string $username, string $password = 'right-pass-1'): User
    {
        $user = $this->makeTechnician();
        $user->update(['username' => $username, 'password' => $password]);

        return $user;
    }

    private function failLogin(string $username, int $times): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->post(route('login'), ['login' => $username, 'password' => 'wrong-' . $i]);
        }
    }

    // ---------- 1. 登入限流 ----------

    // 同一個帳號連續失敗 5 次後被鎖定：之後就算輸入正確密碼也不能登入，並提示稍後再試。
    public function test_login_is_locked_after_five_failures_even_with_the_right_password(): void
    {
        $this->makeLoginUser('victim');

        $this->failLogin('victim', 5);
        $response = $this->post(route('login'), ['login' => 'victim', 'password' => 'right-pass-1']);

        $this->assertGuest();
        $response->assertSessionHasErrors('login');
        $this->assertStringContainsString('登入失敗次數過多', session('errors')->first('login'));
    }

    // 鎖定期間被擋下的嘗試不會重複寫進操作紀錄（避免被拿來灌爆紀錄）；只有前 5 次真正的失敗有記。
    public function test_blocked_attempts_are_not_written_to_the_audit_log(): void
    {
        $this->makeLoginUser('victim');

        $this->failLogin('victim', 12);

        $this->assertSame(5, AuditLog::where('action', 'login_failed')->count());
    }

    // 失敗 4 次還沒鎖定，輸入正確密碼可以登入。
    public function test_four_failures_do_not_lock_the_account(): void
    {
        $this->makeLoginUser('victim');

        $this->failLogin('victim', 4);
        $this->post(route('login'), ['login' => 'victim', 'password' => 'right-pass-1']);

        $this->assertAuthenticated();
    }

    // 登入成功會清掉失敗次數：成功之後又失敗 4 次，仍然不會被鎖定。
    public function test_successful_login_resets_the_failure_counter(): void
    {
        $this->makeLoginUser('victim');

        $this->failLogin('victim', 4);
        $this->post(route('login'), ['login' => 'victim', 'password' => 'right-pass-1']);
        $this->post(route('logout'));
        $this->failLogin('victim', 4);
        $this->post(route('login'), ['login' => 'victim', 'password' => 'right-pass-1']);

        $this->assertAuthenticated();
    }

    // 帳號大小寫不同不能用來繞過限制（計數鍵一律轉小寫）。
    public function test_the_limit_cannot_be_bypassed_by_changing_the_letter_case(): void
    {
        $this->makeLoginUser('victim');

        $this->failLogin('victim', 3);
        $this->failLogin('VICTIM', 2);
        $this->post(route('login'), ['login' => 'Victim', 'password' => 'right-pass-1']);

        $this->assertGuest();
    }

    // 鎖定的是「帳號 + IP」：同一個 IP 的失敗次數還沒到上限時，另一個帳號不受影響。
    public function test_locking_one_account_does_not_lock_another_account(): void
    {
        $this->makeLoginUser('victim');
        $this->makeLoginUser('bystander');

        $this->failLogin('victim', 5);
        $this->post(route('login'), ['login' => 'bystander', 'password' => 'right-pass-1']);

        $this->assertAuthenticated();
    }

    // 同一個 IP 換很多不同帳號輪流猜，累積到 20 次失敗後整個 IP 被鎖定（連真的帳號與正確密碼都不行）。
    public function test_one_ip_cannot_try_unlimited_different_accounts(): void
    {
        $this->makeLoginUser('real_user');

        for ($i = 0; $i < 20; $i++) {
            $this->post(route('login'), ['login' => "guess_$i", 'password' => 'x']);
        }
        $this->post(route('login'), ['login' => 'real_user', 'password' => 'right-pass-1']);

        $this->assertGuest();
    }

    // ---------- 2. 停用帳號後強制登出 ----------

    // 已登入的用戶被停用後，他的下一個請求就會被登出並導回登入頁，並且換掉 remember_token
    // （舊的「記住我」cookie 因此失效）。這條檢查對所有登入方式一視同仁，包含靠 cookie 自動登入。
    public function test_a_deactivated_user_is_logged_out_on_the_next_request(): void
    {
        $user = $this->makeLoginUser('leaving');
        $this->actingAs($user);
        $this->get(route('dashboard'))->assertOk();
        $oldToken = $user->fresh()->remember_token;

        $user->update(['is_active' => false]);
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNotSame($oldToken, $user->fresh()->remember_token);
    }

    // 停用後再次登入也不行（原本就有的規則，這裡一併確認沒有被新機制影響）。
    public function test_a_deactivated_user_cannot_log_in_again(): void
    {
        $user = $this->makeLoginUser('leaving');
        $user->update(['is_active' => false]);

        $this->post(route('login'), ['login' => 'leaving', 'password' => 'right-pass-1']);

        $this->assertGuest();
    }

    // ---------- 3. 確認視窗字串注入 ----------

    // 名稱含單引號與程式碼時，確認視窗裡的字串不能被截斷：
    // 瀏覽器解碼 onsubmit 屬性後，除了開頭與結尾兩個定界的單引號，字串裡不能再有任何未跳脫的單引號。
    public function test_confirm_dialogs_cannot_be_broken_out_of_by_a_malicious_name(): void
    {
        $this->loginAsAnyUser();
        $evil = "x');alert(document.domain);//";
        Department::create(['name' => $evil, 'code' => 'EVIL']);
        $this->makeUserWithRole('technician', '維修人員', ['name' => $evil, 'username' => 'evil.one']);
        $deleted = $this->makeUserWithRole('teacher', '教師', ['name' => $evil, 'username' => 'evil.two']);
        $deleted->delete();
        Role::create(['name' => $evil, 'slug' => 'custom_evil', 'is_system' => false, 'permissions' => []]);
        KnowledgeBase::create(['title' => $evil, 'symptom' => 's', 'solution' => 'f', 'is_published' => true]);

        $checked = 0;
        foreach (['departments.index', 'users.index', 'roles.index', 'knowledge-base.index'] as $route) {
            $query = $route === 'users.index' ? ['status' => ''] : [];
            // users.index 預設不含已刪除的帳號，再看一次「已刪除」篩選，才會出現「還原」按鈕。
            foreach ($route === 'users.index' ? [['status' => ''], ['status' => 'deleted']] : [[]] as $query) {
                $html = $this->get(route($route, $query))->getContent();
                $dom = new \DOMDocument();
                @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
                foreach ($dom->getElementsByTagName('form') as $form) {
                    $onsubmit = $form->getAttribute('onsubmit');   // 已經是瀏覽器解碼後的內容
                    if (! str_contains($onsubmit, 'alert')) {
                        continue;
                    }
                    $this->assertMatchesRegularExpression("/^return confirm\\('.*'\\);$/s", $onsubmit);
                    $inner = substr($onsubmit, strlen("return confirm("), -2);   // 去掉 return confirm( 與結尾的 );
                    $this->assertSame(2, substr_count($inner, "'"), "確認視窗字串被截斷：$route -> $onsubmit");
                    $checked++;
                }
            }
        }
        // 部門 2 個（停用、刪除）、用戶 3 個（停用、刪除、還原）、身分 1 個、知識庫 1 個。
        $this->assertSame(7, $checked);
    }

    // ---------- 4. 報修單歸屬權限 ----------

    private function twoTechniciansAndTwoTeachers(): array
    {
        return [
            $this->makeUserWithRole('technician', '維修人員', ['name' => '維修A']),
            $this->makeUserWithRole('technician', '維修人員', ['name' => '維修B']),
            $this->makeUserWithRole('teacher', '教師', ['name' => '教師1']),
            $this->makeUserWithRole('teacher', '教師', ['name' => '教師2']),
        ];
    }

    // 開始處理：只有被指派的維修人員本人；別的維修人員得到 403，案件狀態不變。
    public function test_only_the_assigned_technician_can_start_a_case(): void
    {
        [$techA, $techB] = $this->twoTechniciansAndTwoTeachers();
        $case = RepairRequest::factory()->create(['status' => 'assigned', 'assigned_to' => $techA->id]);

        $this->actingAs($techB)->post(route('repairs.start', $case))->assertForbidden();
        $this->assertSame('assigned', $case->fresh()->status->value);

        $this->actingAs($techA)->post(route('repairs.start', $case))->assertRedirect(route('repairs.show', $case));
        $this->assertSame('in_progress', $case->fresh()->status->value);
    }

    // 填維修紀錄：開啟表單與送出都一樣，只有被指派的維修人員本人；送出被擋時不會留下任何紀錄。
    public function test_only_the_assigned_technician_can_fill_the_repair_log(): void
    {
        [$techA, $techB] = $this->twoTechniciansAndTwoTeachers();
        $case = RepairRequest::factory()->create(['status' => 'in_progress', 'assigned_to' => $techA->id]);
        $payload = [
            'cause' => '原因', 'resolution' => '處置',
            'started_at' => now()->subHour()->format('Y-m-d\TH:i'), 'ended_at' => now()->format('Y-m-d\TH:i'),
        ];

        $this->actingAs($techB)->get(route('repair-logs.create', $case))->assertForbidden();
        $this->post(route('repair-logs.store', $case), $payload)->assertForbidden();
        $this->assertSame(0, $case->fresh()->repairLogs()->count());
        $this->assertSame('in_progress', $case->fresh()->status->value);

        $this->actingAs($techA)->get(route('repair-logs.create', $case))->assertOk();
        $this->post(route('repair-logs.store', $case), $payload)->assertRedirect(route('repairs.show', $case));
        $this->assertSame('pending_review', $case->fresh()->status->value);
    }

    // 驗收通過：只有報修人本人；別的教師得到 403，案件狀態不變。
    public function test_only_the_reporter_can_accept_a_case(): void
    {
        [, , $teacher1, $teacher2] = $this->twoTechniciansAndTwoTeachers();
        $case = RepairRequest::factory()->create(['status' => 'pending_review', 'reporter_id' => $teacher1->id]);

        $this->actingAs($teacher2)->post(route('repairs.complete', $case))->assertForbidden();
        $this->assertSame('pending_review', $case->fresh()->status->value);

        $this->actingAs($teacher1)->post(route('repairs.complete', $case))->assertRedirect(route('repairs.show', $case));
        $this->assertSame('completed', $case->fresh()->status->value);
    }

    // 驗收退回：同樣只有報修人本人；被擋時退回原因不會被寫進去。
    public function test_only_the_reporter_can_reject_a_case(): void
    {
        [, , $teacher1, $teacher2] = $this->twoTechniciansAndTwoTeachers();
        $case = RepairRequest::factory()->create(['status' => 'pending_review', 'reporter_id' => $teacher1->id]);

        $this->actingAs($teacher2)->post(route('repairs.reject', $case), ['rejection_reason' => '亂退'])->assertForbidden();
        $this->assertNull($case->fresh()->rejection_reason);
        $this->assertSame('pending_review', $case->fresh()->status->value);

        $this->actingAs($teacher1)->post(route('repairs.reject', $case), ['rejection_reason' => '還是壞的'])->assertRedirect();
        $this->assertSame('in_progress', $case->fresh()->status->value);
    }

    // 沒有報修人的案件（例如保養 NG 自動轉入）：由有驗收權限的人處理，不然就沒有人能驗收。
    public function test_a_case_without_a_reporter_can_be_accepted_by_anyone_with_the_accept_permission(): void
    {
        [, , $teacher1] = $this->twoTechniciansAndTwoTeachers();
        $case = RepairRequest::factory()->create(['status' => 'pending_review', 'reporter_id' => null, 'assigned_to' => $teacher1->id]);

        $this->actingAs($teacher1)->post(route('repairs.complete', $case))->assertRedirect(route('repairs.show', $case));
        $this->assertSame('completed', $case->fresh()->status->value);
    }

    // 系統管理員不受歸屬限制，什麼案件都能處理與驗收。
    public function test_admin_is_not_limited_by_case_ownership(): void
    {
        [$techA, , $teacher1] = $this->twoTechniciansAndTwoTeachers();
        $this->loginAsAnyUser();
        $assigned = RepairRequest::factory()->create(['status' => 'assigned', 'assigned_to' => $techA->id]);
        $review = RepairRequest::factory()->create(['status' => 'pending_review', 'reporter_id' => $teacher1->id]);

        $this->post(route('repairs.start', $assigned))->assertRedirect();
        $this->post(route('repairs.complete', $review))->assertRedirect();
        $this->assertSame('in_progress', $assigned->fresh()->status->value);
        $this->assertSame('completed', $review->fresh()->status->value);
    }

    // 檢視：教師只看得到自己報修的；別人報修的詳細頁是 403。
    public function test_a_teacher_can_only_open_cases_they_reported(): void
    {
        [, , $teacher1, $teacher2] = $this->twoTechniciansAndTwoTeachers();
        $mine = RepairRequest::factory()->create(['reporter_id' => $teacher1->id]);
        $others = RepairRequest::factory()->create(['reporter_id' => $teacher2->id]);

        $this->actingAs($teacher1);
        $this->get(route('repairs.show', $mine))->assertOk();
        $this->get(route('repairs.show', $others))->assertForbidden();
    }

    // 檢視：維修人員只看得到指派給自己的案件。
    public function test_a_technician_can_only_open_cases_assigned_to_them(): void
    {
        [$techA, $techB] = $this->twoTechniciansAndTwoTeachers();
        $mine = RepairRequest::factory()->create(['assigned_to' => $techA->id]);
        $others = RepairRequest::factory()->create(['assigned_to' => $techB->id]);

        $this->actingAs($techA);
        $this->get(route('repairs.show', $mine))->assertOk();
        $this->get(route('repairs.show', $others))->assertForbidden();
    }

    // 檢視：有派工權限的人（主管、資訊組主管）與管理員看得到全部。
    public function test_dispatchers_and_admin_can_open_every_case(): void
    {
        [, , $teacher1] = $this->twoTechniciansAndTwoTeachers();
        $case = RepairRequest::factory()->create(['reporter_id' => $teacher1->id]);

        foreach ([$this->makeUserWithRole('executive', '主管'), $this->makeItManager(), $this->makeUserWithRole('admin', '系統管理員')] as $viewer) {
            $this->actingAs($viewer)->get(route('repairs.show', $case))->assertOk();
        }
    }

    // 看板列表也套用同樣的範圍：教師只列出自己報修的，主管列出全部。
    public function test_the_board_only_lists_cases_the_user_may_see(): void
    {
        [, , $teacher1, $teacher2] = $this->twoTechniciansAndTwoTeachers();
        RepairRequest::factory()->create(['title' => '教師一的案件', 'reporter_id' => $teacher1->id]);
        RepairRequest::factory()->create(['title' => '教師二的案件', 'reporter_id' => $teacher2->id]);

        $this->actingAs($teacher1)->get(route('repairs.index'))->assertSee('教師一的案件')->assertDontSee('教師二的案件');
        $this->actingAs($this->makeUserWithRole('executive', '主管'))->get(route('repairs.index'))
            ->assertSee('教師一的案件')->assertSee('教師二的案件');
    }

    // 主控台的報修數字同樣只統計看得到的案件，和看板一致。
    public function test_dashboard_repair_numbers_only_count_visible_cases(): void
    {
        [, , $teacher1, $teacher2] = $this->twoTechniciansAndTwoTeachers();
        RepairRequest::factory()->count(2)->create(['status' => 'pending', 'reporter_id' => $teacher1->id]);
        RepairRequest::factory()->count(5)->create(['status' => 'pending', 'reporter_id' => $teacher2->id]);

        $this->actingAs($teacher1)->get(route('dashboard'))->assertSeeInOrder(['待派工', '2']);
        $this->actingAs($this->makeUserWithRole('executive', '主管'))->get(route('dashboard'))->assertSeeInOrder(['待派工', '7']);
    }

    // 通知鈴鐺的「待驗收」只算這位用戶真的能驗收的案件（自己報修的，或沒有報修人的）。
    public function test_bell_counts_only_cases_the_user_can_actually_accept(): void
    {
        [, , $teacher1, $teacher2] = $this->twoTechniciansAndTwoTeachers();
        RepairRequest::factory()->create(['status' => 'pending_review', 'reporter_id' => $teacher1->id]);
        RepairRequest::factory()->create(['status' => 'pending_review', 'reporter_id' => $teacher2->id]);
        RepairRequest::factory()->create(['status' => 'pending_review', 'reporter_id' => null]);

        // 教師 1 能驗收：自己報修的 1 張 + 沒有報修人的 1 張 = 2 張（教師 2 的那張不算）。
        $this->actingAs($teacher1)->get(route('dashboard'))->assertSee('2 張工單待驗收');
    }

    // 詳細頁上的按鈕只在「真的能操作」時才顯示：別的維修人員看不到「開始處理」。
    public function test_buttons_are_hidden_when_the_viewer_may_not_act_on_the_case(): void
    {
        [$techA, $techB] = $this->twoTechniciansAndTwoTeachers();
        $case = RepairRequest::factory()->create(['status' => 'assigned', 'assigned_to' => $techA->id]);

        $this->actingAs($techA)->get(route('repairs.show', $case))->assertSee('開始處理');
        // 維修人員 B 連這張單的詳細頁都打不開，所以不會看到任何按鈕。
        $this->actingAs($techB)->get(route('repairs.show', $case))->assertForbidden();
    }
}
