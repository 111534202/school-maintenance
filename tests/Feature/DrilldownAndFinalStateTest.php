<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ClassroomReservation;
use App\Models\Device;
use App\Models\DeviceCategory;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceResult;
use App\Models\PreventiveCandidate;
use App\Models\RepairLog;
use App\Models\RepairRequest;
use App\Models\User;
use App\Services\DueMaintenanceOrderGenerator;
use App\Services\NgToRepairService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesReservations;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 兩條流程規則的回歸測試（2026-11-20 使用者要求，全專案檢查過）：
 * 規則一：點擊跳轉的目的頁只顯示被點的那一類資料，且來源的數字和目的頁列出的筆數一致；
 * 規則二：已結案、已淘汰等「已下定論」的資料是唯讀的，畫面不再顯示能改回去的控制項，後端也擋下。
 */
class DrilldownAndFinalStateTest extends TestCase
{
    use CreatesReservations;
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    private function room(string $code = 'D101'): Classroom
    {
        return Classroom::firstOrCreate(['classroom_code' => $code], ['campus' => '本', 'building' => 'D', 'floor' => '1', 'name' => $code . ' 教室']);
    }

    private function device(string $code, string $status = 'normal', ?Classroom $room = null, string $category = '投影機'): Device
    {
        return Device::create([
            'device_code' => $code, 'device_category_id' => DeviceCategory::firstOrCreate(['name' => $category])->id,
            'classroom_id' => ($room ?? $this->room())->id, 'status' => $status,
        ]);
    }

    private function reservation(string $class, string $date): ClassroomReservation
    {
        return $this->makeReservation([
            'classroom_id' => $this->room('R200')->id, 'requester_id' => User::factory()->create()->id, 'class_name' => $class,
            'purpose' => '班級活動', 'status' => ClassroomReservation::STATUS_PENDING,
            'reservation_date' => $date, 'start_time' => '10:00', 'end_time' => '10:50',
        ]);
    }

    /** 看板列表頁畫面上列出的報修單標題（用標題比對，才看得出到底混進了哪些資料）。 */
    private function boardTitles(User $user, array $query): array
    {
        $html = $this->actingAs($user)->get(route('repairs.index', $query))->assertOk()->getContent();
        preg_match_all('/data-modal-frame-href="[^"]*\/repairs\/\d+"[^>]*>.*?<\/tr>/s', $html, $rows);

        return collect($rows[0])->map(fn ($row) => preg_match('/案件-[A-Z0-9]+/', $row, $m) ? $m[0] : null)->filter()->values()->all();
    }

    private function repair(string $title, string $status, array $attrs = []): RepairRequest
    {
        return RepairRequest::factory()->create(array_merge(['title' => $title, 'status' => $status], $attrs));
    }

    // ================= 規則一：跳轉精準 =================

    public function test_open_filter_hides_completed_cases(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $this->repair('案件-OPEN1', 'assigned');
        $this->repair('案件-DONE1', 'completed');

        $titles = $this->boardTitles($admin, ['open' => 1]);

        $this->assertSame(['案件-OPEN1'], $titles);
    }

    public function test_status_filter_accepts_several_statuses(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $this->repair('案件-P', 'pending');
        $this->repair('案件-A', 'assigned');
        $this->repair('案件-I', 'pending_review');

        $titles = $this->boardTitles($admin, ['status' => 'pending,assigned']);

        $this->assertEqualsCanonicalizing(['案件-P', '案件-A'], $titles);
    }

    public function test_created_date_range_only_lists_cases_created_in_that_range(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $this->repair('案件-IN', 'pending', ['created_at' => '2026-11-10 12:00:00']);
        $this->repair('案件-OUT', 'pending', ['created_at' => '2026-10-31 23:59:00']);

        $titles = $this->boardTitles($admin, ['created_from' => '2026-11-01', 'created_to' => '2026-11-30']);

        $this->assertSame(['案件-IN'], $titles);
    }

