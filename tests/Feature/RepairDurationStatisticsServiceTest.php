<?php

namespace Tests\Feature;

use App\Enums\RepairRequestStatus;
use App\Models\RepairLog;
use App\Models\RepairRequest;
use App\Models\User;
use App\Services\RepairDurationStatisticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

class RepairDurationStatisticsServiceTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    private function makeCompletedRequest(User $reporter): RepairRequest
    {
        return RepairRequest::factory()->create([
            'status' => RepairRequestStatus::Completed,
            'requester_id' => $reporter->id,
            'assigned_to' => null,
        ]);
    }

    private function makeLog(
        RepairRequest $repairRequest,
        ?string $startedAt,
        ?string $endedAt,
        ?float $totalHours
    ): RepairLog {
        return RepairLog::factory()->create([
            'repair_request_id' => $repairRequest->id,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'total_hours' => $totalHours,
        ]);
    }

    private function calculate(User $user): array
    {
        return app(RepairDurationStatisticsService::class)->forPeriod(
            $user,
            Carbon::parse('2026-10-01 00:00:00'),
            Carbon::parse('2026-10-31 23:59:59'),
        );
    }

    public function test_average_uses_cumulative_hours_per_completed_request(): void
    {
        $user = $this->makeUserWithRole('admin', '系統管理員');

        // A 報修單：9 月 2 小時，加上 10 月 3 小時，總共 5 小時。
        $requestA = $this->makeCompletedRequest($user);

        $this->makeLog(
            $requestA,
            '2026-09-30 08:00:00',
            '2026-09-30 10:00:00',
            2.0
        );

        $this->makeLog(
            $requestA,
            '2026-10-16 08:00:00',
            '2026-10-16 11:00:00',
            3.0
        );

        // B 報修單：本月 2 小時。
        $requestB = $this->makeCompletedRequest($user);

        $this->makeLog(
            $requestB,
            '2026-10-20 08:00:00',
            '2026-10-20 10:00:00',
            2.0
        );

        // 平均應為 (5 + 2) / 2 = 3.5 小時，而不是每筆紀錄的平均。
        $result = $this->calculate($user);

        $this->assertEquals(3.5, $result['average_hours']);
        $this->assertSame(2, $result['qualifying_request_count']);
        $this->assertSame(2, $result['included_request_count']);
        $this->assertSame(0, $result['missing_hours_request_count']);
    }

    public function test_stored_hours_take_precedence_and_missing_values_use_time_difference(): void
    {
        $user = $this->makeUserWithRole('admin', '系統管理員');

        $requestA = $this->makeCompletedRequest($user);

        // 已儲存 8 小時，即使起訖時間只有 1 小時，也採用 8 小時。
        $this->makeLog(
            $requestA,
            '2026-10-10 08:00:00',
            '2026-10-10 09:00:00',
            8.0
        );

        $requestB = $this->makeCompletedRequest($user);

        // total_hours 為 null，改用起訖時間計算 2 小時。
        $this->makeLog(
            $requestB,
            '2026-10-12 08:00:00',
            '2026-10-12 10:00:00',
            null
        );

        $result = $this->calculate($user);

        $this->assertEquals(5.0, $result['average_hours']);
        $this->assertSame(2, $result['included_request_count']);
        $this->assertSame(0, $result['missing_hours_request_count']);
    }

    public function test_request_with_any_uncalculable_log_is_excluded_and_counted_once(): void
    {
        $user = $this->makeUserWithRole('admin', '系統管理員');

        $incompleteRequest = $this->makeCompletedRequest($user);

        // 這筆紀錄可計算，但同一張報修單還有其他缺少工時的紀錄。
        $this->makeLog(
            $incompleteRequest,
            '2026-10-10 08:00:00',
            '2026-10-10 10:00:00',
            2.0
        );

        $this->makeLog($incompleteRequest, null, null, null);
        $this->makeLog($incompleteRequest, null, null, null);

        $validRequest = $this->makeCompletedRequest($user);

        $this->makeLog(
            $validRequest,
            '2026-10-15 08:00:00',
            '2026-10-15 11:00:00',
            3.0
        );

        $result = $this->calculate($user);

        // 不完整的報修單整張排除，缺漏數仍只計一次。
        $this->assertEquals(3.0, $result['average_hours']);
        $this->assertSame(2, $result['qualifying_request_count']);
        $this->assertSame(1, $result['included_request_count']);
        $this->assertSame(1, $result['missing_hours_request_count']);
    }

    public function test_only_completed_requests_with_an_in_period_ended_at_qualify(): void
    {
        $user = $this->makeUserWithRole('admin', '系統管理員');

        // 本月有紀錄，但報修單尚未結案，不納入。
        $openRequest = RepairRequest::factory()->create([
            'status' => RepairRequestStatus::InProgress,
            'requester_id' => $user->id,
        ]);

        $this->makeLog(
            $openRequest,
            '2026-10-10 08:00:00',
            '2026-10-10 10:00:00',
            2.0
        );

        // 已結案，但只有 9 月的結束時間，不符合本月資格。
        $oldRequest = $this->makeCompletedRequest($user);

        $this->makeLog(
            $oldRequest,
            '2026-09-10 08:00:00',
            '2026-09-10 10:00:00',
            2.0
        );

        $result = $this->calculate($user);

        $this->assertNull($result['average_hours']);
        $this->assertSame(0, $result['qualifying_request_count']);
        $this->assertSame(0, $result['included_request_count']);
        $this->assertSame(0, $result['missing_hours_request_count']);
    }

    public function test_statistics_respect_the_users_visible_repair_requests(): void
    {
        $user = $this->makeUserWithPermissions([]);
        $otherUser = User::factory()->create();

        $visibleRequest = $this->makeCompletedRequest($user);

        $this->makeLog(
            $visibleRequest,
            '2026-10-10 08:00:00',
            '2026-10-10 10:00:00',
            2.0
        );

        $hiddenRequest = $this->makeCompletedRequest($otherUser);

        $this->makeLog(
            $hiddenRequest,
            '2026-10-15 08:00:00',
            '2026-10-15 18:00:00',
            10.0
        );

        $result = $this->calculate($user);

        $this->assertEquals(2.0, $result['average_hours']);
        $this->assertSame(1, $result['qualifying_request_count']);
        $this->assertSame(1, $result['included_request_count']);
    }
}