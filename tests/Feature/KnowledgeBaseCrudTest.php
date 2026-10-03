<?php

namespace Tests\Feature;

use App\Models\KnowledgeBase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

// 知識庫文章的新增、檢視、修改、下架、刪除流程測試（以系統管理員身分操作）。
class KnowledgeBaseCrudTest extends TestCase
{
    // 每個測試開始前都重建一份乾淨的資料庫。
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    // 每個測試開始前先登入一位系統管理員（新增、編輯、刪除需要管理權限）。
    protected function setUp(): void
    {
        parent::setUp();

        // devices/users 表合併後，所有報修/知識庫路由都要求登入。
        $this->loginAsAnyUser();
    }

    // 知識庫列表頁會列出文章。
    public function test_index_page_lists_entries(): void
    {
        KnowledgeBase::factory()->create(['title' => '投影機無法開機']);

        $response = $this->get(route('knowledge-base.index'));

        $response->assertStatus(200);
        $response->assertSee('投影機無法開機');
    }

    // 新增文章頁可以正常開啟。
    public function test_create_page_loads(): void
    {
        $response = $this->get(route('knowledge-base.create'));

        $response->assertStatus(200);
    }

    // 可以新增文章。
    public function test_can_store_a_new_entry(): void
    {
        $payload = [
            'title' => '教室冷氣不冷',
            'category' => '空調',
            'symptom' => '出風口有風但溫度沒有下降。',
            'solution' => '1. 確認遙控器設定溫度。2. 檢查濾網是否堵塞。',
            'is_published' => '1',
        ];

        $response = $this->post(route('knowledge-base.store'), $payload);

        $response->assertRedirect(route('knowledge-base.index'));
        $this->assertDatabaseHas('knowledge_base', [
            'title' => '教室冷氣不冷',
            'category' => '空調',
        ]);
    }

    // 標題、故障現象、排除步驟是必填。
    public function test_store_requires_title_symptom_and_solution(): void
    {
        $response = $this->post(route('knowledge-base.store'), [
            'title' => '',
            'symptom' => '',
            'solution' => '',
        ]);

        $response->assertSessionHasErrors(['title', 'symptom', 'solution']);
        $this->assertDatabaseCount('knowledge_base', 0);
    }

    // 可以開啟單篇文章頁。
    public function test_can_view_a_single_entry(): void
    {
        $entry = KnowledgeBase::factory()->create(['title' => '網路孔沒有訊號']);

        $response = $this->get(route('knowledge-base.show', $entry));

        $response->assertStatus(200);
        $response->assertSee('網路孔沒有訊號');
    }

    // 可以修改文章。
    public function test_can_update_an_entry(): void
    {
        $entry = KnowledgeBase::factory()->create(['title' => '舊標題']);

        $response = $this->put(route('knowledge-base.update', $entry), [
            'title' => '新標題',
            'category' => $entry->category,
            'symptom' => $entry->symptom,
            'solution' => $entry->solution,
            'is_published' => $entry->is_published ? '1' : '0',
        ]);

        $response->assertRedirect(route('knowledge-base.index'));
        $this->assertDatabaseHas('knowledge_base', [
            'id' => $entry->id,
            'title' => '新標題',
        ]);
    }

    // 可以在編輯表單把文章改成「未上架」。
    public function test_can_unpublish_an_entry_via_edit_form(): void
    {
        // 迴歸測試：checkbox 沒勾選時瀏覽器不會送出 is_published 欄位，
        // 表單必須靠隱藏欄位保底送出 0，否則舊值會被誤留著（曾經是真的 bug）。
        $entry = KnowledgeBase::factory()->create(['is_published' => true]);

        $response = $this->put(route('knowledge-base.update', $entry), [
            'title' => $entry->title,
            'category' => $entry->category,
            'symptom' => $entry->symptom,
            'solution' => $entry->solution,
            'is_published' => '0',
        ]);

        $response->assertRedirect(route('knowledge-base.index'));
        $this->assertDatabaseHas('knowledge_base', [
            'id' => $entry->id,
            'is_published' => false,
        ]);
    }

    // 可以刪除文章。
    public function test_can_delete_an_entry(): void
    {
        $entry = KnowledgeBase::factory()->create();

        $response = $this->delete(route('knowledge-base.destroy', $entry));

        $response->assertRedirect(route('knowledge-base.index'));
        $this->assertDatabaseMissing('knowledge_base', ['id' => $entry->id]);
    }
}
