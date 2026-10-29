<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Department;
use App\Models\Device;
use App\Models\DeviceCategory;
use App\Models\RepairRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/** 主控台（數字卡片 + 可點擊的圖表）、頂部通知鈴鐺、語言切換隱藏、選單的主檔分組。 */
class DashboardAndNotificationTest extends TestCase
{
    // 每個測試開始前都重建一份乾淨的資料庫。
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    // 輔助方法：建立一間教室（標成設備異常）與三台設備（兩台正常、一台維修中）。
    private function makeDevices(): void
    {
        $classroom = Classroom::create([
            'department_id' => Department::create(['name' => '資訊組'])->id, 'campus' => '本校區', 'building' => 'A棟',
            'floor' => '1', 'room_code' => 'A101', 'room_name' => 'A101 教室', 'reservation_status' => 'abnormal',
        ]);
        $category = DeviceCategory::create(['name' => '投影機']);
        foreach (['normal', 'normal', 'repairing'] as $i => $status) {
            Device::create([
                'device_code' => "DEV-$i", 'device_category_id' => $category->id, 'classroom_id' => $classroom->id, 'status' => $status,
            ]);
        }
    }

    // ---------- 主控台 ----------

    // 沒登入的人開主控台，會被導到登入頁。
    public function test_guest_cannot_open_the_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    // 主控台的報修數字正確，而且卡片與圖表都連到「已篩好狀態」的報修看板。
    public function test_dashboard_shows_repair_counts_and_links_to_the_matching_filtered_lists(): void
    {
        $this->loginAsAnyUser();
        RepairRequest::factory()->count(2)->create(['status' => 'pending']);
        RepairRequest::factory()->count(3)->create(['status' => 'pending_review']);
        RepairRequest::factory()->create(['status' => 'in_progress']);
        RepairRequest::factory()->create(['status' => 'completed']);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        // 進行中工單 = 除了已結案以外全部 = 2 + 3 + 1 = 6
        $response->assertSeeInOrder(['進行中工單', '6']);
        $response->assertSeeInOrder(['待派工', '2']);
        $response->assertSeeInOrder(['待驗收', '3']);
        // 卡片與圖表都帶著篩選條件的網址（& 在 JSON 裡會被跳脫，所以分別檢查）
        $response->assertSee(route('repairs.index', ['status' => 'pending']), false);
        $response->assertSee(route('repairs.index', ['status' => 'pending_review']), false);
        $response->assertSee('chart-repairStatus', false);
        $response->assertSee('chart-repairTrend', false);
    }

    // 管理員看得到設備、用戶、教室的卡片與圖表，並連到對應的主檔。
    public function test_admin_sees_device_user_and_classroom_cards_and_charts(): void
    {
        $this->makeDevices();
        $this->loginAsAnyUser();
        User::factory()->count(2)->create();

        $response = $this->get(route('dashboard'));

        $response->assertSeeInOrder(['設備總數', '3']);
        $response->assertSeeInOrder(['用戶數量', '3']);  // admin + 2
        $response->assertSeeInOrder(['教室數量', '1']);
        $response->assertSee('設備異常教室 1 間');
        $response->assertSee('chart-deviceStatus', false);
        $response->assertSee('chart-roleDistribution', false);
        $response->assertSee(str_replace('/', '\/', route('devices.index', ['status' => 'repairing'])), false);
        $response->assertSee(route('users.index'), false);
    }

    // 沒有主檔權限的人（例如維修人員）看不到設備、用戶、教室的統計。
    public function test_dashboard_hides_numbers_for_masters_the_user_cannot_open(): void
    {
        $this->makeDevices();
        $this->actingAs($this->makeTechnician());

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('進行中工單');
        $response->assertDontSee('設備總數');
        $response->assertDontSee('用戶數量');
        $response->assertDontSee('教室數量');
        $response->assertDontSee('chart-deviceStatus', false);
        $response->assertDontSee('chart-roleDistribution', false);
    }

    // 資訊組主管看得到設備與教室統計，但看不到用戶數量。
    public function test_it_manager_sees_devices_and_classrooms_but_not_users(): void
    {
        $this->makeDevices();
        $this->actingAs($this->makeItManager());

        $this->get(route('dashboard'))
            ->assertSee('設備總數')->assertSee('教室數量')
            ->assertDontSee('用戶數量');
    }

    // 圖表點擊帶去的網址，目標頁面真的有套用篩選（設備狀態、用戶身分）。
    public function test_chart_target_pages_apply_the_filters_the_dashboard_links_to(): void
    {
        $this->makeDevices();
        $admin = $this->loginAsAnyUser();

        // 設備狀態圖表點「維修中」→ 設備主檔只剩 1 台，而且畫面顯示中文狀態而不是 repairing
        $page = $this->get(route('devices.index', ['status' => 'repairing']));
        $page->assertOk()->assertSee('DEV-2')->assertDontSee('DEV-0')->assertSee('維修中');
        $page->assertDontSee('>repairing<', false);

        // 用戶身分圖表點某個身分 → 用戶主檔只列該身分
        $other = $this->makeTechnician();
        $this->get(route('users.index', ['role_id' => $admin->role_id]))
            ->assertSee($admin->username)->assertDontSee($other->username);
    }

    // 所有主檔與紀錄頁的表格都有框線（全站設計規則）。
    public function test_all_tables_have_borders(): void
    {
        $this->makeDevices();   // 部門、教室、設備類別、設備都至少有一筆（部門主檔沒資料時只顯示空狀態、不畫表格）
        $this->loginAsAnyUser();

        foreach (['classrooms.index', 'device-categories.index', 'devices.index', 'users.index', 'roles.index', 'departments.index', 'audit-logs.index'] as $route) {
            $this->assertStringContainsString('table-bordered', $this->get(route($route))->getContent(), "$route 的表格沒有框線");
        }
    }

