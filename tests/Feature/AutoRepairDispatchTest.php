<?php

namespace Tests\Feature;

use App\Actions\CreateRepairRequestFromMaintenanceNg;
use App\Mail\RepairDispatchedMail;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\RepairRequest;
use Database\Factories\RepairRequestFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 報修自動派工：收到新報修後，自動派給目前沒有進行中工單的維修人員，並通知管理員。
 * （測試環境預設關閉自動派工，這裡的測試自己打開。）
 */
class AutoRepairDispatchTest extends TestCase
{
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['repairs.auto_dispatch' => true]);
        $this->travelTo(Carbon::parse('2026-11-18 11:00:00'));
    }

    /** 教師送出一張報修單。 */
    private function submitRepair($teacher, string $title = '投影機不亮')
    {
        return $this->actingAs($teacher)->post(route('repairs.store'), [
            'title' => $title,
            'description' => '開機後沒有畫面',
            'impact_level' => 'medium',
            'is_class_affected' => 1,
            'device_note' => 'A101 教室投影機',
        ]);
    }

    private function busy($technician, string $status = 'in_progress'): RepairRequest
    {
        return RepairRequestFactory::new()->create(['status' => $status, 'assigned_to' => $technician->id, 'created_at' => '2026-11-18 08:00:00']);
    }

    public function test_a_new_repair_is_dispatched_to_an_idle_technician_and_managers_are_told(): void
    {
        Mail::fake();
        $teacher = $this->makeUserWithRole('teacher', '教師');
        $technician = $this->makeTechnician('小明');
        $manager = $this->makeUserWithRole('it_manager', '資訊組主管');
        $admin = $this->makeUserWithRole('admin', '系統管理員');

        $this->submitRepair($teacher)->assertSessionHasNoErrors()->assertSessionHas('success');

        $repair = RepairRequest::firstOrFail();
        $this->assertSame('assigned', $repair->status->value);
        $this->assertSame($technician->id, $repair->assigned_to);
        $this->assertStringContainsString($technician->name, session('success'));

        // 操作紀錄：操作人是「系統」（沒有登入者），並標記是自動派工。
        $log = AuditLog::where('action', 'assigned')->firstOrFail();
        $this->assertNull($log->user_id);
        $this->assertTrue($log->changes['auto']);
        $this->assertStringContainsString('系統自動派工', $log->description);

        // 通知：信寄給維修人員，副本給有「接收派工通知副本」權限的設備管理員與系統管理員，主旨標明自動派工。
        Mail::assertSent(RepairDispatchedMail::class, function (RepairDispatchedMail $mail) use ($technician, $manager, $admin) {
            return $mail->hasTo($technician->email)
                && $mail->hasCc($manager->email)
                && $mail->hasCc($admin->email)
                && $mail->auto === true
                && str_contains($mail->envelope()->subject, '自動派工');
        });
    }

    public function test_busy_technicians_are_skipped_but_one_waiting_for_acceptance_counts_as_idle(): void
    {
        Mail::fake();
        $teacher = $this->makeUserWithRole('teacher', '教師');
        $busy = $this->makeTechnician('忙碌的');
        $idle = $this->makeTechnician('閒置的');
        $this->busy($busy);                       // 處理中：忙碌

        $this->submitRepair($teacher);
        $this->assertSame($idle->id, RepairRequest::where('title', '投影機不亮')->value('assigned_to'));

        // 只剩「已送出維修紀錄、等驗收」的維修人員：他已經做完了，算閒置。
        $waitingReview = $this->makeTechnician('等驗收的');
        $this->busy($waitingReview, 'pending_review');
        $this->busy($idle, 'assigned');           // 剛剛那位現在也有案件了
        $this->busy($busy, 'assigned');
        $this->submitRepair($teacher, '第二張');
        $this->assertSame($waitingReview->id, RepairRequest::where('title', '第二張')->value('assigned_to'));
    }

    public function test_with_several_idle_technicians_the_one_with_the_fewest_recent_cases_gets_it(): void
    {
        Mail::fake();
        $teacher = $this->makeUserWithRole('teacher', '教師');
        $experienced = $this->makeTechnician('接得多的');
        $fresh = $this->makeTechnician('接得少的');
        RepairRequestFactory::new()->count(2)->create(['status' => 'completed', 'assigned_to' => $experienced->id, 'created_at' => '2026-11-10 09:00:00']);

        $this->submitRepair($teacher);

        $this->assertSame($fresh->id, RepairRequest::where('title', '投影機不亮')->value('assigned_to'));
    }

    public function test_without_an_idle_technician_the_repair_waits_and_is_dispatched_when_one_frees_up(): void
    {
        Mail::fake();
        $teacher = $this->makeUserWithRole('teacher', '教師');
        $technician = $this->makeTechnician();
        $working = $this->busy($technician);

        // 唯一的維修人員在忙：維持新報修，不寄信。
        $this->submitRepair($teacher, '先來的')->assertSessionHasNoErrors();
        $this->travel(5)->minutes();
        $this->submitRepair($teacher, '後來的');
        $this->assertSame(['pending', 'pending'], RepairRequest::whereIn('title', ['先來的', '後來的'])->orderBy('id')->get()->map(fn ($r) => $r->status->value)->all());
        Mail::assertNothingSent();

        // 手上的案件做完（進入待驗收）→ 排程指令把「先來的」補派給他，「後來的」繼續等。
        $working->update(['status' => 'pending_review']);
        $this->artisan('repairs:auto-dispatch')->assertExitCode(0);

        $this->assertSame('assigned', RepairRequest::where('title', '先來的')->first()->status->value);
        $this->assertSame('pending', RepairRequest::where('title', '後來的')->first()->status->value);
        Mail::assertSentCount(1);
    }

    public function test_submitting_a_repair_log_dispatches_the_waiting_repair_to_the_freed_technician(): void
    {
        Mail::fake();
        $technician = $this->makeTechnician();
        $working = RepairRequestFactory::new()->create(['status' => 'in_progress', 'assigned_to' => $technician->id, 'created_at' => '2026-11-18 08:00:00']);
        $waiting = RepairRequestFactory::new()->create(['status' => 'pending', 'created_at' => '2026-11-18 09:00:00']);

        $this->actingAs($technician)
            ->post(route('repair-logs.store', $working), ['cause' => '壞了', 'resolution' => '修好', 'started_at' => '2026-11-18 09:00', 'ended_at' => '2026-11-18 10:00'])
            ->assertSessionHasNoErrors();

        $this->assertSame('pending_review', $working->fresh()->status->value);
        $this->assertSame('assigned', $waiting->fresh()->status->value);
        $this->assertSame($technician->id, $waiting->fresh()->assigned_to);
    }

    public function test_it_can_be_switched_off(): void
    {
        config(['repairs.auto_dispatch' => false]);
        Mail::fake();
        $this->makeTechnician();

        $this->submitRepair($this->makeUserWithRole('teacher', '教師'));

        $this->assertSame('pending', RepairRequest::firstOrFail()->status->value);
        $this->artisan('repairs:auto-dispatch')->expectsOutputToContain('已關閉')->assertExitCode(0);
        $this->assertSame('pending', RepairRequest::firstOrFail()->status->value);
        Mail::assertNothingSent();
    }

    public function test_only_active_assignable_technicians_other_than_the_requester_are_chosen(): void
    {
        Mail::fake();
        $this->makeUserWithRole('admin', '系統管理員');                       // 管理員沒有「可被指派」權限
        $this->makeTechnician('已停用的')->update(['is_active' => false]);
        $technicianRequester = $this->makeTechnician('自己報修的維修人員');

        // 唯一合格的維修人員就是報修人自己：不會派給自己，維持新報修。
        $this->submitRepair($technicianRequester);
        $this->assertSame('pending', RepairRequest::firstOrFail()->status->value);
    }

    public function test_a_mail_failure_never_breaks_the_report_or_the_dispatch(): void
    {
        $teacher = $this->makeUserWithRole('teacher', '教師');
        $technician = $this->makeTechnician();
        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP 連不上'));

        $this->submitRepair($teacher)->assertSessionHasNoErrors()->assertRedirect();

        $repair = RepairRequest::firstOrFail();
        $this->assertSame('assigned', $repair->status->value);
        $this->assertSame($technician->id, $repair->assigned_to);
    }

    public function test_an_acceptor_is_designated_when_the_reporter_cannot_accept(): void
    {
        Mail::fake();
        $technician = $this->makeTechnician();
        $student = $this->makeUserWithRole('student', '學生');
        $teacher = $this->makeUserWithRole('teacher', '教師');
        $executive = $this->makeUserWithRole('executive', '主管');   // 有派工＋驗收權限
        $classroomManager = $this->makeUserWithRole('teacher', '教師', ['name' => '教室管理人']);
        $classroom = Classroom::create(['campus' => '本校區', 'building' => 'A棟', 'floor' => '1F', 'classroom_code' => 'A101', 'name' => 'A101 教室', 'type' => '一般教室', 'is_active' => true, 'reservation_status' => 'normal', 'manager_id' => $classroomManager->id]);

        // 學生報修（沒有驗收權限）：沒有教室資訊時，指定同時有派工與驗收權限的主管。
        $this->submitRepair($student, '學生的報修');
        $first = RepairRequest::where('title', '學生的報修')->firstOrFail();
        $this->assertSame($technician->id, $first->assigned_to);
        $this->assertSame($executive->id, $first->accepted_by);
        $this->assertSame($executive->name, AuditLog::where('action', 'assigned')->latest('id')->first()->changes['acceptor']);

        // 報修地點的教室有管理人（而且有驗收權限）：優先由教室管理人驗收。
        $first->update(['status' => 'pending_review']);
        $this->actingAs($student)->post(route('repairs.store'), [
            'title' => '教室的報修', 'description' => '燈壞了', 'impact_level' => 'low', 'is_class_affected' => 0,
            'device_note' => '燈', 'classroom_id' => $classroom->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame($classroomManager->id, RepairRequest::where('title', '教室的報修')->value('accepted_by'));

        // 教師自己報修（自己有驗收權限）：不指定，由報修人本人驗收。
        RepairRequest::where('title', '教室的報修')->update(['status' => 'pending_review']);
        $this->submitRepair($teacher, '教師的報修');
        $this->assertNull(RepairRequest::where('title', '教師的報修')->value('accepted_by'));
    }

    public function test_a_repair_created_from_a_maintenance_ng_is_dispatched_too(): void
    {
        Mail::fake();
        $technician = $this->makeTechnician();

        $repair = app(CreateRepairRequestFromMaintenanceNg::class)->execute('maintenance_result:1', '保養 NG：燈泡', '亮度不足', 'A101 投影機');

        $this->assertSame('assigned', $repair->status->value);
        $this->assertSame($technician->id, $repair->assigned_to);
    }
}