    public function test_classroom_open_repair_badge_matches_the_board_it_links_to(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $a = $this->room('A101');
        $b = $this->room('B202');
        $this->repair('案件-A1', 'assigned', ['device_id' => $this->device('DEV-A1', 'normal', $a)->id]);
        $this->repair('案件-A2', 'pending', ['device_id' => $this->device('DEV-A2', 'normal', $a)->id]);
        $this->repair('案件-A3', 'completed', ['device_id' => $this->device('DEV-A3', 'normal', $a)->id]);   // 已結案：不算
        $this->repair('案件-B1', 'pending', ['device_id' => $this->device('DEV-B1', 'normal', $b)->id]);
        $this->repair('案件-A4', 'pending', ['device_id' => null, 'classroom_id' => $a->id]);   // 沒綁設備、發現地點填 A101：教室篩選也算

        $html = $this->actingAs($admin)->get(route('classrooms.index'))->assertOk()->getContent();
        $link = htmlspecialchars(route('repairs.index', ['classroom_id' => $a->id, 'open' => 1]));
        $this->assertStringContainsString($link, $html);

        $this->assertEqualsCanonicalizing(['案件-A1', '案件-A2', '案件-A4'], $this->boardTitles($admin, ['classroom_id' => $a->id, 'open' => 1]));
        $this->assertMatchesRegularExpression('/bi-tools me-1"><\/i>3</', $html);   // 徽章數字和看板筆數一樣
    }

    public function test_device_category_filter_only_lists_that_category(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $this->repair('案件-PROJ', 'pending', ['device_id' => $this->device('DEV-1', 'normal', null, '投影機')->id]);
        $this->repair('案件-PC', 'pending', ['device_id' => $this->device('DEV-2', 'normal', null, '電腦')->id]);
        $pcCategory = DeviceCategory::where('name', '電腦')->first();

        $this->assertSame(['案件-PC'], $this->boardTitles($admin, ['device_category_id' => $pcCategory->id]));
    }

    public function test_dashboard_cards_and_trend_chart_link_to_precise_filters(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $this->repair('案件-X', 'pending');

        $html = $this->actingAs($admin)->get(route('dashboard'))->assertOk()->getContent();

        // 進行中工單＝未結案（新報修、已派工、待驗收；已結案、已退回不算）。
        $this->assertStringContainsString(htmlspecialchars(route('repairs.index', ['status' => 'pending,assigned,pending_review'])), $html);
        $this->assertStringContainsString(htmlspecialchars(route('repairs.index', ['status' => 'pending,assigned'])), $html);
        $this->assertStringContainsString(htmlspecialchars(route('repairs.index', ['created_from' => now()->startOfMonth()->toDateString(), 'created_to' => now()->endOfMonth()->toDateString()])), $html);
        // 趨勢圖：每一天的點連到「那一天新增」的報修單。
        $today = now()->toDateString();
        $this->assertStringContainsString(trim(json_encode(route('repairs.index', ['created_from' => $today, 'created_to' => $today]), JSON_HEX_AMP | JSON_HEX_TAG), '"'), $html);
    }

    public function test_dashboard_adds_a_card_for_classrooms_with_abnormal_devices_that_filters_the_list(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        // 異常設備＝有沒結案的報修單（本分支的定義）。
        $bad = $this->device('DEV-BAD', 'normal', $this->room('A101'));
        $this->repair('案件-BAD', 'assigned', ['device_id' => $bad->id]);
        $this->device('DEV-OK', 'normal', $this->room('B202'));

        $this->actingAs($admin)->get(route('dashboard'))->assertOk()
            ->assertSee(htmlspecialchars(route('classrooms.index', ['abnormal' => 1])), false);
        $rows = $this->actingAs($admin)->get(route('classrooms.index', ['abnormal' => 1]))->getContent();
        $this->assertStringContainsString('A101', $rows);
        $this->assertStringNotContainsString('B202', $rows);
    }

