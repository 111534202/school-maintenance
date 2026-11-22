<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Device;
use App\Models\DeviceCategory;
use App\Models\ImportBatch;
use App\Models\KnowledgeBase;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceResult;
use App\Models\RepairRequest;
use App\Models\User;
use App\Support\SafeExcelValueBinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\Concerns\CreatesReservations;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 獨立審查（各身分實際操作）找到的問題的回歸測試：
 * 跟自己無關的待驗收／已結案案件不能被修改派工、500 錯誤改成明確提示、重複送出被擋、提示文字與操作紀錄。
 */
class ReviewFixesTest extends TestCase
{
    use CreatesReservations;
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    private function device(string $code = 'DEV-R1'): Device
    {
        $category = DeviceCategory::firstOrCreate(['name' => '投影機']);
        $room = Classroom::firstOrCreate(['classroom_code' => 'R101'], ['campus' => '本', 'building' => 'R', 'floor' => '1', 'name' => 'R101 教室']);

        return Device::create(['device_code' => $code, 'device_category_id' => $category->id, 'classroom_id' => $room->id, 'status' => 'normal']);
    }

    // ---------- 跟自己無關的驗收階段案件：連修改、派工、課表都不行 ----------

    public function test_a_dispatcher_cannot_edit_assign_or_cancel_an_unrelated_pending_review_case(): void
    {
        $manager = $this->makeItManager();
        $technician = $this->makeTechnician();
        $case = RepairRequest::factory()->create(['status' => 'pending_review', 'title' => '別人的待驗收']);

        $this->actingAs($manager)->get(route('repairs.edit', $case))->assertForbidden();
        $this->actingAs($manager)->put(route('repairs.update', $case), ['title' => '被改掉', 'description' => 'x', 'impact_level' => 'low', 'is_class_affected' => 0, 'device_note' => 'x'])->assertForbidden();
        $this->actingAs($manager)->post(route('repairs.assign', $case), ['assigned_to' => $technician->id])->assertForbidden();
        $this->actingAs($manager)->post(route('repairs.reassign', $case), ['assigned_to' => $technician->id])->assertForbidden();
        $this->actingAs($manager)->post(route('repairs.return', $case), ['return_reason' => 'x'])->assertForbidden();
        $this->actingAs($manager)->getJson(route('repairs.timetable', $case))->assertForbidden();
        $this->assertSame('別人的待驗收', $case->fresh()->title);
    }

    public function test_a_dispatcher_can_still_edit_an_unrelated_case_that_is_not_in_the_acceptance_stage(): void
    {
        $case = RepairRequest::factory()->create(['status' => 'pending']);

        $this->actingAs($this->makeItManager())->get(route('repairs.edit', $case))->assertOk();
    }

    public function test_the_device_profile_hides_unrelated_acceptance_stage_cases(): void
    {
        $device = $this->device();
        RepairRequest::factory()->create(['device_id' => $device->id, 'status' => 'pending_review', 'title' => '別人的待驗收投影機']);
        RepairRequest::factory()->create(['device_id' => $device->id, 'status' => 'assigned', 'title' => '已派工的投影機']);

        $this->actingAs($this->makeItManager())->get(route('device-profile.show', $device))->assertOk()
            ->assertDontSee('別人的待驗收投影機')->assertSee('已派工的投影機');
    }

    // ---------- 500 改成明確提示 ----------

