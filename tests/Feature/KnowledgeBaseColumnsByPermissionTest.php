<?php

namespace Tests\Feature;

use App\Models\KnowledgeBase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 自助知識庫列表：「狀態」與「操作」欄只給有管理權限（knowledge-base.manage）的人；
 * 一般使用者看到的文章一定都是已上架、操作欄也沒有任何按鈕，這兩欄整欄不顯示（不是顯示空白）。
 */
class KnowledgeBaseColumnsByPermissionTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    public function test_a_regular_user_does_not_see_the_status_and_actions_columns(): void
    {
        KnowledgeBase::factory()->create(['title' => '投影機無法開機', 'is_published' => true]);

        $this->actingAs($this->makeUserWithRole('teacher', '教師'))->get(route('knowledge-base.index'))
            ->assertOk()
            ->assertSee('投影機無法開機')
            ->assertSee('查看次數')
            ->assertDontSee('<th class="text-end tb-col-fit">', false)
            ->assertDontSee('tb-pill-ok', false);
    }

    public function test_a_manager_sees_the_status_and_actions_columns(): void
    {
        KnowledgeBase::factory()->create(['title' => '投影機無法開機', 'is_published' => true]);

        $this->actingAs($this->makeUserWithPermissions(['knowledge-base.manage']))->get(route('knowledge-base.index'))
            ->assertOk()
            ->assertSee('<th class="text-end tb-col-fit">', false)
            ->assertSee('tb-pill-ok', false);
    }
}
