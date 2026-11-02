<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Department;
use App\Models\Device;
use App\Models\DeviceCategory;
use App\Models\RepairRequest;
use App\Models\RepairLog;
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

    // 測試使用台灣繁體中文，確保畫面文字與斷言一致。
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.locale' => 'zh_TW']);
        app()->setLocale('zh_TW');
    }

    // 輔助方法：建立一間教室（標成設備異常）與三台設備（兩台正常、一台維修中）。
    private function makeDevices(): void
    {
        $classroom = Classroom::create([
            'department_id' => Department::create(['name' => '資訊組'])->id, 'campus' => '本校區', 'building' => 'A棟',
            'floor' => '1', 'classroom_code' => 'A101', 'name' => 'A101 教室', 'reservation_status' => 'abnormal',
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
        // 本月新增報修卡片只計算本月建立的報修單，不包含上個月的資料。
    public function test_dashboard_shows_the_correct_monthly_repair_count(): void
    {
        $this->loginAsAnyUser();

        // 本月第一天之後建立的兩張報修單，應該計入本月數量。
        $thisMonth = now()->startOfMonth()->addDays(2)->setTime(12, 0);

        RepairRequest::factory()->count(2)->create([
            'status' => 'pending',
            'created_at' => $thisMonth,
        ]);

        // 上個月建立的一張報修單，不應該計入本月數量。
        $lastMonth = now()->startOfMonth()->subDay()->setTime(12, 0);

        RepairRequest::factory()->create([
            'status' => 'pending',
            'created_at' => $lastMonth,
        ]);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('本月新增報修');
        $response->assertSeeInOrder(['本月新增報修', '2']);
    }


    public function test_dashboard_shows_average_cumulative_repair_hours_and_missing_count(): void
    {
        $user = $this->makeUserWithRole('admin', '系統管理員');
        $this->actingAs($user);

        // 固定本月的日期，避免測試執行時間影響統計結果。
        $monthStart = now()->startOfMonth();

        // 第一張已結案報修單：歷史累計工時為 5 小時。
        $requestA = RepairRequest::factory()->create([
            'status' => 'completed',
            'requester_id' => $user->id,
            'assigned_to' => null,
        ]);

        RepairLog::factory()->create([
            'repair_request_id' => $requestA->id,
            'started_at' => $monthStart->copy()->subDays(2)->setTime(8, 0),
            'ended_at' => $monthStart->copy()->subDays(2)->setTime(10, 0),
            'total_hours' => 2,
        ]);

        RepairLog::factory()->create([
            'repair_request_id' => $requestA->id,
            'started_at' => $monthStart->copy()->addDays(2)->setTime(8, 0),
            'ended_at' => $monthStart->copy()->addDays(2)->setTime(11, 0),
            'total_hours' => 3,
        ]);

        // 第二張已結案報修單：累計工時為 2 小時。
        $requestB = RepairRequest::factory()->create([
            'status' => 'completed',
            'requester_id' => $user->id,
            'assigned_to' => null,
        ]);

        RepairLog::factory()->create([
            'repair_request_id' => $requestB->id,
            'started_at' => $monthStart->copy()->addDays(3)->setTime(8, 0),
            'ended_at' => $monthStart->copy()->addDays(3)->setTime(10, 0),
            'total_hours' => 2,
        ]);

        // 第三張已結案報修單：有本月結束的紀錄，但無法計算工時。
        $requestC = RepairRequest::factory()->create([
            'status' => 'completed',
            'requester_id' => $user->id,
            'assigned_to' => null,
        ]);

        RepairLog::factory()->create([
            'repair_request_id' => $requestC->id,
            'started_at' => null,
            'ended_at' => $monthStart->copy()->addDays(4)->setTime(10, 0),
            'total_hours' => null,
        ]);

        $response = $this->get(route('dashboard'));

        $response->assertOk();

        // (5 + 2) / 2 = 3.50 小時；缺少工時的第三張不納入平均。
        $response->assertSee('本月平均累計維修工時');
        $response->assertSee('3.50');
        $response->assertSee('有 1 張已結案報修單缺少可計算工時');

        // 平均工時卡片連到已結案報修單清單。
        $response->assertSee(
            route('repairs.index', ['status' => 'completed']),
            false
        );
    }


    // 近 14 天報修趨勢：每日數量正確、空白日期補 0，且不包含 14 天以前的資料。
    public function test_dashboard_repair_trend_counts_each_day_and_excludes_older_repairs(): void
    {
        $this->loginAsAnyUser();

        // 固定以今天為基準，避免測試跨日執行時日期範圍不一致。
        $today = now()->startOfDay();
        $yesterday = $today->copy()->subDay();
        $oldDate = $today->copy()->subDays(14)->setTime(12, 0);

        // 今天建立兩張，昨天建立一張。
        RepairRequest::factory()->count(2)->create([
            'status' => 'pending',
            'created_at' => $today->copy()->setTime(10, 0),
        ]);

        RepairRequest::factory()->create([
            'status' => 'pending',
            'created_at' => $yesterday->copy()->setTime(10, 0),
        ]);

        // 14 天以前的資料不應進入近 14 天趨勢。
        RepairRequest::factory()->create([
            'status' => 'pending',
            'created_at' => $oldDate,
        ]);

        $response = $this->get(route('dashboard'));

        $response->assertOk();

                $html = $response->getContent();

        // 從 Blade 輸出的 JavaScript 中擷取圖表 JSON。
        $this->assertSame(
            1,
            preg_match('/var charts = (\{.*?\});/s', $html, $matches),
            '找不到儀表板輸出的圖表 JSON。'
        );

        $charts = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('repairTrend', $charts);

        $trend = $charts['repairTrend'];

        // 近 14 天應該有 14 個日期標籤及 14 個數量。
        $this->assertCount(14, $trend['labels']);
        $this->assertCount(14, $trend['counts']);

        // 找出今天及昨天在圖表中的位置。
        $todayIndex = array_search($today->format('m/d'), $trend['labels'], true);
        $yesterdayIndex = array_search($yesterday->format('m/d'), $trend['labels'], true);

        $this->assertNotFalse($todayIndex, '趨勢圖缺少今天的日期。');
        $this->assertNotFalse($yesterdayIndex, '趨勢圖缺少昨天的日期。');

        // 今天兩張、昨天一張。
        $this->assertSame(2, $trend['counts'][$todayIndex]);
        $this->assertSame(1, $trend['counts'][$yesterdayIndex]);

        // 沒有報修的日期應補 0。
        $this->assertContains(0, $trend['counts']);

        // 日期範圍從 13 天前到今天。
        $this->assertSame(
            $today->copy()->subDays(13)->format('m/d'),
            $trend['labels'][0]
        );
        $this->assertSame($today->format('m/d'), $trend['labels'][13]);

        // 總數應為 3，證明 14 天以前的報修單沒有算進來。
        $this->assertSame(3, array_sum($trend['counts']));
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

    // 儀表板排行依主檔權限顯示。
    public function test_dashboard_repair_rankings_follow_master_data_permissions(): void
    {
        // 技術人員沒有教室與設備主檔管理權限。
        $this->actingAs($this->makeTechnician());

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('classroomRepairRanking', null);
        $response->assertViewHas('deviceCategoryRepairRanking', null);

        // 資訊組主管具有教室與設備管理權限。
        $this->actingAs($this->makeItManager());

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('classroomRepairRanking');
        $response->assertViewHas('deviceCategoryRepairRanking');
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

    // 儀表板本月排行：確認教室與設備類別的數量、排序及月份範圍。
    public function test_dashboard_ranks_classrooms_and_device_categories_for_current_month(): void
    {
        $user = $this->makeUserWithRole('admin', '系統管理員');
        $this->actingAs($user);

        $monthStart = now()->startOfMonth();
        $lastMonth = $monthStart->copy()->subMonth()->addDays(5);

        $department = Department::create(['name' => '資訊組']);

        $classroomA = Classroom::create([
            'department_id' => $department->id,
            'campus' => '本校區',
            'building' => 'A棟',
            'floor' => '1',
            'classroom_code' => 'A101',
            'name' => '一樓教室',
            'reservation_status' => 'normal',
        ]);

        $classroomB = Classroom::create([
            'department_id' => $department->id,
            'campus' => '本校區',
            'building' => 'A棟',
            'floor' => '2',
            'classroom_code' => 'B202',
            'name' => '二樓教室',
            'reservation_status' => 'normal',
        ]);

        $projector = DeviceCategory::create(['name' => '投影機']);
        $computer = DeviceCategory::create(['name' => '電腦']);

        $projectorA = Device::create([
            'device_code' => 'RANK-DEV-1',
            'device_category_id' => $projector->id,
            'classroom_id' => $classroomA->id,
            'status' => 'normal',
        ]);

        $computerA = Device::create([
            'device_code' => 'RANK-DEV-2',
            'device_category_id' => $computer->id,
            'classroom_id' => $classroomA->id,
            'status' => 'normal',
        ]);

        $projectorB = Device::create([
            'device_code' => 'RANK-DEV-3',
            'device_category_id' => $projector->id,
            'classroom_id' => $classroomB->id,
            'status' => 'normal',
        ]);

        // 建立指定設備與日期的報修單。
        $createRepair = function (Device $device, $createdAt) use ($user): void {
            RepairRequest::factory()->create([
                'device_id' => $device->id,
                'requester_id' => $user->id,
                'created_at' => $createdAt,
            ]);
        };

        // 本月：A101 有 3 張，B202 有 1 張。
        // 投影機有 3 張，電腦有 1 張。
        $createRepair($projectorA, $monthStart->copy()->addDays(1)->setTime(10, 0));
        $createRepair($projectorA, $monthStart->copy()->addDays(2)->setTime(10, 0));
        $createRepair($computerA, $monthStart->copy()->addDays(3)->setTime(10, 0));
        $createRepair($projectorB, $monthStart->copy()->addDays(4)->setTime(10, 0));

        // 上個月的報修不應被算入本月排行。
        $createRepair($projectorB, $lastMonth);

        $response = $this->get(route('dashboard'));

        $response->assertOk();

        $response->assertViewHas(
            'classroomRepairRanking',
            fn ($ranking) =>
                array_column($ranking, 'label') === [
                    'A101 一樓教室',
                    'B202 二樓教室',
                ]
                && array_column($ranking, 'count') === [3, 1]
        );

        $response->assertViewHas(
            'deviceCategoryRepairRanking',
            fn ($ranking) =>
                array_column($ranking, 'label') === [
                    '投影機',
                    '電腦',
                ]
                && array_column($ranking, 'count') === [3, 1]
        );
    }

    // 儀表板統計：待修 = 待派工 + 已派工，維修中與待驗收分開計算。
    public function test_dashboard_counts_pending_assigned_in_progress_and_pending_review_separately(): void
    {
        $this->loginAsAnyUser();

        RepairRequest::factory()->count(2)->create(['status' => 'pending']);
        RepairRequest::factory()->count(3)->create(['status' => 'assigned']);
        RepairRequest::factory()->count(4)->create(['status' => 'in_progress']);
        RepairRequest::factory()->count(5)->create(['status' => 'pending_review']);
        RepairRequest::factory()->count(2)->create(['status' => 'completed']);

        $response = $this->get(route('dashboard'));

        $response->assertOk();

        // 待修 = 待派工 2 + 已派工 3 = 5。
        $response->assertSeeInOrder(['待修數', '5']);

        // 維修中只計算 in_progress。
        $response->assertSeeInOrder(['維修中', '4']);

        // 待驗收獨立計算，不併入待修數。
        $response->assertSeeInOrder(['待驗收', '5']);
    }
}
