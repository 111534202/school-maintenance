<?php

namespace Tests\Feature;

use App\Models\KnowledgeBase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 自助知識庫的搜尋：關鍵字比對標題、分類、故障現象與排除步驟，也可以只看某一個分類；
 * 未上架的文章一般使用者搜尋也找不到。
 */
class KnowledgeBaseSearchTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    protected function setUp(): void
    {
        parent::setUp();

        KnowledgeBase::factory()->create(['title' => '投影機無法開機', 'category' => '投影機', 'symptom' => '按下電源沒有反應', 'solution' => '檢查電源線', 'is_published' => true]);
        KnowledgeBase::factory()->create(['title' => '教室電腦無法連上網路', 'category' => '網路', 'symptom' => '網頁打不開', 'solution' => '重新插拔網路線', 'is_published' => true]);
        KnowledgeBase::factory()->create(['title' => '冷氣沒有冷房效果', 'category' => '空調', 'symptom' => '出風口不冷', 'solution' => '清洗濾網', 'is_published' => true]);
        KnowledgeBase::factory()->create(['title' => '草稿：網路交換器故障', 'category' => '網路', 'symptom' => '整間教室斷線', 'solution' => '待補', 'is_published' => false]);
    }

    public function test_keyword_matches_title_symptom_and_solution(): void
    {
        $this->actingAs($this->makeUserWithRole('teacher', '教師'));

        $this->get(route('knowledge-base.index', ['keyword' => '投影機']))
            ->assertOk()->assertSee('投影機無法開機')->assertDontSee('教室電腦無法連上網路')->assertSee('找到 1 筆');

        // 比對故障現象
        $this->get(route('knowledge-base.index', ['keyword' => '網頁打不開']))
            ->assertSee('教室電腦無法連上網路')->assertDontSee('冷氣沒有冷房效果');

        // 比對排除步驟
        $this->get(route('knowledge-base.index', ['keyword' => '濾網']))
            ->assertSee('冷氣沒有冷房效果')->assertDontSee('投影機無法開機');
    }

    public function test_category_filter_and_hidden_drafts(): void
    {
        $this->actingAs($this->makeUserWithRole('teacher', '教師'));

        $this->get(route('knowledge-base.index', ['category' => '網路']))
            ->assertOk()->assertSee('教室電腦無法連上網路')->assertDontSee('投影機無法開機')
            ->assertDontSee('草稿：網路交換器故障');   // 未上架的不會出現在一般使用者的搜尋結果

        $this->get(route('knowledge-base.index', ['keyword' => '不存在的關鍵字']))
            ->assertOk()->assertSee('找不到符合的項目');
    }

    public function test_managers_can_find_unpublished_entries(): void
    {
        $this->actingAs($this->makeUserWithRole('admin', '系統管理員'));

        $this->get(route('knowledge-base.index', ['keyword' => '交換器']))
            ->assertOk()->assertSee('草稿：網路交換器故障');
    }
}