    public function test_bell_pending_review_count_and_link_use_the_same_definition(): void
    {
        $me = $this->makeUserWithPermissions(['repairs.accept', 'repairs.create']);
        $this->repair('案件-MINE', 'pending_review', ['requester_id' => $me->id, 'accepted_by' => null]);
        // 我報修、但指定別人驗收：不是等我驗收。
        $this->repair('案件-OTHER', 'pending_review', ['requester_id' => $me->id, 'accepted_by' => $this->makeTechnician('別人')->id]);

        $html = $this->actingAs($me)->get(route('repairs.create'))->assertOk()->getContent();
        $this->assertStringContainsString('1 張工單待驗收', $html);
        $this->assertStringContainsString(htmlspecialchars(route('repairs.index', ['status' => 'pending_review', 'acceptable' => 1])), $html);
        $this->assertSame(['案件-MINE'], $this->boardTitles($me, ['status' => 'pending_review', 'acceptable' => 1]));
    }

    public function test_bell_unassigned_maintenance_link_excludes_completed_orders(): void
    {
        $manager = $this->makeItManager();
        $device = $this->device('DEV-M1');
        $open = MaintenanceOrder::factory()->create(['device_id' => $device->id, 'assigned_to' => null, 'status' => MaintenanceOrder::STATUS_PENDING]);
        $done = MaintenanceOrder::factory()->create(['device_id' => $device->id, 'assigned_to' => null, 'status' => MaintenanceOrder::STATUS_COMPLETED]);

        $html = $this->actingAs($manager)->get(route('maintenance-orders.index', ['assignee' => 'none', 'unfinished' => 1]))->assertOk()->getContent();

        $this->assertStringContainsString($open->displayNumber(), $html);
        $this->assertStringNotContainsString($done->displayNumber(), $html);
    }

    public function test_maintenance_filter_form_keeps_mine_and_unfinished_when_filtering_again(): void
    {
        $this->actingAs($this->makeItManager())->get(route('maintenance-orders.index', ['mine' => 1, 'unfinished' => 1]))->assertOk()
            ->assertSee('<input type="hidden" name="mine" value="1">', false)
            ->assertSee('<input type="hidden" name="unfinished" value="1">', false);
    }

    public function test_reservation_bell_link_lists_exactly_what_the_bell_counts(): void
    {
        $this->travelTo('2026-11-09 08:00:00');
        $reviewer = $this->makeUserWithPermissions(['reservations.manage']);
        $upcoming = $this->reservation('未來班', '2026-11-12');
        $this->reservation('過期班', '2026-11-05');

        $this->assertSame(1, ClassroomReservation::awaitingReview()->count());
        $html = $this->actingAs($reviewer)->get(route('reservations.index', ['status' => 'pending', 'upcoming' => 1]))->assertOk()->getContent();
        $this->assertStringContainsString('未來班', $html);
        $this->assertStringNotContainsString('過期班', $html);
        $this->assertNotNull($upcoming);
    }

    public function test_acting_on_a_filtered_list_returns_to_the_same_filtered_list(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $device = $this->device('DEV-BACK', 'repairing');
        $filtered = route('devices.index', ['classroom_id' => $device->classroom_id, 'abnormal' => 1]);

        $this->actingAs($admin)->from($filtered)->patch(route('devices.disable', $device))->assertRedirect($filtered);
        // 從別的頁面（例如詳細頁）送出：回列表的預設網址。
        $other = $this->device('DEV-BACK2');
        $this->actingAs($admin)->from(route('devices.show', $other))->patch(route('devices.disable', $other))->assertRedirect(route('devices.index'));
    }

    public function test_back_to_list_drops_the_page_number_but_keeps_the_filters(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $device = $this->device('DEV-PAGE', 'repairing');
        $from = route('devices.index', ['abnormal' => 1, 'page' => 3]);

        $this->actingAs($admin)->from($from)->patch(route('devices.disable', $device))
            ->assertRedirect(route('devices.index', ['abnormal' => 1]));
    }