    public function test_bad_filter_values_in_the_url_do_not_cause_a_server_error(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');

        // 日期格式不對：回到「沒有篩選條件的同一頁」並提示（不是回上一頁：重新整理時上一頁就是它自己，會無限轉址）。
        $this->actingAs($admin)->from(route('dashboard'))->get(route('maintenance-orders.index', ['from' => '2026-13-45', 'to' => 'abc']))
            ->assertRedirect(route('maintenance-orders.index'))->assertSessionHasErrors('from');
        $this->actingAs($admin)->from(route('repairs.index', ['created_from' => '2026-02-31']))->get(route('repairs.index', ['created_from' => '2026-02-31']))
            ->assertRedirect(route('repairs.index'));
        // 被改成陣列的篩選條件：當作沒帶，頁面照常顯示。
        foreach ([route('repairs.index', ['status' => ['x']]), route('repairs.index', ['keyword' => ['x']]), route('knowledge-base.index', ['keyword' => ['x']])] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
        $this->actingAs($admin)->getJson(route('maintenance-completion-rate.index', ['from' => 'abc']))->assertStatus(422);
        $this->actingAs($admin)->getJson(route('maintenance-completion-rate.index', ['from' => '2026-11-01']))->assertOk();
    }

    public function test_overlong_text_and_impossible_dates_are_rejected_with_a_message(): void
    {
        $teacher = $this->makeUserWithRole('teacher', '教師');
        $device = $this->device();

        $this->actingAs($teacher)->post(route('repairs.store'), ['title' => 't', 'description' => str_repeat('字', 5001), 'impact_level' => 'low', 'is_class_affected' => 0, 'device_id' => $device->id])
            ->assertSessionHasErrors('description');

        $technician = $this->makeTechnician();
        $repair = RepairRequest::factory()->create(['status' => 'assigned', 'assigned_to' => $technician->id]);
        $base = ['cause' => '壞了', 'resolution' => '修好', 'started_at' => now()->subHour()->format('Y-m-d H:i'), 'ended_at' => now()->format('Y-m-d H:i')];
        $this->actingAs($technician)->post(route('repair-logs.store', $repair), ['cause' => str_repeat('字', 5001)] + $base)->assertSessionHasErrors('cause');
        $this->actingAs($technician)->post(route('repair-logs.store', $repair), ['started_at' => '2099-01-01 08:00', 'ended_at' => '2099-01-01 09:00'] + $base)
            ->assertSessionHasErrors(['started_at', 'ended_at']);
    }

    public function test_dispatch_time_beyond_2037_is_rejected(): void
    {
        $manager = $this->makeItManager();
        $technician = $this->makeTechnician();
        $case = RepairRequest::factory()->create(['status' => 'pending']);

        $this->actingAs($manager)->post(route('repairs.assign', $case), ['assigned_to' => $technician->id, 'scheduled_at' => '2099-01-01 08:00'])
            ->assertSessionHasErrors('scheduled_at');
    }

    public function test_validation_messages_use_chinese_field_names(): void
    {
        $manager = $this->makeItManager();
        $case = RepairRequest::factory()->create(['status' => 'pending']);

        $this->actingAs($manager)->post(route('repairs.assign', $case), ['assigned_to' => $this->makeTechnician()->id, 'accepted_by' => 999999])
            ->assertSessionHasErrors('accepted_by');
        $message = session('errors')->first('accepted_by');
        $this->assertStringContainsString('驗收人', $message);
        $this->assertStringNotContainsString('accepted by', $message);
    }

    // ---------- 重新指派不清掉建議處理時間 ----------

    public function test_the_reassign_form_keeps_the_existing_scheduled_time(): void
    {
        $technician = $this->makeTechnician();
        $case = RepairRequest::factory()->create(['status' => 'assigned', 'assigned_to' => $technician->id, 'scheduled_at' => '2026-12-05 10:30:00']);

        $this->actingAs($this->makeItManager())->get(route('repairs.show', $case))->assertOk()->assertSee('value="2026-12-05T10:30"', false);
    }

    // ---------- 保養流程 ----------

    public function test_reporting_a_result_twice_redirects_with_a_chinese_message(): void
    {
        $order = MaintenanceOrder::factory()->create(['device_id' => $this->device()->id, 'status' => MaintenanceOrder::STATUS_COMPLETED]);
        MaintenanceResult::factory()->create(['maintenance_order_id' => $order->id, 'result' => 'ok']);
        $admin = $this->makeUserWithRole('admin', '系統管理員');

        $this->actingAs($admin)->get(route('maintenance-orders.results.create', $order))
            ->assertRedirect(route('maintenance-orders.show', $order))->assertSessionHas('error', '這張工單已經回報過保養結果了。');
        $this->actingAs($admin)->post(route('maintenance-orders.results.store', $order), ['result' => 'ok', 'executed_at' => now()->format('Y-m-d H:i')])
            ->assertRedirect(route('maintenance-orders.show', $order))->assertSessionHas('error');
    }

    public function test_a_future_execution_time_is_rejected_and_a_report_is_logged(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $order = MaintenanceOrder::factory()->create(['device_id' => $this->device()->id, 'status' => 'in_progress', 'assigned_to' => $admin->id]);

        $this->actingAs($admin)->post(route('maintenance-orders.results.store', $order), ['result' => 'ok', 'executed_at' => '2030-01-01 08:00'])
            ->assertSessionHasErrors('executed_at');

        $this->actingAs($admin)->post(route('maintenance-orders.results.store', $order), ['result' => 'ok', 'executed_at' => now()->subHour()->format('Y-m-d H:i')])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_logs', ['action' => 'created', 'loggable_type' => MaintenanceResult::class]);
    }

    public function test_creating_an_order_from_a_plan_twice_does_not_duplicate_it(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $plan = MaintenancePlan::factory()->create(['device_id' => $this->device()->id, 'next_due_date' => '2026-12-20']);

        $this->actingAs($admin)->post(route('maintenance-plans.create-order', $plan))->assertSessionHas('success');
        $this->actingAs($admin)->post(route('maintenance-plans.create-order', $plan))->assertSessionHas('error');
        $this->assertSame(1, MaintenanceOrder::where('maintenance_plan_id', $plan->id)->count());
    }

    // NG 轉報修由回報結果時自動建立（每台設備一張）；手動「建立報修單」的入口已拿掉，重送回報也不會再建一次。
    public function test_an_ng_result_cannot_be_turned_into_two_repair_requests(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $device = $this->device();
        $order = MaintenanceOrder::factory()->create(['device_id' => $device->id, 'status' => 'in_progress', 'assigned_to' => $admin->id]);
        $payload = ['result' => 'ng', 'executed_at' => now()->subHour()->format('Y-m-d H:i'), 'note' => '壞了', 'ng_device_ids' => [$device->id]];

        $this->actingAs($admin)->post(route('maintenance-orders.results.store', $order), $payload)->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('maintenance-orders.results.store', $order), $payload)->assertSessionHas('error');
        $this->assertSame(1, RepairRequest::count());
        $this->assertSame(1, MaintenanceResult::firstOrFail()->repairRequests()->count());
    }

