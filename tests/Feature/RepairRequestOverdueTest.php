<?php

namespace Tests\Feature;

use App\Models\RepairRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

// 「逾時工單」功能的測試：判斷規則（RepairRequest::scopeOverdue / isOverdue）、
// 看板的 ?overdue=1 篩選、主控台的逾時工單卡片數字。
class RepairRequestOverdueTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('zh_TW');

        $this->loginAsAnyUser();
    }

    // 還沒結案、且預計處理時間已經過了 → 算逾時。
    public function test_open_case_past_scheduled_time_is_overdue(): void
    {
        $repairRequest = RepairRequest::factory()->create([
            'status' => 'assigned',
            'scheduled_at' => now()->subHours(3),
        ]);

        $this->assertTrue($repairRequest->is_overdue);
        $this->assertTrue(RepairRequest::overdue()->whereKey($repairRequest->id)->exists());
    }

    // 已經結案的案件，就算預計處理時間早就過了，也不算逾時（案子已經處理完了）。
    public function test_completed_case_is_never_overdue_even_if_scheduled_time_passed(): void
    {
        $repairRequest = RepairRequest::factory()->create([
            'status' => 'completed',
            'scheduled_at' => now()->subDays(2),
        ]);

        $this->assertFalse($repairRequest->is_overdue);
        $this->assertFalse(RepairRequest::overdue()->whereKey($repairRequest->id)->exists());
    }

    // 還沒填預計處理時間（例如都還沒派工）的案件，沒有期限可比較，不算逾時。
    public function test_case_without_scheduled_time_is_not_overdue(): void
    {
        $repairRequest = RepairRequest::factory()->create([
            'status' => 'pending',
            'scheduled_at' => null,
        ]);

        $this->assertFalse($repairRequest->is_overdue);
    }

    // 預計處理時間還沒到，不算逾時。
    public function test_case_with_future_scheduled_time_is_not_overdue(): void
    {
        $repairRequest = RepairRequest::factory()->create([
            'status' => 'assigned',
            'scheduled_at' => now()->addHours(5),
        ]);

        $this->assertFalse($repairRequest->is_overdue);
    }

    // 看板 ?overdue=1 只會列出逾時的案件，不會混進其他案件。
    public function test_board_overdue_filter_only_shows_overdue_cases(): void
    {
        $overdue = RepairRequest::factory()->create([
            'title' => '逾時案件',
            'status' => 'assigned',
            'scheduled_at' => now()->subDay(),
        ]);
        RepairRequest::factory()->create([
            'title' => '正常案件',
            'status' => 'assigned',
            'scheduled_at' => now()->addDay(),
        ]);

        $response = $this->get(route('repairs.index', ['overdue' => 1]));

        $response->assertOk();
        $response->assertSeeText('逾時案件');
        $response->assertDontSeeText('正常案件');
    }

    // 看板的狀態篩選多了「逾時」（?status=overdue，舊網址 ?overdue=1 一樣），而且不再顯示紅色的提醒框。
    public function test_board_status_filter_has_overdue_option_without_the_red_notice(): void
    {
        RepairRequest::factory()->create(['title' => '逾時案件', 'status' => 'assigned', 'scheduled_at' => now()->subDay()]);
        RepairRequest::factory()->create(['title' => '正常案件', 'status' => 'assigned', 'scheduled_at' => now()->addDay()]);

        $this->get(route('repairs.index', ['status' => 'overdue']))->assertOk()
            ->assertSeeText('逾時案件')->assertDontSeeText('正常案件')
            ->assertSee('<option value="overdue" selected', false)
            ->assertDontSee('目前只顯示已逾時');
    }

    // 主控台卡片點過去的篩選：進行中＝未結案（新報修＋已派工＋待驗收）、待修數＝新報修＋已派工、待派工＝新報修、逾時＝逾時；卡片數字跟看板筆數一致。
    public function test_dashboard_cards_link_to_matching_board_filters(): void
    {
        foreach (['pending' => 1, 'assigned' => 2, 'pending_review' => 3, 'completed' => 1] as $status => $count) {
            RepairRequest::factory()->count($count)->create(['status' => $status, 'title' => '案件-' . $status, 'scheduled_at' => now()->addDay()]);
        }

        $this->get(route('dashboard'))->assertOk()
            ->assertSee(route('repairs.index', ['status' => 'pending,assigned,pending_review']), false)
            ->assertSee(route('repairs.index', ['status' => 'pending,assigned']), false)
            ->assertSee(route('repairs.index', ['status' => 'overdue']), false);
        $this->assertSame(6, $this->get(route('dashboard'))->viewData('openCount'));

        $board = $this->get(route('repairs.index', ['status' => 'pending,assigned,pending_review']))->assertOk()
            ->assertSee('<option value="pending,assigned,pending_review" selected>新報修、已派工、待驗收</option>', false);
        $this->assertSame(6, $board->viewData('repairRequests')->total());
        $this->assertSame(3, $this->get(route('repairs.index', ['status' => 'pending,assigned']))->viewData('repairRequests')->total());
        $this->assertSame(1, $this->get(route('repairs.index', ['status' => 'pending']))->viewData('repairRequests')->total());
    }

    // 主控台「逾時工單」卡片的數字要跟實際逾時案件數一致。
    public function test_dashboard_overdue_count_matches_overdue_cases(): void
    {
        RepairRequest::factory()->count(2)->create([
            'status' => 'assigned',
            'scheduled_at' => now()->subHours(1),
        ]);
        RepairRequest::factory()->create([
            'status' => 'assigned',
            'scheduled_at' => now()->addHours(1),
        ]);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('overdueCount', 2);
    }
}