    public function test_repeat_repair_badge_link_lists_the_same_cases_for_a_restricted_user(): void
    {
        $manager = $this->makeItManager();
        $device = $this->device('DEV-REP');
        foreach (range(1, 4) as $i) {
            $this->repair('案件-REP' . $i, 'pending', ['device_id' => $device->id, 'created_at' => now()->subDays(2)]);
        }
        $this->repair('案件-HIDDEN', 'pending_review', ['device_id' => $device->id, 'created_at' => now()->subDays(1)]);   // 跟主管無關的待驗收：看不到

        $html = $this->actingAs($manager)->get(route('devices.index'))->assertOk()->getContent();
        $this->assertStringContainsString('4', strip_tags($html));
        $titles = $this->boardTitles($manager, ['device_id' => $device->id, 'created_from' => now()->subDays(Device::REPEAT_REPAIR_DAYS)->toDateString()]);
        $this->assertCount(4, $titles);
        $this->assertSame(4, (int) Device::withCount(['repairRequests as c' => fn ($q) => $q->visibleOnBoard($manager)->where('created_at', '>=', now()->subDays(Device::REPEAT_REPAIR_DAYS)->startOfDay())])->find($device->id)->c);
    }

    // ================= 規則二：已下定論的資料唯讀 =================

    public function test_a_retired_device_cannot_be_disabled_and_gets_no_qr_sticker(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $retired = $this->device('DEV-RET2', 'retired');
        $normal = $this->device('DEV-QR-OK', 'normal');

        $this->actingAs($admin)->get(route('devices.index'))->assertOk()->assertDontSee(route('devices.disable', $retired), false)->assertSee(route('devices.disable', $normal), false);
        $this->actingAs($admin)->patch(route('devices.disable', $retired))->assertSessionHas('error');
        $this->assertFalse(Device::withTrashed()->find($retired->id)->trashed());
        $this->assertSame('retired', $retired->fresh()->status);

        $this->actingAs($admin)->get(route('devices.qr-sheet'))->assertOk()
            ->assertSee('DEV-QR-OK')->assertDontSee('DEV-RET2')->assertSee('已排除 1 台');
    }

    public function test_a_disabled_but_not_deleted_device_gets_no_maintenance_orders(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $device = $this->device('DEV-DIS', 'disabled');
        $plan = MaintenancePlan::factory()->create(['device_id' => $device->id, 'next_due_date' => now()->toDateString(), 'is_active' => true]);

        $this->actingAs($admin)->post(route('maintenance-plans.create-order', $plan))->assertSessionHas('error');
        $this->assertSame(0, MaintenanceOrder::count());
    }

    public function test_a_completed_order_offers_no_report_button(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $order = MaintenanceOrder::factory()->create(['device_id' => $this->device('DEV-NOBTN')->id, 'status' => MaintenanceOrder::STATUS_COMPLETED]);

        $this->assertFalse($order->canBeReportedBy($admin));
        $this->actingAs($admin)->get(route('maintenance-orders.show', $order))->assertOk()->assertDontSee(route('maintenance-orders.results.create', $order), false);
    }

    public function test_a_completed_repair_does_not_show_the_return_reason_warning(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $done = $this->repair('案件-DONE', 'completed', ['rejection_reason' => '上次沒修好的說明']);
        $open = $this->repair('案件-WIP', 'assigned', ['rejection_reason' => '請再檢查電源']);

        $this->actingAs($admin)->get(route('repairs.show', $done))->assertOk()
            ->assertDontSee('上次沒修好的說明')->assertDontSee('bi-arrow-counterclockwise', false);
        $this->actingAs($admin)->get(route('repairs.show', $open))->assertOk()->assertSee('請再檢查電源');
    }

