<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Department;
use App\Models\Device;
use App\Models\DeviceCategory;
use App\Models\KnowledgeBase;
use App\Models\RepairRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

class RepairRequestFlowTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loginAsAnyUser();
    }

    private function makeDevice(array $attrs = []): Device
    {
        $department = Department::create(['name' => '資訊組']);
        $classroom = Classroom::create([
            'department_id' => $department->id,
            'campus' => '本校區',
            'building' => 'A棟',
            'floor' => '1',
            'room_code' => 'A101',
            'room_name' => 'A101 教室',
        ]);
        $category = DeviceCategory::create(['name' => '投影機']);

        return Device::create([
            'device_code' => 'DEV-A101-01',
            'device_category_id' => $category->id,
            'brand' => 'Epson',
            'model' => 'EB-X500',
            'classroom_id' => $classroom->id,
            'status' => 'normal',
            ...$attrs,
        ]);
    }

    public function test_index_page_lists_requests(): void
    {
        RepairRequest::factory()->create(['title' => 'A101 投影機無法開機']);

        $response = $this->get(route('repairs.index'));

        $response->assertStatus(200);
        $response->assertSee('A101 投影機無法開機');
    }

    public function test_create_page_loads(): void
    {
        $response = $this->get(route('repairs.create'));

        $response->assertStatus(200);
    }

    public function test_can_submit_a_repair_request(): void
    {
        $payload = [
            'title' => '教室冷氣完全不冷',
            'device_note' => 'A101 冷氣',
            'location' => 'A101',
            'description' => '出風口有風但溫度沒有下降，已檢查濾網。',
            'impact_level' => 'high',
            'affects_class' => '1',
        ];

        $response = $this->post(route('repairs.store'), $payload);

        $repairRequest = RepairRequest::firstWhere('title', '教室冷氣完全不冷');
        $response->assertRedirect(route('repairs.show', $repairRequest));
        $this->assertDatabaseHas('repair_requests', [
            'title' => '教室冷氣完全不冷',
            'impact_level' => 'high',
            'affects_class' => true,
            'status' => 'pending',
        ]);
    }

    public function test_store_requires_title_description_and_impact_level(): void
    {
        $response = $this->post(route('repairs.store'), [
            'title' => '',
            'description' => '',
            'impact_level' => '',
            'affects_class' => '0',
        ]);

        $response->assertSessionHasErrors(['title', 'description', 'impact_level']);
        $this->assertDatabaseCount('repair_requests', 0);
    }

    public function test_can_view_a_submitted_request(): void
    {
        $repairRequest = RepairRequest::factory()->create(['title' => '網路孔沒有訊號']);

        $response = $this->get(route('repairs.show', $repairRequest));

        $response->assertStatus(200);
        $response->assertSee('網路孔沒有訊號');
    }

    public function test_knowledge_base_show_page_links_to_repair_request_create(): void
    {
        $entry = KnowledgeBase::factory()->create();

        $response = $this->get(route('knowledge-base.show', $entry));

        $response->assertStatus(200);
        $response->assertSee(route('repairs.create', ['from_kb' => $entry->id]), false);
    }

    public function test_repair_request_create_prefills_from_knowledge_base(): void
    {
        $entry = KnowledgeBase::factory()->create(['title' => '投影機無法開機']);

        $response = $this->get(route('repairs.create', ['from_kb' => $entry->id]));

        $response->assertStatus(200);
        $response->assertSee('投影機無法開機');
    }

    public function test_knowledge_base_resolved_redirects_with_thank_you_message(): void
    {
        $entry = KnowledgeBase::factory()->create(['title' => '投影機無法開機']);

        $response = $this->get(route('knowledge-base.resolved', $entry));

        $response->assertRedirect(route('knowledge-base.index'));
        $response->assertSessionHas('success');
    }

    public function test_create_page_prefills_from_scanned_device(): void
    {
        $device = $this->makeDevice();

        $response = $this->get(route('repairs.create', ['device' => $device->id]));

        $response->assertStatus(200);
        $response->assertSee($device->device_code);
        $response->assertSee('Epson');
    }

    public function test_device_lookup_endpoint_returns_device_info(): void
    {
        $device = $this->makeDevice();

        $response = $this->getJson(route('repairs.device-lookup', ['code' => $device->device_code]));

        $response->assertOk();
        $response->assertJson(['id' => $device->id, 'device_code' => $device->device_code]);
    }

    public function test_device_lookup_accepts_the_full_url_encoded_in_the_qr_sticker(): void
    {
        // 設備 QR 貼紙編碼的是 route('devices.entry') 的整串網址（.../d/{device_code}），
        // 掃到整串網址也要找得到設備（曾經只認純設備編號，掃 QR 會「找不到設備」）。
        $device = $this->makeDevice();

        $response = $this->getJson(route('repairs.device-lookup', ['code' => route('devices.entry', $device)]));

        $response->assertOk();
        $response->assertJson(['id' => $device->id]);
    }

    public function test_device_lookup_accepts_asset_code_and_serial_number(): void
    {
        $device = $this->makeDevice(['asset_code' => 'AST-9001', 'serial_number' => 'SN-777']);

        $this->getJson(route('repairs.device-lookup', ['code' => 'AST-9001']))->assertOk()->assertJson(['id' => $device->id]);
        $this->getJson(route('repairs.device-lookup', ['code' => 'SN-777']))->assertOk()->assertJson(['id' => $device->id]);
    }

    public function test_device_lookup_returns_404_for_unknown_or_empty_code(): void
    {
        $this->makeDevice();

        $this->getJson(route('repairs.device-lookup', ['code' => 'NOPE-000']))->assertNotFound();
        $this->getJson(route('repairs.device-lookup'))->assertNotFound();
    }

    public function test_submitting_a_repair_request_with_device_id_links_the_real_device(): void
    {
        $device = $this->makeDevice();

        $response = $this->post(route('repairs.store'), [
            'title' => 'A101 投影機故障',
            'device_id' => $device->id,
            'description' => '開機後畫面閃爍。',
            'impact_level' => 'medium',
            'affects_class' => '1',
        ]);

        $repairRequest = RepairRequest::firstWhere('title', 'A101 投影機故障');
        $response->assertRedirect(route('repairs.show', $repairRequest));
        $this->assertSame($device->id, $repairRequest->device_id);
        $this->assertNotNull($repairRequest->reporter_id);
    }
}
