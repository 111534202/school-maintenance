<?php

namespace Tests\Feature;

use App\Models\KnowledgeBase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/** 自助知識庫的查看次數：打開文章一次加 1、列表依次數由多到少排、修改內容不會歸零、所有人都看得到數字。 */
class KnowledgeBaseViewCountTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    public function test_opening_an_entry_counts_and_the_list_is_sorted_by_views(): void
    {
        $this->loginAsAnyUser();
        $rare = KnowledgeBase::factory()->create(['title' => '很少人看的文章', 'is_published' => true]);
        $popular = KnowledgeBase::factory()->create(['title' => '很多人看的文章', 'is_published' => true]);

        $this->get(route('knowledge-base.show', ['knowledge_base' => $popular, 'modal' => 1]))->assertOk();
        $this->get(route('knowledge-base.show', $popular))->assertOk();
        $this->get(route('knowledge-base.show', $rare))->assertOk();

        $this->assertSame(2, $popular->fresh()->view_count);
        $this->assertSame(1, $rare->fresh()->view_count);
        $this->get(route('knowledge-base.index'))->assertOk()
            ->assertSee('查看次數')
            ->assertSeeInOrder(['很多人看的文章', '很少人看的文章']);
    }

    public function test_updating_content_keeps_the_count(): void
    {
        $this->loginAsAnyUser();
        $entry = KnowledgeBase::factory()->create(['is_published' => true]);
        $entry->forceFill(['view_count' => 7])->save();

        $this->put(route('knowledge-base.update', $entry), [
            'title' => '改過的標題',
            'category' => '投影機',
            'symptom' => '改過的現象',
            'solution' => '改過的步驟',
            'is_published' => 1,
            'view_count' => 0,   // 表單就算送了也不會被改
        ]);

        $this->assertSame('改過的標題', $entry->fresh()->title);
        $this->assertSame(7, $entry->fresh()->view_count);
    }

    public function test_the_entry_in_a_modal_has_no_edit_button(): void
    {
        $this->loginAsAnyUser();
        $entry = KnowledgeBase::factory()->create(['is_published' => true]);

        $this->get(route('knowledge-base.show', ['knowledge_base' => $entry, 'modal' => 1]))
            ->assertDontSee(route('knowledge-base.edit', $entry), false);
        $this->get(route('knowledge-base.show', $entry))
            ->assertSee(route('knowledge-base.edit', $entry), false);
    }
}