    public function test_a_retired_device_is_read_only_everywhere(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $device = $this->device('DEV-OLD', 'retired');

        // 畫面：列表與詳細頁都沒有「修改」入口。
        $this->actingAs($admin)->get(route('devices.index'))->assertOk()->assertDontSee(route('devices.edit', $device), false);
        $this->actingAs($admin)->get(route('devices.show', $device))->assertOk()->assertDontSee(route('devices.edit', $device), false);
        // 後端：開修改表單與送出修改都被擋，資料不變。
        $this->actingAs($admin)->get(route('devices.edit', $device))->assertRedirect(route('devices.show', $device))->assertSessionHas('error');
        foreach (['normal', 'repairing', 'disabled', 'retired'] as $status) {
            $this->actingAs($admin)->put(route('devices.update', $device), [
                'device_code' => 'DEV-CHANGED', 'brand' => '被改掉', 'device_category_id' => $device->device_category_id, 'classroom_id' => $device->classroom_id, 'status' => $status,
            ])->assertRedirect(route('devices.show', $device))->assertSessionHas('error');
        }
        $fresh = $device->fresh();
        $this->assertSame('DEV-OLD', $fresh->device_code);
        $this->assertSame('retired', $fresh->status);
        $this->assertNull($fresh->brand);
    }

    public function test_an_ng_result_on_a_retired_device_does_not_create_a_repair_request(): void
    {
        $device = $this->device('DEV-NG', 'retired');
        $order = MaintenanceOrder::factory()->create(['device_id' => $device->id, 'status' => MaintenanceOrder::STATUS_COMPLETED]);
        $result = MaintenanceResult::factory()->create(['maintenance_order_id' => $order->id, 'result' => 'ng']);

        app(NgToRepairService::class)->convert($result);

        $this->assertSame(0, RepairRequest::count());
        $this->assertSame(MaintenanceResult::NG_CONVERSION_NOT_APPLICABLE, $result->fresh()->ng_conversion_status);
        $this->actingAs($this->makeUserWithRole('admin', '系統管理員'))->get(route('maintenance-orders.show', $order))->assertOk()->assertSee('設備已淘汰或停用，不能報修');
    }

    public function test_ai_candidate_for_a_retired_device_cannot_be_approved(): void
    {
        $manager = $this->makeItManager();
        $device = $this->device('DEV-AI', 'normal');
        $candidate = PreventiveCandidate::create([
            'device_id' => $device->id, 'risk_score' => 0.8, 'threshold' => 0.5, 'algorithm' => 'rule_based',
            'explanation' => [], 'features' => [], 'status' => PreventiveCandidate::STATUS_PENDING,
        ]);
        $device->update(['status' => 'retired']);

        $this->actingAs($manager)->get(route('preventive-candidates.index'))->assertOk()->assertSee('設備已淘汰或停用，不能建立保養工單');
        $this->actingAs($manager)->post(route('preventive-candidates.approve', $candidate), ['scheduled_date' => now()->addDays(3)->toDateString()])
            ->assertSessionHas('error');
        $this->assertSame(PreventiveCandidate::STATUS_PENDING, $candidate->fresh()->status);
        $this->assertSame(0, MaintenanceOrder::count());
    }

    public function test_no_maintenance_orders_are_created_for_a_retired_device(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $device = $this->device('DEV-PLAN', 'retired');
        $plan = MaintenancePlan::factory()->create(['device_id' => $device->id, 'next_due_date' => now()->subDay()->toDateString(), 'is_active' => true]);

        $this->actingAs($admin)->post(route('maintenance-plans.create-order', $plan))->assertSessionHas('error');
        $this->assertSame(0, app(DueMaintenanceOrderGenerator::class)->generate());
        $this->assertSame(0, MaintenanceOrder::count());
    }

    public function test_a_completed_maintenance_order_without_a_result_row_cannot_get_a_result(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $order = MaintenanceOrder::factory()->create(['device_id' => $this->device('DEV-OLDORD')->id, 'status' => MaintenanceOrder::STATUS_COMPLETED]);

        $this->actingAs($admin)->post(route('maintenance-orders.results.store', $order), ['result' => 'ok', 'executed_at' => now()->subHour()->format('Y-m-d H:i')])
            ->assertSessionHas('error');
        $this->assertSame(0, MaintenanceResult::count());
    }

    public function test_restoring_a_user_that_was_never_deleted_is_refused(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $active = User::factory()->create();

        $this->actingAs($admin)->patch(route('users.restore', $active))->assertSessionHas('error');
        $this->assertDatabaseMissing('audit_logs', ['action' => 'restored', 'loggable_id' => $active->id]);
    }