    // ---------- 其他 ----------

    public function test_an_unpublished_article_title_is_not_leaked_by_the_resolved_link(): void
    {
        $teacher = $this->makeUserWithRole('teacher', '教師');
        $article = KnowledgeBase::factory()->create(['is_published' => false, 'title' => '尚未發佈的秘密文章']);

        $this->actingAs($teacher)->get(route('knowledge-base.resolved', $article))->assertNotFound();
    }

    public function test_the_reservation_error_is_shown_to_people_without_the_review_permission(): void
    {
        $teacher = $this->makeUserWithRole('teacher', '教師');

        $this->actingAs($teacher)->withSession(['error' => '這個時段已經核准給別人'])->get(route('reservations.index'))
            ->assertOk()->assertSee('這個時段已經核准給別人');
    }

    public function test_405_and_422_pages_are_chinese(): void
    {
        $teacher = $this->makeUserWithRole('teacher', '教師');

        $this->actingAs($teacher)->get('/logout')->assertStatus(405)->assertSee('這個網址不能直接開啟')->assertDontSee('Method Not Allowed');
        $html = view('errors.422')->render();
        $this->assertStringContainsString('這個操作現在無法進行', $html);
    }

    public function test_undoing_a_user_import_keeps_accounts_that_have_reservations(): void
    {
        // 資料庫要用 requester_id 查預約（用錯欄位名稱在 MySQL 會整頁 500，SQLite 卻不會報錯，所以用「有預約的帳號必須被保留」來抓）。
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $batch = ImportBatch::create(['import_type' => 'users', 'name' => 'x.xlsx', 'imported_count' => 2, 'skipped_count' => 0, 'created_by' => $admin->id]);
        $unused = User::factory()->create(['role_id' => $admin->role_id, 'last_login_at' => null]);
        $booked = User::factory()->create(['role_id' => $admin->role_id, 'last_login_at' => null]);
        $unused->forceFill(['import_batch_id' => $batch->id])->save();
        $booked->forceFill(['import_batch_id' => $batch->id])->save();
        $room = Classroom::create(['campus' => '本', 'building' => 'R', 'floor' => '1', 'classroom_code' => 'R201', 'name' => 'R201 教室']);
        $this->makeReservation(['classroom_id' => $room->id, 'requester_id' => $booked->id, 'class_name' => '測試班', 'reservation_date' => now()->addDay()->toDateString(),
            'start_time' => '08:10', 'end_time' => '09:00', 'purpose' => '班級活動', 'status' => 'pending']);

        $this->actingAs($admin)->post(route('imports.undo', ['users', $batch]))->assertSessionHas('success');
        $this->assertSoftDeleted('users', ['id' => $unused->id]);
        $this->assertNotSoftDeleted('users', ['id' => $booked->id]);
    }

