<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Device;
use App\Models\DeviceCategory;
use App\Models\RepairRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 設備、用戶、報修單、空間申請的「匯出 Excel」：列表頁右下角先開預覽視窗，
 * 勾選要匯出的欄位（columns[]）與資料（ids[]），再匯出 Excel——和零件庫存、成本分析報表一樣。
 */
class ListExportPreviewTest extends TestCase
{
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    private function classroom(): Classroom
    {
        return Classroom::firstOrCreate(['classroom_code' => 'A101'], ['campus' => '本校區', 'building' => 'A棟', 'floor' => '1F', 'name' => 'A101 教室', 'type' => '一般教室', 'is_active' => true]);
    }

    private function device(string $code): Device
    {
        $category = DeviceCategory::firstOrCreate(['name' => '投影機']);

        return Device::create(['device_code' => $code, 'device_category_id' => $category->id, 'classroom_id' => $this->classroom()->id, 'status' => 'normal', 'brand' => 'Epson']);
    }

    /** @return list<list<mixed>> 下載到的 xlsx 內容（含標題列） */
    private function downloaded(string $url): array
    {
        $response = $this->get($url)->assertOk();

        return Excel::toArray(new class implements ToArray
        {
            public function array(array $rows): void {}
        }, $response->baseResponse->getFile()->getPathname(), null, \Maatwebsite\Excel\Excel::XLSX)[0];
    }

    public function test_list_pages_have_the_preview_dialog_and_the_bottom_buttons(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $device = $this->device('DEV-001');
        $repair = RepairRequest::factory()->create(['title' => '投影機不亮', 'requester_id' => $admin->id]);
        $this->actingAs($admin);

        $this->get(route('devices.index', ['status' => 'normal']))->assertOk()
            ->assertSee('id="deviceExportModal"', false)
            ->assertSee('name="columns[]" value="serial_number"', false)
            ->assertSee('name="ids[]" value="' . $device->id . '"', false)
            ->assertSee('name="status" value="normal"', false)      // 目前的篩選條件一起送出
            ->assertSee('data-export-total="1"', false)
            ->assertDontSee('匯出 PDF')
            ->assertSeeInOrder(['class="tb-bottom-actions"', route('imports.index', 'devices'), 'data-bs-target="#deviceExportModal"', 'data-bs-target="#deviceQrModal"'], false);

        $this->get(route('users.index'))->assertOk()
            ->assertSee('id="userExportModal"', false)
            ->assertSeeInOrder(['class="tb-bottom-actions"', route('imports.index', 'users'), 'data-bs-target="#userExportModal"'], false);

        $this->get(route('repairs.index'))->assertOk()
            ->assertSee('id="repairExportModal"', false)
            ->assertSee('name="ids[]" value="' . $repair->id . '"', false)
            ->assertSeeInOrder(['class="tb-bottom-actions"', 'data-bs-target="#repairExportModal"'], false);

        $this->get(route('reservations.index'))->assertOk()
            ->assertSee('id="reservationExportModal"', false)
            ->assertDontSee('匯出 PDF')
            ->assertSeeInOrder(['class="tb-bottom-actions"', 'data-bs-target="#reservationExportModal"'], false);
    }

    public function test_device_export_keeps_only_the_ticked_columns_and_devices(): void
    {
        $this->actingAs($this->makeUserWithRole('admin', '系統管理員'));
        $this->device('DEV-001');
        $second = $this->device('DEV-002');

        $rows = $this->downloaded(route('devices.export', ['columns' => ['device_code', 'brand'], 'ids' => [$second->id]]));

        $this->assertSame([['設備編號', '品牌'], ['DEV-002', 'Epson']], $rows);
    }

    public function test_user_and_repair_exports_keep_only_the_ticked_columns_and_rows(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $other = User::factory()->create(['name' => '王小明']);
        $this->actingAs($admin);

        $rows = $this->downloaded(route('users.export', ['columns' => ['name'], 'ids' => [$other->id]]));
        $this->assertSame([['姓名'], ['王小明']], $rows);

        RepairRequest::factory()->create(['title' => '冷氣漏水', 'requester_id' => $admin->id]);
        $picked = RepairRequest::factory()->create(['title' => '投影機不亮', 'requester_id' => $admin->id]);
        $rows = $this->downloaded(route('repairs.export', ['columns' => ['id', 'title'], 'ids' => [$picked->id]]));
        $this->assertSame([['編號', '報修標題'], [$picked->id, '投影機不亮']], $rows);
    }

    public function test_ticked_ids_cannot_reach_beyond_what_the_user_may_see(): void
    {
        $teacher = $this->makeUserWithRole('teacher', '教師');
        $others = RepairRequest::factory()->create(['title' => '別人的報修', 'requester_id' => User::factory()->create()->id]);

        $rows = $this->actingAs($teacher)->downloaded(route('repairs.export', ['ids' => [$others->id]]));

        $this->assertCount(1, $rows);   // 只有標題列
    }

    public function test_without_ids_everything_matching_is_exported(): void
    {
        // 資料比預覽多（超過 500 筆）而且全選時，預覽視窗送出不帶 ids[]：匯出符合條件的全部。
        $this->actingAs($this->makeUserWithRole('admin', '系統管理員'));
        $this->device('DEV-001');
        $this->device('DEV-002');

        $rows = $this->downloaded(route('devices.export', ['columns' => ['device_code']]));

        $this->assertSame([['設備編號'], ['DEV-001'], ['DEV-002']], $rows);
    }
}