    public function test_a_completed_repair_offers_no_way_to_go_back_for_anyone(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $technician = $this->makeTechnician();
        $done = $this->repair('案件-FINAL', 'completed', ['assigned_to' => $technician->id, 'requester_id' => $admin->id, 'accepted_by' => $admin->id]);
        RepairLog::factory()->create(['repair_request_id' => $done->id]);

        $this->actingAs($admin)->get(route('repairs.show', $done))->assertOk()
            ->assertDontSee('確認退回')->assertDontSee('重新指派')->assertDontSee('取消工單')->assertDontSee(route('repairs.edit', $done), false);
        $this->actingAs($admin)->post(route('repairs.reject', $done), ['rejection_reason' => '想退回'])->assertSessionHas('error');
        $this->assertSame('completed', $done->fresh()->status->value);
    }

    public function test_accepting_a_repair_shows_the_closing_info_in_a_dialog_that_closes_when_clicking_outside(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $technician = $this->makeTechnician();
        $technician->update(['name' => '阿修']);
        $case = $this->repair('案件-CLOSE', 'pending_review', ['assigned_to' => $technician->id, 'requester_id' => $admin->id]);
        RepairLog::factory()->create(['repair_request_id' => $case->id, 'total_hours' => 1.5]);

        $html = $this->actingAs($admin)->followingRedirects()->post(route('repairs.complete', $case))->assertOk()->getContent();

        $this->assertStringContainsString('id="closingInfoDialog"', $html);
        $this->assertStringContainsString('#' . $case->id . ' 案件-CLOSE', $html);
        $this->assertStringContainsString('阿修', $html);
        $this->assertStringContainsString('共 1.50 小時', $html);
        // 點視窗外面就關閉。
        $this->assertStringContainsString('event.target === d', $html);
        // 之後再開同一頁不會再跳出（只在剛驗收通過那一次顯示）。
        $this->actingAs($admin)->get(route('repairs.show', $case))->assertOk()->assertDontSee('id="closingInfoDialog"', false);
    }

    // 本分支的「上一步」：有本站的上一頁就回瀏覽紀錄的上一頁（history.back），沒有才回系統結構上的上一層（連結本身）。
    public function test_the_back_button_goes_back_in_history_and_falls_back_to_the_parent_page(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $html = $this->actingAs($admin)->get(route('roles.index'))->assertOk()->getContent();

        $this->assertStringContainsString('history.back()', $html);
        $this->assertStringContainsString('href="' . route('dashboard') . '" class="btn btn-outline-light btn-sm tb-nav-btn flex-shrink-0" data-page-back', $html);
    }

    public function test_ai_settings_are_saved_from_the_popup_and_return_to_the_candidates_page(): void
    {
        $manager = $this->makeItManager();

        $this->actingAs($manager)->put(route('ai-settings.update'), [
            'risk_threshold' => 0.6, 'min_completed_orders' => 3, 'recent_window' => 5, 'dedup_window_days' => 30, 'require_approval' => 1, 'schedule_lead_days' => 7,
        ])->assertRedirect(route('preventive-candidates.index'));
        $this->actingAs($manager)->get(route('ai-settings.edit'))->assertOk()->assertSee('風險分數怎麼算');
    }

    public function test_pages_are_gzip_compressed_when_the_browser_accepts_it(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');

        // 瀏覽器會帶 Accept-Encoding: gzip：回應壓縮、解開後是完整的 HTML。
        $response = $this->actingAs($admin)->withHeaders(['Accept-Encoding' => 'gzip'])->get(route('classrooms.index'))->assertOk();
        $this->assertSame('gzip', $response->headers->get('Content-Encoding'));
        $plain = gzdecode($response->getContent());
        $this->assertStringEndsWith('</html>', rtrim($plain));
        $this->assertLessThan(strlen($plain) / 2, strlen($response->getContent()));

        // 沒帶（例如一般測試、舊工具）就不壓縮。
        $this->flushHeaders();
        $this->assertNull($this->actingAs($admin)->get(route('classrooms.index'))->headers->get('Content-Encoding'));
    }

