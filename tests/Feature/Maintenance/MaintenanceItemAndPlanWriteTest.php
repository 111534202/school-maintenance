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
 *   POST  maintenance-plans                      新增保養計畫（設備或設備類別擇一、必填說明、算出下次到期日）
 *   PUT   maintenance-plans/{id}                 修改保養計畫（重算到期日；原本勾選的保養項目資料保留不動）
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

    public function test_storing_a_plan_for_a_category_saves_the_description_and_computes_the_next_due_date(): void
    {
        $category = DeviceCategory::create(['name' => '電腦']);

        $this->post(route('maintenance-plans.store'), [
            'name' => '電腦月保養',
            'targets' => ['category:' . $category->id],
            'description' => '清潔風扇、更新防毒',
            'cycle_days' => 30,
            'start_date' => '2026-10-07',
            'is_active' => 1,
        ])->assertRedirect(route('maintenance-plans.index'))->assertSessionHas('success', '保養計畫新增成功');

        $plan = MaintenancePlan::where('name', '電腦月保養')->firstOrFail();
        $this->assertSame(30, $plan->cycle_days);
        $this->assertSame($category->id, $plan->device_category_id);
        $this->assertNull($plan->device_id, '以設備類別建立的計畫不另存單一設備');
        $this->assertSame('清潔風扇、更新防毒', $plan->description);
        $this->assertSame('2026-11-06', $plan->next_due_date->toDateString(), '下次到期日 = 開始日 + 週期天數');
        $this->assertSame(0, \App\Models\MaintenanceOrder::count(), '還沒到期，不產生工單（到了 11/06 才產生）');
        $this->assertDatabaseCount('maintenance_plan_items', 0);
    }

    public function test_storing_a_plan_for_a_single_device(): void
    {
        $device = $this->makeDevice('PLAN-DEV');

        $this->post(route('maintenance-plans.store'), [
            'name' => '單台月檢', 'targets' => ['device:' . $device->id], 'description' => '檢查燈泡', 'cycle_days' => 30, 'start_date' => '2026-10-07', 'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $plan = MaintenancePlan::where('name', '單台月檢')->firstOrFail();
        $this->assertSame($device->id, $plan->device_id);
        $this->assertNull($plan->device_category_id, '選設備時類別由設備推得，不另存');
    }

    // 一個計畫只能有一個保養對象（一台設備或一個設備類別）；工單照計畫的對象建立。
    public function test_a_plan_has_exactly_one_target(): void
    {
        $category = DeviceCategory::create(['name' => '冷氣']);
        $a = $this->makeDevice('ONE-A');
        $base = ['name' => '單一對象', 'description' => '清潔', 'cycle_days' => 30, 'start_date' => '2026-10-07', 'is_active' => 1];

        $this->post(route('maintenance-plans.store'), $base + ['targets' => ['device:' . $a->id, 'category:' . $category->id]])
            ->assertSessionHasErrors(['targets' => '一個保養計畫只能選一台設備或一個設備類別。']);
        $this->post(route('maintenance-plans.store'), $base + ['targets' => []])->assertSessionHasErrors('targets');
        $this->assertDatabaseCount('maintenance_plans', 0);

        $this->post(route('maintenance-plans.store'), $base + ['targets' => ['device:' . $a->id]])->assertSessionHasNoErrors();
        $plan = MaintenancePlan::where('name', '單一對象')->firstOrFail();
        $this->assertSame($a->id, $plan->device_id);
        $this->get(route('maintenance-plans.index'))->assertSee('ONE-A（測試電腦）');
        $html = $this->get(route('maintenance-plans.edit', $plan))->assertOk()->assertSee('設備：ONE-A', false)->getContent();
        $this->assertStringNotContainsString('planTargetAdd', $html);   // 沒有「新增設備／類別」

        $this->post(route('maintenance-plans.create-order', $plan))->assertSessionHas('success');
        $order = \App\Models\MaintenanceOrder::where('maintenance_plan_id', $plan->id)->sole();
        $this->assertSame($a->id, $order->device_id);
        $this->assertSame('ONE-A（測試電腦）', $order->targetsLabel());
        $this->post(route('maintenance-plans.create-order', $plan))->assertSessionHas('error');   // 同一期不重複建立
    }

    // 下次到期日（起始日＋週期）已經過了就擋下：今天 10/07，起始日 10/01＋3 天＝10/04 已過期；改舊計畫時沒動起始日、週期就不檢查。
    public function test_a_plan_whose_next_due_date_already_passed_is_rejected(): void
    {
        $category = DeviceCategory::create(['name' => '電腦']);
        $base = ['name' => '過期計畫', 'targets' => ['category:' . $category->id], 'description' => 'x', 'is_active' => 1];

        $this->post(route('maintenance-plans.store'), $base + ['start_date' => '2026-10-01', 'cycle_days' => 3])
            ->assertSessionHasErrors(['start_date' => '依起始日加上週期設定，下次到期日是 2026-10-04，已經過了。請修改起始日或將週期調長。']);
        $this->assertDatabaseCount('maintenance_plans', 0);

        // 到期日剛好是今天：可以，而且啟用的計畫馬上產生排定今天的工單（不用等半夜排程）；下次到期日不動（做完才推）。停用的不建立。
        $this->post(route('maintenance-plans.store'), $base + ['start_date' => '2026-10-01', 'cycle_days' => 6])->assertSessionHasNoErrors()
            ->assertSessionHas('success', '保養計畫新增成功；已產生保養工單 MO-00001（排定 2026-10-07）');
        $this->assertSame(1, \App\Models\MaintenanceOrder::count());
        $this->post(route('maintenance-plans.store'), ['is_active' => 0] + $base + ['name' => '停用的', 'start_date' => '2026-10-01', 'cycle_days' => 6])->assertSessionHasNoErrors();
        $this->assertSame(1, \App\Models\MaintenanceOrder::count());

        $old = $this->planWith([]);   // 起始日 2026-01-01、週期 30 天（舊資料，到期日早就過了）
        $this->put(route('maintenance-plans.update', $old), $this->updatePayload($old, ['name' => '只改名稱']))->assertSessionHasNoErrors();
        $this->put(route('maintenance-plans.update', $old), $this->updatePayload($old, ['cycle_days' => 31]))->assertSessionHasErrors('start_date');
    }

    public function test_storing_a_plan_without_required_fields_saves_nothing(): void
    {
        $this->from(route('maintenance-plans.create'))
            ->post(route('maintenance-plans.store'), ['name' => '', 'cycle_days' => 0, 'description' => ''])
            ->assertRedirect(route('maintenance-plans.create'))
            ->assertSessionHasErrors(['name', 'cycle_days', 'start_date', 'description', 'targets']);

        // 設備／類別要真的存在（亂打的值、不存在的編號都不行），週期不能超過 3650 天；已淘汰的設備不能選。
        $retired = $this->makeDevice('RETIRED', 'retired');
        $base = ['name' => '壞資料', 'description' => 'x', 'start_date' => '2026-10-07', 'cycle_days' => 30];
        $this->post(route('maintenance-plans.store'), ['cycle_days' => 3651, 'targets' => ['category:1']] + $base)->assertSessionHasErrors('cycle_days');
        $this->post(route('maintenance-plans.store'), ['targets' => ['category:99999']] + $base)->assertSessionHasErrors('targets.0');
        $this->post(route('maintenance-plans.store'), ['targets' => ['foo']] + $base)->assertSessionHasErrors('targets.0');
        $this->post(route('maintenance-plans.store'), ['targets' => ['device:' . $retired->id]] + $base)->assertSessionHasErrors('targets.0');
        $this->post(route('maintenance-plans.store'), ['targets' => ['device:' . $retired->id, 'device:' . $retired->id]] + $base)->assertSessionHasErrors('targets.1');

        $this->assertDatabaseCount('maintenance_plans', 0);
    }

    #[DataProvider('usersWithoutManagePermission')]
    public function test_storing_a_plan_needs_the_manage_permission(array $permissions): void
    {
        $category = DeviceCategory::create(['name' => '電腦']);
        $this->actingAs($this->makeUserWithPermissions($permissions));

        $this->post(route('maintenance-plans.store'), ['name' => '不該被建立', 'targets' => ['category:' . $category->id], 'description' => 'x', 'cycle_days' => 30, 'start_date' => '2026-10-07'])
            ->assertForbidden();

        $this->assertDatabaseCount('maintenance_plans', 0);
    }

    // ───────────── PUT maintenance-plans/{id} ─────────────

    private function planWith(array $items): MaintenancePlan
    {
        $plan = MaintenancePlan::create([
            'name' => '舊計畫',
            'description' => '舊說明',
            'device_category_id' => DeviceCategory::firstOrCreate(['name' => '投影機'])->id,
            'cycle_days' => 30,
            'start_date' => '2026-01-01',
            'next_due_date' => '2026-01-31',
            'is_active' => true,
        ]);
        $plan->maintenanceItems()->sync(collect($items)->pluck('id')->all());

        return $plan;
    }

    private function updatePayload(MaintenancePlan $plan, array $override = []): array
    {
        return array_merge(['name' => '同名', 'targets' => ['category:' . $plan->device_category_id], 'description' => '舊說明', 'cycle_days' => 30, 'start_date' => '2026-01-01', 'is_active' => 1], $override);
    }

    public function test_updating_a_plan_recomputes_the_due_date_and_keeps_the_old_item_data(): void
    {
        $a = $this->item(['name' => '項目甲']);
        $plan = $this->planWith([$a]);
        $device = $this->makeDevice('NEW-DEV');

        $this->get(route('maintenance-plans.edit', $plan))->assertOk()->assertSee('舊說明')->assertSee('類別：投影機')->assertDontSee('保養項目（可複選）');
        $this->put(route('maintenance-plans.update', $plan), $this->updatePayload($plan, [
            'name' => '新計畫', 'targets' => ['device:' . $device->id], 'description' => '新說明', 'cycle_days' => 90, 'start_date' => '2026-10-07',
        ]))->assertRedirect(route('maintenance-plans.index'))->assertSessionHas('success', '保養計畫更新成功');

        $plan->refresh();
        $this->assertSame('新計畫', $plan->name);
        $this->assertSame('新說明', $plan->description);
        $this->assertSame($device->id, $plan->device_id);
        $this->assertNull($plan->device_category_id);
        $this->assertSame(90, $plan->cycle_days);
        $this->assertSame('2027-01-05', $plan->next_due_date->toDateString(), '下次到期日依新的開始日與週期重算');
        $this->assertSame([$a->id], $plan->maintenanceItems()->pluck('maintenance_items.id')->all(), '舊的保養項目資料保留');
        $this->assertDatabaseCount('maintenance_plans', 1);
    }

    public function test_updating_a_plan_can_deactivate_it(): void
    {
        $plan = $this->planWith([]);

        // 編輯畫面的啟用開關關掉時送出 is_active = 0，計畫就變成停用。
        $this->put(route('maintenance-plans.update', $plan), $this->updatePayload($plan, ['is_active' => 0]))
            ->assertRedirect(route('maintenance-plans.index'));

        $this->assertDatabaseHas('maintenance_plans', ['id' => $plan->id, 'is_active' => false]);
    }

    public function test_updating_a_plan_with_invalid_data_changes_nothing(): void
    {
        $plan = $this->planWith([]);

        $this->from(route('maintenance-plans.edit', $plan))
            ->put(route('maintenance-plans.update', $plan), ['name' => '', 'cycle_days' => 0, 'start_date' => '不是日期', 'description' => '', 'targets' => ['']])
            ->assertRedirect(route('maintenance-plans.edit', $plan))
            ->assertSessionHasErrors(['name', 'cycle_days', 'start_date', 'description', 'targets']);

        $plan->refresh();
        $this->assertSame('舊計畫', $plan->name);
        $this->assertSame('舊說明', $plan->description);
        $this->assertSame('2026-01-31', $plan->next_due_date->toDateString());
    }

    #[DataProvider('usersWithoutManagePermission')]
    public function test_updating_a_plan_needs_the_manage_permission(array $permissions): void
    {
        $plan = $this->planWith([]);
        $this->actingAs($this->makeUserWithPermissions($permissions));

        $this->put(route('maintenance-plans.update', $plan), $this->updatePayload($plan, ['name' => '被偷改', 'cycle_days' => 1]))
            ->assertForbidden();

        $this->assertDatabaseHas('maintenance_plans', ['id' => $plan->id, 'name' => '舊計畫', 'cycle_days' => 30]);
    }
}
