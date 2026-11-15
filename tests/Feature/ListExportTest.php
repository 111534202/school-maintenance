<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Device;
use App\Models\DeviceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 設備、用戶、操作紀錄的「匯出 Excel」：依目前篩選條件輸出、寫操作紀錄、有權限才能用、用戶名單不含密碼。
 */
class ListExportTest extends TestCase
{
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    private function device(string $code, string $status = 'normal'): Device
    {
        $category = DeviceCategory::firstOrCreate(['name' => '投影機']);
        $classroom = Classroom::firstOrCreate(['classroom_code' => 'A101'], ['campus' => '本校區', 'building' => 'A棟', 'floor' => '1F', 'name' => 'A101 教室', 'type' => '一般教室', 'is_active' => true, 'reservation_status' => 'normal']);

        return Device::create(['device_code' => $code, 'device_category_id' => $category->id, 'classroom_id' => $classroom->id, 'status' => $status, 'is_core' => false, 'brand' => 'Epson']);
    }

    /**
     * 發出匯出請求，把下載到的 xlsx 讀回來。
     *
     * @return array{0: string, 1: list<list<mixed>>} [檔名, 第一張工作表所有列（含標題列）]
     */
    private function downloadRows(string $url): array
    {
        $response = $this->get($url)->assertOk();
        $name = rawurldecode(explode("filename*=utf-8''", $response->headers->get('content-disposition'))[1] ?? $response->headers->get('content-disposition'));
        $path = $response->baseResponse->getFile()->getPathname();
        $sheets = Excel::toArray(new class implements ToArray
        {
            public function array(array $rows): void {}
        }, $path, null, \Maatwebsite\Excel\Excel::XLSX);

        return [$name, $sheets[0]];
    }

    public function test_device_export_follows_the_current_filters_and_logs(): void
    {
        $this->loginAsAnyUser();
        $this->device('DEV-001');
        $this->device('DEV-002', 'repairing');

        [$name, $rows] = $this->downloadRows(route('devices.export', ['status' => 'repairing']));

        $this->assertStringContainsString('設備清單-', $name);
        $this->assertCount(2, $rows);                       // 標題列＋符合篩選的 1 台
        $this->assertSame('DEV-002', $rows[1][0]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'downloaded']);
    }

    public function test_user_export_has_no_password_and_respects_filters(): void
    {
        $admin = $this->loginAsAnyUser();
        $other = $this->makeUserWithRole('teacher', '教師');
        $other->forceFill(['is_active' => false])->save();

        [, $rows] = $this->downloadRows(route('users.export', ['status' => 'inactive']));

        $this->assertCount(2, $rows);
        $this->assertSame($other->name, $rows[1][1]);
        $flat = json_encode($rows, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString((string) $admin->name, $flat);
        $this->assertStringNotContainsString($other->password, $flat);
        $this->assertNotContains('密碼', $rows[0]);
    }

    public function test_audit_log_export_requires_the_permission_and_leaves_out_change_snapshots(): void
    {
        $this->loginAsAnyUser();

        [$name, $rows] = $this->downloadRows(route('audit-logs.export'));

        $this->assertStringContainsString('操作紀錄-', $name);
        $this->assertNotContains('變更內容', $rows[0]);

        auth()->logout();
        $teacher = $this->makeUserWithRole('teacher', '教師');
        $this->actingAs($teacher)->get(route('audit-logs.export'))->assertForbidden();
        $this->actingAs($teacher)->get(route('devices.export'))->assertForbidden();
        $this->actingAs($teacher)->get(route('users.export'))->assertForbidden();
    }

    public function test_export_buttons_show_on_the_list_pages(): void
    {
        $this->loginAsAnyUser();

        $this->get(route('devices.index'))->assertSee(route('devices.export'), false);
        $this->get(route('users.index'))->assertSee(route('users.export'), false);
        $this->get(route('audit-logs.index'))->assertSee(route('audit-logs.export'), false);
    }
}
