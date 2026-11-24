<?php

namespace Tests\Feature;

use App\Models\RepairRequest;
use Database\Factories\RepairRequestFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 測試報告後續修正：
 * - 派工通知信寄不出去時，派工照樣成功（不能出現 500），畫面提醒「通知信沒寄出」；
 * - 報修人沒有驗收權限（學生）時，派工畫面的驗收人預設選項不能寫成「報修人本人驗收」。
 */
class DispatchMailAndAcceptorTest extends TestCase
{
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    public function test_assigning_still_works_when_the_notification_mail_fails(): void
    {
        $itManager = $this->makeItManager();
        $technician = $this->makeTechnician();
        $case = RepairRequestFactory::new()->create(['status' => 'pending']);

        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP 連線失敗'));

        $this->actingAs($itManager)
            ->post(route('repairs.assign', $case), ['assigned_to' => $technician->id])
            ->assertRedirect(route('repairs.show', $case))
            ->assertSessionHas('success', fn (string $message) => str_contains($message, '已派工') && str_contains($message, '通知信沒有寄出'));

        $case->refresh();
        $this->assertSame('assigned', $case->status->value);
        $this->assertSame($technician->id, $case->assigned_to);
    }

    public function test_reassigning_still_works_when_the_notification_mail_fails(): void
    {
        $itManager = $this->makeItManager();
        $first = $this->makeTechnician();
        $second = $this->makeTechnician();
        $case = RepairRequestFactory::new()->create(['status' => 'assigned', 'assigned_to' => $first->id]);

        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP 連線失敗'));

        $this->actingAs($itManager)
            ->post(route('repairs.reassign', $case), ['assigned_to' => $second->id])
            ->assertRedirect(route('repairs.show', $case))
            ->assertSessionHas('success', fn (string $message) => str_contains($message, '已重新指派') && str_contains($message, '通知信沒有寄出'));

        $this->assertSame($second->id, $case->fresh()->assigned_to);
    }

    public function test_a_successful_mail_adds_no_warning_to_the_message(): void
    {
        Mail::fake();
        $itManager = $this->makeItManager();
        $technician = $this->makeTechnician();
        $case = RepairRequestFactory::new()->create(['status' => 'pending']);

        $this->actingAs($itManager)
            ->post(route('repairs.assign', $case), ['assigned_to' => $technician->id])
            ->assertSessionHas('success', fn (string $message) => ! str_contains($message, '通知信'));
    }

    public function test_the_default_acceptor_option_is_honest_for_a_student_requester(): void
    {
        $student = $this->makeUserWithRole('student', '學生');
        $teacher = $this->makeUserWithRole('teacher', '教師');
        $itManager = $this->makeItManager();

        $studentCase = RepairRequest::factory()->create(['status' => 'pending', 'requester_id' => $student->id]);
        $this->actingAs($itManager)->get(route('repairs.show', $studentCase))
            ->assertOk()
            ->assertSee('不指定（只有系統管理員能驗收）')
            ->assertDontSee('報修人本人驗收（預設）');

        $teacherCase = RepairRequest::factory()->create(['status' => 'pending', 'requester_id' => $teacher->id]);
        $this->get(route('repairs.show', $teacherCase))
            ->assertOk()
            ->assertSee('報修人本人驗收（預設）');
    }
}