    // ---------- 頂部通知鈴鐺 ----------

    // 通知鈴鐺統計「新報修待派工」與「待驗收」，並附上跳轉連結。
    public function test_bell_counts_new_repairs_and_pending_reviews_with_jump_links(): void
    {
        $this->actingAs($this->makeUserWithRole('executive', '主管'));  // 有派工 + 驗收權限
        RepairRequest::factory()->count(2)->create(['status' => 'pending']);
        RepairRequest::factory()->count(1)->create(['status' => 'pending_review']);

        $response = $this->get(route('dashboard'));

        $response->assertSee('id="notificationBadge"', false);
        $response->assertSee('2 張新報修待派工');
        $response->assertSee('1 張工單待驗收');
        $response->assertSee(route('repairs.index', ['status' => 'pending']), false);
        $response->assertSee(route('repairs.index', ['status' => 'pending_review']), false);
    }

    // 維修人員的鈴鐺只計算「指派給自己、未結案」的案件。
    public function test_bell_for_a_technician_counts_only_cases_assigned_to_them(): void
    {
        $technician = $this->makeTechnician();
        $other = $this->makeTechnician();
        $this->actingAs($technician);
        RepairRequest::factory()->create(['status' => 'assigned', 'assigned_to' => $technician->id]);
        RepairRequest::factory()->create(['status' => 'in_progress', 'assigned_to' => $technician->id]);
        RepairRequest::factory()->create(['status' => 'completed', 'assigned_to' => $technician->id]);   // 已結案不算
        RepairRequest::factory()->create(['status' => 'assigned', 'assigned_to' => $other->id]);          // 別人的不算
        RepairRequest::factory()->create(['status' => 'pending']);                                        // 沒有派工權限，不通知

        $response = $this->get(route('dashboard'));

        $response->assertSee('2 張指派給你的案件待處理');
        $response->assertDontSee('新報修待派工');
        $response->assertSee(route('repairs.index', ['assignee' => $technician->name]), false);
    }

    // 沒有待辦時鈴鐺不顯示紅色數字，清單顯示「目前沒有待處理的事項」。
    public function test_bell_shows_empty_state_without_a_badge_when_nothing_is_pending(): void
    {
        $this->actingAs($this->makeUserWithRole('executive', '主管'));

        $response = $this->get(route('dashboard'));

        $response->assertSee('目前沒有待處理的事項');
        $response->assertDontSee('id="notificationBadge"', false);
    }

    // 每個頁面頂部都有通知鈴鐺，不只主控台。
    public function test_bell_is_available_on_every_page_not_only_the_dashboard(): void
    {
        $this->actingAs($this->makeUserWithRole('executive', '主管'));
        RepairRequest::factory()->create(['status' => 'pending']);

        $this->get(route('repairs.index'))->assertSee('1 張新報修待派工');
        $this->get(route('knowledge-base.index'))->assertSee('1 張新報修待派工');
    }

    // ---------- 語言切換先隱藏 ----------

    // 語言切換預設隱藏，設定開啟後會再出現。
    public function test_language_switcher_is_hidden_by_default_and_can_be_turned_back_on(): void
    {
        $this->loginAsAnyUser();

        $this->get(route('dashboard'))->assertDontSee('id="locale-select"', false);

        config(['app.locale_switcher' => true]);
        $this->get(route('dashboard'))->assertSee('id="locale-select"', false);
    }

    // ---------- 選單：主檔歸成一類 ----------

    // 用戶、身分、部門、教室、設備類別、設備都收在同一個可收合的「主檔」群組裡（手機抽屜與桌面側邊欄各一份）。
    public function test_master_data_links_are_grouped_in_one_collapsible_group(): void
    {
        $this->loginAsAnyUser();

        $html = $this->get(route('dashboard'))->getContent();

        // 桌面側邊欄與手機抽屜各一個群組（id 不能重複）
        $this->assertStringContainsString('id="side-master"', $html);
        $this->assertStringContainsString('id="drawer-master"', $html);
        // 主檔群組 = 從 id="…-master" 到後面「業務功能」小標題（text-uppercase）之前
        foreach (['drawer', 'side'] as $navId) {
            $this->assertSame(1, preg_match('#id="' . $navId . '-master".*?(?=text-uppercase)#s', $html, $m), "[$navId] 找不到主檔群組");
            $group = $m[0];
            // 群組裡有用戶、身分、部門、教室、設備類別、設備
            foreach (['users.index', 'roles.index', 'departments.index', 'classrooms.index', 'device-categories.index', 'devices.index'] as $route) {
                $this->assertStringContainsString('href="' . route($route) . '"', $group, "[$navId] $route 應該在主檔群組裡");
            }
            // 報修看板與操作紀錄是獨立項目，不屬於主檔
            $this->assertStringNotContainsString(route('repairs.index'), $group);
            $this->assertStringNotContainsString(route('audit-logs.index'), $group);
        }
    }

    // 在主檔頁面時群組預設展開，其他頁面預設收合。
    public function test_master_group_starts_expanded_only_on_a_master_page(): void
    {
        $this->loginAsAnyUser();

        $this->get(route('users.index'))->assertSee('class="collapse show" id="side-master"', false);
        $this->get(route('repairs.index'))->assertSee('class="collapse " id="side-master"', false);
    }

    // 完全沒有主檔權限的人看不到「主檔」群組。
    public function test_master_group_is_absent_for_users_without_any_master_permission(): void
    {
        $this->actingAs($this->makeTechnician());

        $this->get(route('dashboard'))->assertDontSee('id="side-master"', false);
    }
}
