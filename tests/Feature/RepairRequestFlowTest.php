<?php

namespace Tests\Feature;

use App\Models\KnowledgeBase;
use App\Models\RepairRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepairRequestFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_page_lists_requests(): void
    {
        RepairRequest::factory()->create(['title' => 'A101 投影機無法開機']);

        $response = $this->get(route('repair-requests.index'));

        $response->assertStatus(200);
        $response->assertSee('A101 投影機無法開機');
    }

    public function test_create_page_loads(): void
    {
        $response = $this->get(route('repair-requests.create'));

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

        $response = $this->post(route('repair-requests.store'), $payload);

        $repairRequest = RepairRequest::firstWhere('title', '教室冷氣完全不冷');
        $response->assertRedirect(route('repair-requests.show', $repairRequest));
        $this->assertDatabaseHas('repair_requests', [
            'title' => '教室冷氣完全不冷',
            'impact_level' => 'high',
            'affects_class' => true,
            'status' => 'pending',
        ]);
    }

    public function test_store_requires_title_description_and_impact_level(): void
    {
        $response = $this->post(route('repair-requests.store'), [
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

        $response = $this->get(route('repair-requests.show', $repairRequest));

        $response->assertStatus(200);
        $response->assertSee('網路孔沒有訊號');
    }

    public function test_knowledge_base_show_page_links_to_repair_request_create(): void
    {
        $entry = KnowledgeBase::factory()->create();

        $response = $this->get(route('knowledge-base.show', $entry));

        $response->assertStatus(200);
        $response->assertSee(route('repair-requests.create', ['from_kb' => $entry->id]), false);
    }

    public function test_repair_request_create_prefills_from_knowledge_base(): void
    {
        $entry = KnowledgeBase::factory()->create(['title' => '投影機無法開機']);

        $response = $this->get(route('repair-requests.create', ['from_kb' => $entry->id]));

        $response->assertStatus(200);
        $response->assertSee('投影機無法開機');
    }

    public function test_knowledge_base_resolved_redirects_with_thank_you_message(): void
    {
        $entry = KnowledgeBase::factory()->create(['title' => '投影機無法開機']);

        $response = $this->get(route('knowledge-base.resolved', $entry));

        $response->assertRedirect(route('knowledge-base.index'));
        $response->assertSessionHas('status');
    }
}
