<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMaintenanceFixtures;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 主控台最上面的洋蔥式「功能入口」：項目與權限跟側邊選單同一份資料（App\Support\NavMenu）。
 */
class DashboardEntryTest extends TestCase
{
    use CreatesMaintenanceFixtures;
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    /** 取出功能入口那一區的 HTML。 */
    private function entry(string $slug): string
    {
        $html = $this->actingAs($this->makeUser($slug))->get(route('dashboard'))->assertOk()->getContent();
        $this->assertSame(1, preg_match('#id="home-entry".*?</section>#s', $html, $m), '找不到功能入口');

        return $m[0];
    }

    public function test_admin_sees_every_category_as_a_tile_with_its_functions_inside(): void
    {
        $entry = $this->entry('admin');

        foreach (['work', 'maintenance', 'master'] as $id) {
            $this->assertStringContainsString('data-entry-open="'.$id.'"', $entry, "缺少分類 $id");
            $this->assertStringContainsString('data-entry-panel="'.$id.'"', $entry, "缺少第二層 $id");
        }
        // 第二層裡有原本的功能連結
        foreach (['repairs.index', 'maintenance-orders.index', 'preventive-candidates.index', 'users.index', 'devices.index'] as $route) {
            $this->assertStringContainsString('href="'.route($route).'"', $entry, "$route 應該在功能入口裡");
        }
    }

    public function test_entry_follows_permissions(): void
    {
        $teacher = $this->entry('teacher');
        $this->assertStringContainsString('data-entry-open="work"', $teacher);
        $this->assertStringNotContainsString('data-entry-open="maintenance"', $teacher);
        $this->assertStringNotContainsString('data-entry-open="master"', $teacher);
        $this->assertStringNotContainsString(route('maintenance-orders.index'), $teacher);

        $technician = $this->entry('technician');
        $this->assertStringContainsString('data-entry-open="maintenance"', $technician);
        $this->assertStringNotContainsString(route('preventive-candidates.index'), $technician);
    }

    public function test_entry_does_not_list_the_dashboard_itself(): void
    {
        $this->assertStringNotContainsString('href="'.route('dashboard').'"', $this->entry('admin'));
    }

    public function test_existing_dashboard_cards_and_charts_are_still_there(): void
    {
        $html = $this->actingAs($this->makeUser('admin'))->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('kpi-card', $html);
        $this->assertStringContainsString('chart.umd', $html);
    }
}