    public function test_dashboard_cards_and_the_bell_show_the_same_numbers_for_the_same_things(): void
    {
        $this->travelTo('2026-11-09 08:00:00');
        $reviewer = $this->makeUserWithPermissions(['reservations.manage', 'repairs.accept', 'repairs.create']);
        $this->reservation('未來班', '2026-11-12');
        $this->reservation('過期班', '2026-11-05');   // 時段已過：兩邊都不算
        $this->repair('案件-MINE', 'pending_review', ['requester_id' => $reviewer->id, 'accepted_by' => null]);
        $this->repair('案件-OTHER', 'pending_review', ['requester_id' => $reviewer->id, 'accepted_by' => $this->makeTechnician()->id]);   // 指定別人驗收：不算

        $response = $this->actingAs($reviewer)->get(route('dashboard'))->assertOk();

        $response->assertViewHas('pendingReservationCount', 1)->assertViewHas('pendingReviewCount', 1);
        $response->assertSee('1 筆空間申請待審核')->assertSee('1 張工單待驗收');   // 鈴鐺
    }

    // ---------- 合併到本分支後補的：淘汰的設備不產生工單並記錄、取消淘汰 ----------

    // 計畫的設備已淘汰：到期也不產生工單，操作紀錄寫出原因；「建立工單」按鈕不顯示、硬送也擋下。
    public function test_a_plan_on_a_retired_device_creates_no_order_and_logs_why(): void
    {
        $old = $this->device('DEV-GONE', 'retired');
        $plan = MaintenancePlan::factory()->create(['device_id' => $old->id, 'device_category_id' => null, 'cycle_days' => 30, 'start_date' => now()->subDays(30)->toDateString(), 'next_due_date' => now()->toDateString(), 'is_active' => true]);

        $this->assertSame(0, app(DueMaintenanceOrderGenerator::class)->generate());
        $this->assertDatabaseHas('audit_logs', ['description' => '保養計畫「' . $plan->name . '」' . now()->toDateString() . ' 這一期沒有產生工單：設備已淘汰或停用']);

        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $this->actingAs($admin)->get(route('maintenance-plans.index'))->assertOk()->assertDontSee(route('maintenance-plans.create-order', $plan), false);
        $this->actingAs($admin)->post(route('maintenance-plans.create-order', $plan))->assertSessionHas('error', '這個計畫的設備已淘汰或停用，不能再建立保養工單。');
        $this->assertSame(0, MaintenanceOrder::count());
    }

    // 不小心改成已淘汰：只有系統管理員能「取消淘汰」改回正常（留下紀錄）；其他有設備權限的人不行。
    public function test_only_a_system_admin_can_undo_retiring_a_device(): void
    {
        $device = $this->device('DEV-OOPS', 'retired');
        $manager = $this->makeUserWithPermissions(['devices.manage', 'devices.view']);
        $this->actingAs($manager)->patch(route('devices.unretire', $device))->assertForbidden();
        $this->actingAs($manager)->get(route('devices.show', $device))->assertOk()->assertDontSee('取消淘汰');

        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $this->actingAs($admin)->get(route('devices.show', $device))->assertOk()->assertSee('取消淘汰');
        $this->actingAs($admin)->patch(route('devices.unretire', $device))->assertRedirect(route('devices.show', $device))->assertSessionHas('success');
        $this->assertSame('normal', $device->fresh()->status);
        $this->actingAs($admin)->get(route('devices.edit', $device))->assertOk();   // 改回正常後又能編輯
    }

    // 改成「已淘汰」存檔前會再確認一次（說明淘汰後就鎖住）。
    public function test_retiring_a_device_asks_for_confirmation_first(): void
    {
        $device = $this->device('DEV-ASK');
        $this->actingAs($this->makeUserWithRole('admin', '系統管理員'))->get(route('devices.edit', $device))->assertOk()
            ->assertSee('淘汰後設備資料會鎖住', false)->assertSee('確認淘汰', false);
    }
}
