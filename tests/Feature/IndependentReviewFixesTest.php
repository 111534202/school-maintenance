<?php

namespace Tests\Feature;

use App\Enums\RepairRequestStatus;
use App\Models\KnowledgeBase;
use App\Models\RepairRequest;
use App\Support\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 獨立檢測（各身分權限與操作邏輯）找到的缺陷修正，以及整合劉家芸零件庫存後的權限統一：
 *   1. 「開始處理」只能用在已派工的案件，不能把待驗收拉回處理中（待驗收退回只能走驗收退回）。
 *   4. 未上架的知識庫文章，沒有管理權限的人看不到。
 *   5. 填維修紀錄的表單，案件不是處理中就不開。
 *   另外：零件庫存改用 parts.manage 權限，不再寫死身分代碼。
 */
class IndependentReviewFixesTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    // ---- 第 1 項：驗收退回只能走「驗收不通過」（原本的「開始處理」已拿掉）----

    // 待驗收的案件，被指派的維修人員不能再填維修紀錄把它拉回去；只能由報修人驗收不通過（必填原因）。
    public function test_a_case_waiting_for_review_cannot_be_pulled_back_by_the_technician(): void
    {
        $tech = $this->makeTechnician();
        $case = RepairRequest::factory()->create(['status' => 'pending_review', 'assigned_to' => $tech->id]);
        $this->actingAs($tech);

        $this->get(route('repair-logs.create', $case))->assertRedirect(route('repairs.show', $case))->assertSessionHas('error');
        $this->post(route('repairs.return', $case), ['return_reason' => 'x'])->assertForbidden();

        $this->assertSame(RepairRequestStatus::PendingReview, $case->fresh()->status);
    }

    // 驗收不通過（報修人，必填原因）退回「已派工」，維修人員再填一次維修紀錄。
    public function test_rejection_by_the_reporter_returns_the_case_to_assigned(): void
    {
        $teacher = $this->makeUserWithRole('teacher', '教師');
        $case = RepairRequest::factory()->create(['status' => 'pending_review', 'requester_id' => $teacher->id]);
        $this->actingAs($teacher);

        $this->post(route('repairs.reject', $case), ['rejection_reason' => '還是會閃退'])->assertSessionHasNoErrors();

        $this->assertSame(RepairRequestStatus::Assigned, $case->fresh()->status);
    }

    // ---- 第 4 項：未上架的知識庫文章 ----

    private function entries(): array
    {
        return [
            KnowledgeBase::factory()->create(['title' => '已上架文章', 'is_published' => true]),
            KnowledgeBase::factory()->create(['title' => '草稿未上架文章', 'is_published' => false]),
        ];
    }

    // 一般用戶：列表只看到已上架的；直接開未上架文章的網址是 404。
    public function test_unpublished_articles_are_hidden_from_regular_users(): void
    {
        [$published, $draft] = $this->entries();
        $this->actingAs($this->makeUserWithRole('teacher', '教師'));

        $this->get(route('knowledge-base.index'))->assertOk()->assertSee('已上架文章')->assertDontSee('草稿未上架文章');
        $this->get(route('knowledge-base.show', $published))->assertOk();
        $this->get(route('knowledge-base.show', $draft))->assertNotFound();
    }

    // 知識庫管理者與管理員看得到草稿（要能編輯、上架）。
    public function test_unpublished_articles_are_visible_to_knowledge_base_managers(): void
    {
        [, $draft] = $this->entries();

        foreach ([$this->makeItManager(), $this->makeUserWithRole('admin', '系統管理員')] as $manager) {
            $this->actingAs($manager);
            $this->get(route('knowledge-base.index'))->assertOk()->assertSee('草稿未上架文章');
            $this->get(route('knowledge-base.show', $draft))->assertOk();
        }
    }

    // 報修單帶入「剛看過的知識庫文章」時，也不能用它洩漏未上架文章的標題。
    public function test_repair_form_does_not_leak_an_unpublished_article_title(): void
    {
        [, $draft] = $this->entries();
        $this->actingAs($this->makeUserWithRole('teacher', '教師'));

        $this->get(route('repairs.create', ['from_kb' => $draft->id]))->assertOk()->assertDontSee('草稿未上架文章');
    }

    // ---- 第 5 項：填維修紀錄的表單要看案件狀態 ----

    // 已結案、待驗收、已退回的案件，被指派的維修人員開表單 → 導回案件頁並顯示錯誤（已派工才能填）。
    public function test_repair_log_form_is_not_opened_unless_the_case_is_assigned(): void
    {
        $tech = $this->makeTechnician();
        $this->actingAs($tech);

        foreach (['completed', 'pending_review', 'returned'] as $status) {
            $case = RepairRequest::factory()->create(['status' => $status, 'assigned_to' => $tech->id]);
            $this->get(route('repair-logs.create', $case))
                ->assertRedirect(route('repairs.show', $case))
                ->assertSessionHas('error');
        }
    }

    // 已派工的案件照常開得起來；不是被指派的人仍然是 403（授權檢查沒有被狀態檢查取代）。
    public function test_repair_log_form_still_opens_for_the_assigned_technician_and_stays_forbidden_to_others(): void
    {
        $tech = $this->makeTechnician();
        $case = RepairRequest::factory()->create(['status' => 'assigned', 'assigned_to' => $tech->id]);

        $this->actingAs($tech);
        $this->get(route('repair-logs.create', $case))->assertOk();

        $this->actingAs($this->makeUserWithRole('teacher', '教師'));
        $this->get(route('repair-logs.create', $case))->assertForbidden();
    }

    // ---- 零件庫存權限（整合劉家芸的模組）----

    // 零件庫存由 parts.manage 控制：設備管理員預設有；維修人員、教師沒有；自訂身分勾了就能進。
    public function test_parts_inventory_is_controlled_by_the_parts_manage_permission(): void
    {
        $this->actingAs($this->makeItManager());
        $this->get(route('parts.index'))->assertOk();

        $this->actingAs($this->makeTechnician());
        $this->get(route('parts.index'))->assertForbidden();

        $this->actingAs($this->makeUserWithRole('teacher', '教師'));
        $this->get(route('parts.index'))->assertForbidden();

        $this->actingAs($this->makeUserWithPermissions(['parts.manage']));
        $this->get(route('parts.index'))->assertOk();
    }

    // 側邊選單只對有權限的人顯示「零件庫存」。
    public function test_parts_link_is_shown_only_to_people_who_can_manage_parts(): void
    {
        $this->actingAs($this->makeItManager());
        $this->get(route('dashboard'))->assertSee(route('parts.index'), false);

        $this->actingAs($this->makeUserWithRole('teacher', '教師'));
        $this->get(route('dashboard'))->assertDontSee(route('parts.index'), false);
    }

    // 權限清單、預設值、語言檔三處一致（身分主檔的勾選畫面不會缺名稱）。
    public function test_parts_permission_is_in_the_catalog_defaults_and_language_files(): void
    {
        $this->assertTrue(PermissionCatalog::exists('parts.manage'));
        $this->assertContains('parts.manage', PermissionCatalog::defaultsFor('it_manager'));
        $this->assertNotContains('parts.manage', PermissionCatalog::defaultsFor('technician'));

        foreach (['zh_TW', 'en'] as $locale) {
            $this->assertNotSame('permissions.items.parts.manage.name', __('permissions.items.parts.manage.name', [], $locale));
            $this->assertNotSame('permissions.groups.inventory', __('permissions.groups.inventory', [], $locale));
        }
    }
}
