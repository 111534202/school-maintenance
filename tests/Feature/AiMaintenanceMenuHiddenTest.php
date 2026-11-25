<?php

namespace Tests\Feature;

use App\Support\NavMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/** AI 預防保養的選單開關（config/features.php）：關掉時選單不出現，其他保養選單不受影響；打開就恢復。 */
class AiMaintenanceMenuHiddenTest extends TestCase
{
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    /** 輔助方法：這位用戶選單裡的全部連結文字（第一層與第二層）。 */
    private function menuLabels($user): array
    {
        $labels = [];
        foreach (NavMenu::build($user)['menu'] as $entry) {
            foreach ($entry['type'] === 'group' ? $entry['children'] : [$entry] as $link) {
                $labels = $link['show'] ? [...$labels, $link['label']] : $labels;
            }
        }

        return $labels;
    }

    // 正式環境沒有設定時，預設就是隱藏。
    public function test_default_is_hidden(): void
    {
        $this->assertStringContainsString("env('FEATURE_AI_MAINTENANCE', false)", (string) file_get_contents(config_path('features.php')));
    }

    // 關掉：管理員、資訊組主管都看不到，其他保養選單還在。
    public function test_menu_is_hidden_when_the_feature_is_off(): void
    {
        config(['features.ai_maintenance' => false]);

        foreach ([$this->makeUserWithRole('admin', '系統管理員'), $this->makeItManager()] as $user) {
            $labels = $this->menuLabels($user);
            $this->assertNotContains('AI 預防保養', $labels);
            foreach (['保養項目', '保養計畫', '保養工單', '設備履歷'] as $label) {
                $this->assertContains($label, $labels);
            }
        }
    }

    // 打開：有權限的人又看得到。
    public function test_menu_returns_when_the_feature_is_on(): void
    {
        config(['features.ai_maintenance' => true]);

        $this->assertContains('AI 預防保養', $this->menuLabels($this->makeItManager()));
        $this->assertNotContains('AI 預防保養', $this->menuLabels($this->makeTechnician()));
    }
}
