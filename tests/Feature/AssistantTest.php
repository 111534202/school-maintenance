<?php

namespace Tests\Feature;

use App\Models\KnowledgeBase;
use App\Models\RepairRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/** 右下角「AI 客服」：依知識庫自動回答、只查得到自己的報修、草稿不外流、按鈕依權限顯示。 */
class AssistantTest extends TestCase
{
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    /** 輔助方法：以指定用戶問一個問題。 */
    private function askAs(User $user, string $question): TestResponse
    {
        return $this->actingAs($user)->postJson(route('assistant.ask'), ['question' => $question]);
    }

    /** 輔助方法：建立一篇知識庫文章。 */
    private function article(string $title, bool $published = true, string $symptom = '現象說明'): KnowledgeBase
    {
        return KnowledgeBase::factory()->create(['title' => $title, 'category' => null, 'symptom' => $symptom, 'solution' => '請依序檢查', 'is_published' => $published]);
    }

    // 沒登入不能問。
    public function test_guests_cannot_ask(): void
    {
        $this->postJson(route('assistant.ask'), ['question' => '投影機'])->assertUnauthorized();
    }

    // 登入後的頁面有客服按鈕；放在對話框裡的頁面（?modal=1）與登入頁沒有。
    public function test_widget_is_shown_only_on_full_pages_after_login(): void
    {
        $this->get(route('login'))->assertOk()->assertDontSee('id="asstFab"', false);

        $teacher = $this->makeUserWithRole('teacher', '教師');
        $this->actingAs($teacher)->get(route('dashboard'))->assertOk()->assertSee('id="asstFab"', false);
        $this->actingAs($teacher)->get(route('knowledge-base.index', ['modal' => 1]))->assertOk()->assertDontSee('id="asstFab"', false);
    }

    // 口語問法找得到文章，並附上「我要報修」（帶文章編號）。
    public function test_finds_published_article_from_a_colloquial_question(): void
    {
        $match = $this->article('投影機無法開機');
        $this->article('教室冷氣沒有冷房效果');

        $this->askAs($this->makeUserWithRole('teacher', '教師'), '投影機開不了機怎麼辦？')->assertOk()
            ->assertJsonPath('items.0.title', '投影機無法開機')
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('actions.0.url', route('repairs.create', ['from_kb' => $match->id], false));
    }

    // 草稿不拿來回答，連知識庫管理者問也一樣。
    public function test_unpublished_articles_are_never_used(): void
    {
        $this->article('投影機無法開機', false);

        $this->askAs($this->makeItManager(), '投影機無法開機')->assertOk()
            ->assertJsonCount(0, 'items')
            ->assertJsonPath('text', __('assistant.reply.not_found'));
    }

    // 不相關的問題說找不到，不要硬湊文章。
    public function test_unrelated_question_gets_not_found(): void
    {
        $this->article('投影機無法開機');

        $this->askAs($this->makeUserWithRole('teacher', '教師'), '今天天氣如何')->assertOk()
            ->assertJsonCount(0, 'items')->assertJsonPath('text', __('assistant.reply.not_found'));
    }

    // 報修進度只列自己的；指定別人的編號也查不到（管理員也一樣，只查自己提出的）。
    public function test_progress_lists_only_own_repairs(): void
    {
        $me = $this->makeUserWithRole('teacher', '教師');
        $mine = RepairRequest::factory()->create(['requester_id' => $me->id, 'title' => '我的投影機']);
        $other = RepairRequest::factory()->create(['requester_id' => $this->makeItManager()->id, 'title' => '別人的電腦']);

        $this->askAs($me, '我的報修進度')->assertOk()->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.url', route('repairs.show', $mine, false))->assertDontSee('別人的電腦');
        $this->askAs($me, '#' . $other->id . ' 的進度')->assertOk()->assertJsonCount(0, 'items')
            ->assertJsonPath('text', __('assistant.reply.no_repairs'));
    }

    // 沒有報修權限就沒有「我要報修」；沒有保養權限就不給保養工單的按鈕。
    public function test_actions_follow_permissions(): void
    {
        $none = $this->makeUserWithPermissions([]);
        $this->askAs($none, '怎麼報修')->assertOk()->assertJsonPath('text', __('assistant.guide.repair_denied'))->assertJsonCount(0, 'actions');
        $this->askAs($none, '保養怎麼看')->assertOk()->assertJsonPath('text', __('assistant.guide.maintenance_denied'))->assertJsonCount(0, 'actions');
        $this->askAs($this->makeTechnician(), '保養怎麼看')->assertOk()->assertJsonPath('actions.0.url', route('maintenance-orders.index', [], false));
    }

    // 打招呼後面接問題時，要回答問題而不是只打招呼；英文單字裡剛好有 hi 也不算打招呼。
    public function test_greeting_does_not_swallow_the_question(): void
    {
        $this->article('投影機無法開機');
        $teacher = $this->makeUserWithRole('teacher', '教師');

        $this->askAs($teacher, '你好')->assertOk()->assertJsonPath('text', __('assistant.reply.greeting'));
        $this->askAs($teacher, '你好，投影機無法開機')->assertOk()->assertJsonPath('items.0.title', '投影機無法開機');
        $this->askAs($teacher, 'this')->assertOk()->assertJsonPath('text', __('assistant.reply.not_found'));
    }

    // 空白、太長、不是文字都擋下；奇怪的輸入不會出錯，HTML 原樣當文字處理。
    public function test_invalid_and_odd_input(): void
    {
        $teacher = $this->makeUserWithRole('teacher', '教師');

        $this->askAs($teacher, '   ')->assertStatus(422);
        $this->askAs($teacher, str_repeat('字', 201))->assertStatus(422);
        $this->actingAs($teacher)->postJson(route('assistant.ask'), ['question' => ['x']])->assertStatus(422);
        foreach (['！！！', '1', '😀', '<script>alert(1)</script>', "' OR 1=1 --", '%'] as $odd) {
            $this->askAs($teacher, $odd)->assertOk()->assertJsonStructure(['text', 'items', 'actions', 'suggestions']);
        }
    }

    // 中英文語言檔的鍵要一致，關鍵字也要相同。
    public function test_language_files_match(): void
    {
        $zh = require lang_path('zh_TW/assistant.php');
        $en = require lang_path('en/assistant.php');

        foreach (['ui', 'reply', 'action', 'guide'] as $section) {
            $this->assertSame(array_keys($zh[$section]), array_keys($en[$section]), $section);
        }
        $this->assertSame($zh['keywords'], $en['keywords']);
    }
}