    public function test_dashboard_status_counts_match_what_the_board_shows(): void
    {
        $manager = $this->makeItManager();
        RepairRequest::factory()->create(['status' => 'pending_review']);   // 跟他無關的待驗收案件
        RepairRequest::factory()->create(['status' => 'assigned']);

        $counts = collect($this->actingAs($manager)->get(route('dashboard'))->assertOk()->viewData('repairStatuses'))->pluck('count', 'key');
        // 跟他無關的待驗收案件不算進圖表（點進去的列表也看不到，數字才對得上）；其他狀態照常。
        $this->assertSame(0, (int) $counts['pending_review']);
        $this->assertSame(1, (int) $counts['assigned']);
        $this->actingAs($manager)->get(route('repairs.index', ['status' => 'pending_review']))->assertSee('目前沒有符合條件的報修案件');
    }

    public function test_a_maintenance_plan_start_date_must_be_a_sensible_date(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $payload = ['name' => '測試計畫', 'cycle_days' => 30, 'device_id' => $this->device()->id, 'item_ids' => []];

        $this->actingAs($admin)->post(route('maintenance-plans.store'), $payload + ['start_date' => '9999-12-31'])->assertSessionHasErrors('start_date');
        $this->actingAs($admin)->post(route('maintenance-plans.store'), $payload + ['start_date' => '1999-01-01'])->assertSessionHasErrors('start_date');
    }

    public function test_the_duplicate_device_code_message_mentions_disabled_devices(): void
    {
        $manager = $this->makeItManager();
        $device = $this->device('DEV-DUP');
        $device->delete();   // 停用＝軟刪除，編號仍被占用

        $this->actingAs($manager)->post(route('devices.store'), ['device_code' => 'DEV-DUP', 'device_category_id' => $device->device_category_id, 'classroom_id' => $device->classroom_id, 'status' => 'normal'])
            ->assertSessionHasErrors('device_code');
        $this->assertStringContainsString('已停用', session('errors')->first('device_code'));
    }

    public function test_the_dispatch_form_warns_when_the_reporter_cannot_accept(): void
    {
        $technician = $this->makeTechnician();   // 維修人員沒有驗收權限
        $case = RepairRequest::factory()->create(['status' => 'pending', 'requester_id' => $technician->id]);

        $this->actingAs($this->makeItManager())->get(route('repairs.show', $case))->assertOk()->assertSee('報修人沒有驗收權限，請指定驗收人');
    }

