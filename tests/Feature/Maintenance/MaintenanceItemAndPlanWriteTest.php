<?php

namespace Tests\Feature\Maintenance;

use App\Models\DeviceCategory;
use App\Models\MaintenanceItem;
use App\Models\MaintenancePlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesMaintenanceFixtures;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 保養項目與保養計畫的四條寫入路由（之前沒有測試）：
 *   PUT   maintenance-items/{id}                 修改保養項目
 *   PATCH maintenance-items/{id}/toggle-status   啟用／停用保養項目
 *   POST  maintenance-plans                      新增保養計畫（順便寫入計畫的保養項目、算出下次到期日）
 *   PUT   maintenance-plans/{id}                 修改保養計畫（重算到期日、同步保養項目）
 * 每條都測四件事：成功時資料真的寫對、驗證失敗時什麼都不寫、沒有管理權限（maintenance.manage）會被擋下。
 */
class MaintenanceItemAndPlanWriteTest extends TestCase
{
    use CreatesMaintenanceFixtures;
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-07 09:00:00'));
        $this->actingAs($this->makeUser('admin'));
    }

    private function item(array $attrs = []): MaintenanceItem
    {
        return MaintenanceItem::create(array_merge([
            'name' => '清潔風扇',
            'description' => '用氣槍清除灰塵',
            'default_cycle_days' => 90,
            'is_active' => true,
        ], $attrs));
    }

    /** 沒有 maintenance.manage 的身分：只能看（view）、回報（report），或什麼都沒有。 */
    public static function usersWithoutManagePermission(): array
    {
        return [
            '只有查看與回報' => [['maintenance.view', 'maintenance.report']],
            '只有查看' => [['maintenance.view']],
            '沒有任何保養權限' => [[]],
        ];
    }

    // ───────────── PUT maintenance-items/{id} ─────────────

    public function test_updating_an_item_saves_every_field(): void
    {
        $item = $this->item();
        $category = DeviceCategory::create(['name' => '投影機']);

        $this->put(route('maintenance-items.update', $item), [
            'name' => '更換濾網',
            'description' => '更換投影機進氣濾網',
            'default_cycle_days' => 180,
            'device_category_id' => $category->id,
            'is_active' => 1,
        ])->assertRedirect(route('maintenance-items.index'))->assertSessionHas('success', '保養項目更新成功');

        $this->assertDatabaseHas('maintenance_items', [
            'id' => $item->id,
            'name' => '更換濾網',
            'description' => '更換投影機進氣濾網',
            'default_cycle_days' => 180,
            'device_category_id' => $category->id,
        ]);
    }

    public function test_updating_an_item_with_invalid_data_changes_nothing(): void
    {
        $item = $this->item();

        $this->from(route('maintenance-items.edit', $item))
            ->put(route('maintenance-items.update', $item), [
                'name' => '',                       // 必填
                'default_cycle_days' => 0,          // 至少 1 天
                'device_category_id' => 99999,      // 不存在的設備類別
            ])
            ->assertRedirect(route('maintenance-items.edit', $item))
            ->assertSessionHasErrors(['name', 'default_cycle_days', 'device_category_id']);

        $this->assertDatabaseHas('maintenance_items', ['id' => $item->id, 'name' => '清潔風扇', 'default_cycle_days' => 90]);

        // 週期上限 3650 天，名稱最長 150 字
        $this->put(route('maintenance-items.update', $item), ['name' => str_repeat('字', 151), 'default_cycle_days' => 3651])
            ->assertSessionHasErrors(['name', 'default_cycle_days']);
    }

    #[DataProvider('usersWithoutManagePermission')]
    public function test_updating_an_item_needs_the_manage_permission(array $permissions): void
    {
        $item = $this->item();
        $this->actingAs($this->makeUserWithPermissions($permissions));

        $this->put(route('maintenance-items.update', $item), ['name' => '被偷改'])->assertForbidden();

        $this->assertDatabaseHas('maintenance_items', ['id' => $item->id, 'name' => '清潔風扇']);
    }

    // ───────────── PATCH maintenance-items/{id}/toggle-status ─────────────

    public function test_toggle_status_flips_an_item_between_active_and_inactive(): void
    {
        $item = $this->item(['is_active' => true]);

        $this->patch(route('maintenance-items.toggle-status', $item))
            ->assertRedirect(route('maintenance-items.index'))->assertSessionHas('success', '已停用');
        $this->assertDatabaseHas('maintenance_items', ['id' => $item->id, 'is_active' => false]);

        $this->patch(route('maintenance-items.toggle-status', $item))
            ->assertRedirect(route('maintenance-items.index'))->assertSessionHas('success', '已重新啟用');
        $this->assertDatabaseHas('maintenance_items', ['id' => $item->id, 'is_active' => true]);
    }

    #[DataProvider('usersWithoutManagePermission')]
    public function test_toggling_an_item_needs_the_manage_permission(array $permissions): void
    {
        $item = $this->item(['is_active' => true]);
        $this->actingAs($this->makeUserWithPermissions($permissions));

        $this->patch(route('maintenance-items.toggle-status', $item))->assertForbidden();

        $this->assertDatabaseHas('maintenance_items', ['id' => $item->id, 'is_active' => true]);
    }

    // ───────────── POST maintenance-plans ─────────────

    public function test_storing_a_plan_saves_items_and_computes_the_next_due_date(): void
    {
        $category = DeviceCategory::create(['name' => '電腦']);
        $a = $this->item(['name' => '清潔風扇']);
        $b = $this->item(['name' => '更新防毒']);

        $this->post(route('maintenance-plans.store'), [
            'name' => '電腦月保養',
            'device_category_id' => $category->id,
            'cycle_days' => 30,
            'start_date' => '2026-10-07',
            'is_active' => 1,
            'item_ids' => [$a->id, $b->id],
        ])->assertRedirect(route('maintenance-plans.index'))->assertSessionHas('success', '保養計畫新增成功');

        $plan = MaintenancePlan::where('name', '電腦月保養')->firstOrFail();
        $this->assertSame(30, $plan->cycle_days);
        $this->assertSame($category->id, $plan->device_category_id);
        $this->assertNull($plan->device_id, '以設備類別建立的計畫不另存單一設備');
        $this->assertSame('2026-11-06', $plan->next_due_date->toDateString(), '下次到期日 = 開始日 + 週期天數');
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $plan->maintenanceItems()->pluck('maintenance_items.id')->all());
    }

    public function test_storing_a_plan_without_required_fields_saves_nothing(): void
    {
        $this->from(route('maintenance-plans.create'))
            ->post(route('maintenance-plans.store'), ['name' => '', 'cycle_days' => 0, 'item_ids' => []])
            ->assertRedirect(route('maintenance-plans.create'))
            ->assertSessionHasErrors(['name', 'cycle_days', 'start_date', 'item_ids']);

        // 保養項目要真的存在，週期不能超過 3650 天
        $this->post(route('maintenance-plans.store'), ['name' => '壞資料', 'cycle_days' => 3651, 'start_date' => '2026-10-07', 'item_ids' => [99999]])
            ->assertSessionHasErrors(['cycle_days', 'item_ids.0']);

        $this->assertDatabaseCount('maintenance_plans', 0);
        $this->assertDatabaseCount('maintenance_plan_items', 0);
    }

    #[DataProvider('usersWithoutManagePermission')]
    public function test_storing_a_plan_needs_the_manage_permission(array $permissions): void
    {
        $item = $this->item();
        $this->actingAs($this->makeUserWithPermissions($permissions));

        $this->post(route('maintenance-plans.store'), ['name' => '不該被建立', 'cycle_days' => 30, 'start_date' => '2026-10-07', 'item_ids' => [$item->id]])
            ->assertForbidden();

        $this->assertDatabaseCount('maintenance_plans', 0);
    }

    // ───────────── PUT maintenance-plans/{id} ─────────────

    private function planWith(array $items): MaintenancePlan
    {
        $plan = MaintenancePlan::create([
            'name' => '舊計畫',
            'cycle_days' => 30,
            'start_date' => '2026-01-01',
            'next_due_date' => '2026-01-31',
            'is_active' => true,
        ]);
        $plan->maintenanceItems()->sync(collect($items)->pluck('id')->all());

        return $plan;
    }

    public function test_updating_a_plan_recomputes_the_due_date_and_replaces_its_items(): void
    {
        $a = $this->item(['name' => '項目甲']);
        $b = $this->item(['name' => '項目乙']);
        $c = $this->item(['name' => '項目丙']);
        $plan = $this->planWith([$a, $b]);

        $this->put(route('maintenance-plans.update', $plan), [
            'name' => '新計畫',
            'cycle_days' => 90,
            'start_date' => '2026-10-07',
            'is_active' => 1,
            'item_ids' => [$b->id, $c->id],      // 拿掉甲、保留乙、新增丙
        ])->assertRedirect(route('maintenance-plans.index'))->assertSessionHas('success', '保養計畫更新成功');

        $plan->refresh();
        $this->assertSame('新計畫', $plan->name);
        $this->assertSame(90, $plan->cycle_days);
        $this->assertSame('2027-01-05', $plan->next_due_date->toDateString(), '下次到期日依新的開始日與週期重算');
        $this->assertEqualsCanonicalizing([$b->id, $c->id], $plan->maintenanceItems()->pluck('maintenance_items.id')->all());
        $this->assertDatabaseCount('maintenance_plans', 1);
    }

    public function test_updating_a_plan_can_deactivate_it(): void
    {
        $item = $this->item();
        $plan = $this->planWith([$item]);

        // 編輯畫面的啟用開關關掉時送出 is_active = 0，計畫就變成停用。
        $this->put(route('maintenance-plans.update', $plan), ['name' => '同名', 'cycle_days' => 30, 'start_date' => '2026-01-01', 'is_active' => 0, 'item_ids' => [$item->id]])
            ->assertRedirect(route('maintenance-plans.index'));

        $this->assertDatabaseHas('maintenance_plans', ['id' => $plan->id, 'is_active' => false]);
    }

    public function test_updating_a_plan_with_invalid_data_changes_nothing(): void
    {
        $item = $this->item();
        $plan = $this->planWith([$item]);

        $this->from(route('maintenance-plans.edit', $plan))
            ->put(route('maintenance-plans.update', $plan), ['name' => '', 'cycle_days' => 0, 'start_date' => '不是日期', 'item_ids' => []])
            ->assertRedirect(route('maintenance-plans.edit', $plan))
            ->assertSessionHasErrors(['name', 'cycle_days', 'start_date', 'item_ids']);

        $plan->refresh();
        $this->assertSame('舊計畫', $plan->name);
        $this->assertSame('2026-01-31', $plan->next_due_date->toDateString());
        $this->assertSame([$item->id], $plan->maintenanceItems()->pluck('maintenance_items.id')->all());
    }

    #[DataProvider('usersWithoutManagePermission')]
    public function test_updating_a_plan_needs_the_manage_permission(array $permissions): void
    {
        $item = $this->item();
        $plan = $this->planWith([$item]);
        $this->actingAs($this->makeUserWithPermissions($permissions));

        $this->put(route('maintenance-plans.update', $plan), ['name' => '被偷改', 'cycle_days' => 1, 'start_date' => '2026-10-07', 'item_ids' => [$item->id]])
            ->assertForbidden();

        $this->assertDatabaseHas('maintenance_plans', ['id' => $plan->id, 'name' => '舊計畫', 'cycle_days' => 30]);
    }
}
