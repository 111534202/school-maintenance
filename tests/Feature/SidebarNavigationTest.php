<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 左側選單：依身分權限顯示分區與連結。
 * 第一層的順序：主控台 → 主檔 → 報修服務 → 保養管理 → 空間與零件（含成本分析報表）→ 系統（洋蔥式選單，點分類才進到第二層）。
 */
class SidebarNavigationTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    /** 輔助方法：以指定用戶開主控台，回傳畫面 HTML。 */
    private function dashboardAs($user): string
    {
        return $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();
    }

    // 教師：只有報修服務與空間申請（空間與零件只剩空間申請一項，直接變成第一層的連結）；沒有保養管理、零件庫存、主檔、系統。
    public function test_teacher_sees_only_repair_services_and_reservations(): void
    {
        $html = $this->dashboardAs($this->makeUserWithRole('teacher', '教師'));

        foreach (['報修服務', '自助知識庫', '空間申請'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        foreach (['保養管理', '保養項目', '零件庫存', '用戶主檔', '設備主檔', '操作紀錄'] as $text) {
            $this->assertStringNotContainsString($text, $html);
        }
    }

    // 維修人員：多了保養管理（查看與回報），但沒有 AI 預防保養與零件庫存。
    public function test_technician_sees_maintenance_but_not_ai_or_parts(): void
    {
        $html = $this->dashboardAs($this->makeTechnician());

        $this->assertStringContainsString('保養管理', $html);
        $this->assertStringContainsString('保養工單', $html);
        $this->assertStringNotContainsString('AI 預防保養', $html);
        $this->assertStringNotContainsString('零件庫存', $html);
    }

    // 設備管理員：保養、AI、零件庫存、設備相關主檔、操作紀錄都有；但沒有用戶、身分、部門主檔。
    public function test_it_manager_sees_operations_and_device_masters_but_not_account_masters(): void
    {
        $html = $this->dashboardAs($this->makeItManager());

        foreach (['AI 預防保養', '零件庫存', '教室主檔', '設備主檔', '操作紀錄'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        foreach (['用戶主檔', '身分主檔', '部門主檔'] as $text) {
            $this->assertStringNotContainsString($text, $html);
        }
    }

    // 只有 AI 預防保養權限（沒有保養查看權限）時，保養管理只剩一個項目，直接變成第一層的連結（不顯示「保養管理」分類）。
    public function test_ai_only_user_sees_just_the_ai_link(): void
    {
        $html = $this->dashboardAs($this->makeUserWithPermissions(['ai-maintenance.manage']));

        $this->assertStringNotContainsString('data-drill-open="maintenance"', $html);
        $this->assertStringContainsString('AI 預防保養', $html);
        $this->assertStringNotContainsString('保養項目', $html);
    }

    // 第一層的分類順序固定：主檔（緊接主控台）→ 報修服務 → 保養管理 → 空間與零件 → 系統（只有一項時直接顯示成連結）。
    public function test_sections_appear_in_the_documented_order(): void
    {
        $html = $this->dashboardAs($this->makeUserWithRole('admin', '系統管理員'));
        // 只看漢堡選單（寬螢幕上方列的下拉選單也有同樣的連結，順序一樣，但不在這裡比）。
        $html = substr($html, (int) strpos($html, 'id="drill-drawer"'));

        $positions = array_map(fn ($text) => strpos($html, $text), [
            'data-drill-open="master"', 'data-drill-open="work"', 'data-drill-open="maintenance"', 'data-drill-open="space"',
            'href="' . route('audit-logs.index') . '"',
        ]);

        $this->assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions);
    }

    // 英文介面：新增的選單文字都有英文，沒有露出翻譯鍵名（例如 common.nav.xxx）。
    public function test_english_sidebar_shows_no_missing_translation_keys(): void
    {
        $admin = $this->makeUserWithRole('admin', '系統管理員');
        $html = $this->actingAs($admin)->withSession(['locale' => 'en'])->get(route('dashboard'))->assertOk()->getContent();

        // 先確認真的是英文介面（避免語言沒切成功而讓下面的檢查白白通過）。
        foreach (['Repair Services', 'Maintenance Items', 'Space Requests', 'Parts Inventory', 'AI Preventive Maintenance'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringNotContainsString('common.nav.', $html);
    }

    // 填維修紀錄頁屬於報修：選單停在「報修服務」那一層（該層預設展開）。
    public function test_repair_menu_stays_open_on_the_repair_log_page(): void
    {
        $tech = $this->makeTechnician();
        $case = \App\Models\RepairRequest::factory()->create(['status' => 'in_progress', 'assigned_to' => $tech->id]);

        $html = $this->actingAs($tech)->get(route('repair-logs.create', $case))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/class="drill-panel drill-sub is-open" data-drill-panel="work"/', $html);
    }
}