    public function test_the_dispatch_page_still_opens_when_the_reporter_account_was_deleted(): void
    {
        $reporter = $this->makeUserWithRole('teacher', '教師');
        $case = RepairRequest::factory()->create(['status' => 'pending', 'requester_id' => $reporter->id]);
        $reporter->delete();   // 帳號軟刪除後，報修人關聯會變成 null

        $this->actingAs($this->makeItManager())->get(route('repairs.show', $case))->assertOk();
    }

    public function test_array_values_in_list_filters_are_ignored_on_every_list_page(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');

        foreach (['classrooms.index', 'devices.index', 'parts.index', 'audit-logs.index', 'device-categories.index', 'users.index', 'roles.index',
            'maintenance-items.index', 'maintenance-plans.index', 'repairs.index', 'knowledge-base.index', 'maintenance-orders.index'] as $name) {
            $this->actingAs($admin)->get(route($name, ['keyword' => ['x'], 'status' => ['y']]))->assertOk();
        }
    }

    public function test_times_before_1971_are_rejected_instead_of_crashing_the_database(): void
    {
        $manager = $this->makeItManager();
        $technician = $this->makeTechnician();
        $case = RepairRequest::factory()->create(['status' => 'pending']);

        $this->actingAs($manager)->post(route('repairs.assign', $case), ['assigned_to' => $technician->id, 'scheduled_at' => '1960-01-01 08:00'])
            ->assertSessionHasErrors('scheduled_at');
    }

    public function test_excel_exports_do_not_turn_text_into_formulas(): void
    {
        $binder = new SafeExcelValueBinder;
        $sheet = (new Spreadsheet)->getActiveSheet();

        foreach (['=HYPERLINK("http://x","點我")', '+cmd', '@SUM(1)', '-cmd'] as $text) {
            $cell = $sheet->getCell('A1');
            $binder->bindValue($cell, $text);
            $this->assertSame(DataType::TYPE_STRING, $cell->getDataType(), $text);
        }
        $cell = $sheet->getCell('A2');
        $binder->bindValue($cell, '-5');
        $this->assertSame(DataType::TYPE_NUMERIC, $cell->getDataType());   // 一般負數不受影響
        $this->assertSame(SafeExcelValueBinder::class, config('excel.value_binder.default'));
    }

    public function test_future_time_errors_use_plain_words_and_the_dispatch_form_warns_for_a_deleted_reporter(): void
    {
        $technician = $this->makeTechnician();
        $repair = RepairRequest::factory()->create(['status' => 'assigned', 'assigned_to' => $technician->id]);

        $this->actingAs($technician)->post(route('repair-logs.store', $repair), ['cause' => 'a', 'resolution' => 'b', 'started_at' => now()->addDay()->format('Y-m-d H:i'), 'ended_at' => now()->addDay()->format('Y-m-d H:i')])
            ->assertSessionHasErrors('started_at');
        $message = session('errors')->first('started_at');
        $this->assertStringContainsString('不能晚於現在的時間', $message);
        $this->assertStringNotContainsString('minutes', $message);

        $reporter = $this->makeUserWithRole('teacher', '教師');
        $case = RepairRequest::factory()->create(['status' => 'pending', 'requester_id' => $reporter->id]);
        $reporter->delete();
        $this->actingAs($this->makeItManager())->get(route('repairs.show', $case))->assertOk()->assertSee('報修人沒有驗收權限，請指定驗收人');
    }

    public function test_a_device_category_with_only_disabled_devices_is_not_deleted_and_does_not_crash(): void
    {
        $device = $this->device('DEV-CAT');
        $device->delete();   // 停用＝軟刪除，但外鍵仍指著這個類別

        $this->actingAs($this->makeUserWithRole('admin', '系統管理員'))->delete(route('device-categories.destroy', $device->device_category_id))
            ->assertRedirect(route('device-categories.index'))->assertSessionHas('error');
        $this->assertDatabaseHas('device_categories', ['id' => $device->device_category_id]);
    }
}
