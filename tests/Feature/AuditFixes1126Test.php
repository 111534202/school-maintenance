<?php

namespace Tests\Feature;

use App\Mail\RepairDispatchedMail;
use App\Mail\TemporaryPasswordMail;
use App\Models\Classroom;
use App\Models\ClassroomReservationSlot;
use App\Models\MaintenanceItem;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Models\Part;
use App\Models\RepairRequest;
use App\Services\DueMaintenanceOrderGenerator;
use App\Services\RepairRequestWorkflow;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesMaintenanceFixtures;
use Tests\TestCase;

/** 2026-11-26 獨立檢查發現的高嚴重度缺陷與信件連結問題的回歸測試。 */
class AuditFixes1126Test extends TestCase
{
    use CreatesMaintenanceFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-26 09:00:00');
        $this->actingAs($this->makeUser('admin'));
    }

    // ---- 修改保養計畫 ----

    private function updatePlan(MaintenancePlan $plan, array $overrides = []): void
    {
        $item = MaintenanceItem::create(['name' => '檢查項目', 'is_active' => true]);

        // 已指定單一設備的計畫不另存類別（由設備推得），所以表單不送 device_category_id。
        $this->put(route('maintenance-plans.update', $plan), array_merge([
            'name' => $plan->name,
            'cycle_days' => $plan->cycle_days,
            'start_date' => $plan->start_date->toDateString(),
            'item_ids' => [$item->id],
        ], $overrides))->assertSessionHasNoErrors();
    }

    private function planWithCompletedOrder(): MaintenancePlan
    {
        $plan = $this->makePlan($this->makeDevice(), ['start_date' => '2026-10-16', 'cycle_days' => 30, 'next_due_date' => '2026-12-15']);
        MaintenanceOrder::create([
            'maintenance_plan_id' => $plan->id, 'device_id' => $plan->device_id, 'device_category_id' => $plan->device_category_id,
            'source' => MaintenanceOrder::SOURCE_PERIODIC, 'status' => MaintenanceOrder::STATUS_COMPLETED, 'scheduled_date' => '2026-11-15',
        ]);

        return $plan;
    }

    public function test_renaming_a_plan_keeps_its_next_due_date(): void
    {
        $plan = $this->planWithCompletedOrder();

        $this->updatePlan($plan, ['name' => '只改名稱']);

        $this->assertSame('2026-12-15', $plan->fresh()->next_due_date->toDateString());
    }

    public function test_changing_the_cycle_counts_from_the_latest_order(): void
    {
        $plan = $this->planWithCompletedOrder();

        $this->updatePlan($plan, ['cycle_days' => 60]);

        $this->assertSame('2027-01-14', $plan->fresh()->next_due_date->toDateString());
    }

    public function test_changing_the_start_date_never_lands_on_or_before_an_existing_order(): void
    {
        $plan = $this->planWithCompletedOrder();

        $this->updatePlan($plan, ['start_date' => '2026-09-01']);

        // 09-01 起每 30 天：10-01、10-31、11-30（第一個晚於最後工單 11-15 的日期）。
        $this->assertSame('2026-11-30', $plan->fresh()->next_due_date->toDateString());
    }

    // ---- 工單改期 ----

    public function test_rescheduling_the_current_order_does_not_create_a_second_one(): void
    {
        $plan = $this->makePlan($this->makeDevice(), ['next_due_date' => '2026-11-25']);
        app(DueMaintenanceOrderGenerator::class)->generate(now());
        $order = MaintenanceOrder::where('maintenance_plan_id', $plan->id)->firstOrFail();

        $this->put(route('maintenance-orders.update', $order), ['scheduled_date' => '2026-12-01'])->assertSessionHasNoErrors();

        $this->assertSame('2026-12-01', $plan->fresh()->next_due_date->toDateString());
        app(DueMaintenanceOrderGenerator::class)->generate(now()->addDays(10));
        $this->assertSame(1, MaintenanceOrder::where('maintenance_plan_id', $plan->id)->count());

        // 完成改期後的工單，下一期照新日期往後推。
        $plan->fresh()->advanceDueDateAfter($order->fresh());
        $this->assertSame('2026-12-31', $plan->fresh()->next_due_date->toDateString());
    }

    // ---- 取消報修 ----

    /** @return array{0: RepairRequest, 1: Part} */
    private function rejectedRepairWithParts(): array
    {
        $repair = RepairRequest::factory()->create(['status' => 'assigned', 'created_at' => '2026-10-01 08:00:00']);
        app(RepairRequestWorkflow::class)->start($repair);
        $part = Part::create(['name' => '零件', 'specification' => 'x', 'unit_price' => 100, 'current_stock' => 10, 'safety_stock' => 1]);
        $this->post(route('repair-logs.store', $repair), [
            'cause' => '原因', 'resolution' => '處理', 'started_at' => '2026-10-12T09:00', 'ended_at' => '2026-10-12T10:00',
            'part_usages' => [['part_id' => $part->id, 'quantity' => 2]],
        ])->assertSessionHasNoErrors();
        $this->post(route('repairs.reject', $repair), ['rejection_reason' => '沒修好']);

        return [$repair->fresh(), $part->fresh()];
    }

    public function test_a_rejected_repair_with_used_parts_cannot_be_cancelled(): void
    {
        [$repair] = $this->rejectedRepairWithParts();
        $this->assertSame('in_progress', $repair->status->value);

        $this->get(route('repairs.show', $repair))->assertOk()->assertDontSee('取消工單');
        $this->delete(route('repairs.destroy', $repair))->assertSessionHas('error');

        $this->assertNotSoftDeleted($repair);
    }

    public function test_cancelling_an_in_progress_repair_frees_the_device_and_classroom(): void
    {
        $device = $this->makeDevice();
        $device->update(['is_core' => true]);
        $repair = RepairRequest::factory()->create(['status' => 'assigned', 'device_id' => $device->id, 'created_at' => '2026-10-01 08:00:00']);
        app(RepairRequestWorkflow::class)->start($repair);
        $this->assertSame('repairing', $device->fresh()->status);
        $this->assertSame('abnormal', $device->classroom()->first()->reservation_status);

        $this->delete(route('repairs.destroy', $repair))->assertSessionHas('success');

        $this->assertSoftDeleted($repair);
        $this->assertSame('normal', $device->fresh()->status);
        $this->assertNotSame('abnormal', $device->classroom()->first()->reservation_status);
    }

    public function test_cancelling_one_of_two_open_repairs_keeps_the_device_under_repair(): void
    {
        $device = $this->makeDevice();
        $first = RepairRequest::factory()->create(['status' => 'assigned', 'device_id' => $device->id, 'created_at' => '2026-10-01 08:00:00']);
        app(RepairRequestWorkflow::class)->start($first);
        RepairRequest::factory()->create(['status' => 'pending', 'device_id' => $device->id]);

        $this->delete(route('repairs.destroy', $first));

        $this->assertSame('repairing', $device->fresh()->status);
    }

    public function test_cancelling_a_pending_report_does_not_undo_a_manual_repairing_status(): void
    {
        $device = $this->makeDevice('T-MANUAL', 'repairing');
        $report = RepairRequest::factory()->create(['status' => 'pending', 'device_id' => $device->id]);

        $this->delete(route('repairs.destroy', $report))->assertSessionHas('success');

        $this->assertSame('repairing', $device->fresh()->status);
    }

    // ---- 同時操作（用過期的物件模擬另一個人剛改過資料） ----

    public function test_a_stale_reject_cannot_reopen_a_closed_repair(): void
    {
        $repair = RepairRequest::factory()->create(['status' => 'pending_review']);
        $stale = RepairRequest::findOrFail($repair->id);

        app(RepairRequestWorkflow::class)->complete($repair);

        try {
            app(RepairRequestWorkflow::class)->reject($stale, '太慢按下的退回');
            $this->fail('已結案的單不能再被退回');
        } catch (DomainException) {
            $this->assertSame('completed', $repair->fresh()->status->value);
            $this->assertNull($repair->fresh()->rejection_reason);
        }
    }

    public function test_a_stale_action_on_a_cancelled_repair_is_refused(): void
    {
        $repair = RepairRequest::factory()->create(['status' => 'assigned']);
        $stale = RepairRequest::findOrFail($repair->id);
        $repair->delete();

        $this->expectException(DomainException::class);
        app(RepairRequestWorkflow::class)->start($stale);
    }

    public function test_a_stale_abort_leaves_a_closed_repair_alone(): void
    {
        $repair = RepairRequest::factory()->create(['status' => 'pending_review']);
        $stale = RepairRequest::findOrFail($repair->id);
        app(RepairRequestWorkflow::class)->complete($repair);

        app(RepairRequestWorkflow::class)->abort($stale);

        $this->assertSame('completed', $repair->fresh()->status->value);
    }

    // ---- 忘記密碼信的連結 ----

    public function test_the_reset_mail_link_uses_the_configured_site_address(): void
    {
        config(['app.url' => 'https://school.example.edu']);
        $user = $this->makeUser('teacher');
        auth()->logout();
        Mail::fake();

        $this->withServerVariables(['HTTP_HOST' => 'attacker.example'])
            ->post('http://attacker.example/forgot-password', ['email' => $user->email]);

        Mail::assertSent(TemporaryPasswordMail::class, function (TemporaryPasswordMail $mail): bool {
            $html = $mail->render();

            return str_contains($html, 'https://school.example.edu/login') && ! str_contains($html, 'attacker.example');
        });
    }

    // ---- 派工信：使用者打的 Markdown 不能變成連結或圖片 ----

    public function test_markdown_typed_into_a_repair_stays_as_plain_text_in_the_dispatch_mail(): void
    {
        $title = '[立即重新登入](http://evil.example/login)';
        $description = '![t](http://evil.example/pixel.png)
# 大標題
投影機 A-101（燈泡）壞了！';
        $repair = RepairRequest::factory()->create(['title' => $title, 'description' => $description]);

        $mail = new RepairDispatchedMail($repair, $this->makeUser('technician'));
        $html = $mail->render();

        $this->assertStringNotContainsString('href="http://evil.example', $html);
        $this->assertStringNotContainsString('<img src="http://evil.example', $html);
        $this->assertDoesNotMatchRegularExpression('/<h1[^>]*>\s*大標題/u', $html);

        // 畫面上看到的仍然是使用者原本打的字。
        $visible = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringContainsString($title, $visible);
        $this->assertStringContainsString('投影機 A-101（燈泡）壞了！', $visible);
    }

    // ---- 管理員重設密碼 ----

    public function test_admin_password_reset_also_cancels_the_temporary_password(): void
    {
        $user = $this->makeUser('teacher');
        $user->forceFill(['temporary_password' => Hash::make('temp-secret-1'), 'temporary_password_expires_at' => now()->addMinutes(30)])->save();

        $this->post(route('users.reset-password', $user), ['password' => 'new-password-123', 'password_confirmation' => 'new-password-123'])
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertNull($user->temporary_password);
        $this->assertNull($user->temporary_password_expires_at);
        $this->assertTrue(Hash::check('new-password-123', $user->password));
    }

    // ---- 預約節次的時間以課表設定為準 ----

    private function reserve(array $slot): TestResponse
    {
        $classroom = Classroom::firstOrCreate(
            ['classroom_code' => 'R-SLOT'],
            ['campus' => '本校區', 'building' => 'A', 'floor' => '1', 'name' => '節次教室', 'is_active' => true, 'reservation_status' => 'normal']
        );
        $date = now()->addDays(3)->toDateString();

        return $this->post(route('reservations.store'), [
            'classroom_id' => $classroom->id, 'class_name' => '一年甲班', 'purpose' => '活動',
            'reservation_date' => $date, 'start_time' => '00:00', 'end_time' => '23:59',
            'slots' => [array_merge(['reservation_date' => $date, 'start_time' => '00:00', 'end_time' => '23:59'], $slot)],
        ]);
    }

    public function test_a_forged_period_cannot_occupy_the_whole_day(): void
    {
        $this->reserve(['period_number' => 1])->assertSessionHas('success');

        $slot = ClassroomReservationSlot::firstOrFail();
        $this->assertSame('08:30', substr((string) $slot->start_time, 0, 5));
        $this->assertSame('09:20', substr((string) $slot->end_time, 0, 5));
    }

    public function test_a_period_that_does_not_exist_is_refused(): void
    {
        $this->reserve(['period_number' => 99])->assertSessionHas('error');

        $this->assertSame(0, ClassroomReservationSlot::count());
    }
}
