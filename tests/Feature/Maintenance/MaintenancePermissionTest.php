<?php

namespace Tests\Feature\Maintenance;

use App\Models\MaintenanceOrder;
use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesMaintenanceFixtures;
use Tests\TestCase;

/**
 * 保養模組的四個權限（maintenance.view / maintenance.manage / maintenance.report / ai-maintenance.manage）：
 * 五個內建身分各自能開哪些頁、看到哪些選單與按鈕，以及舊資料庫的補值 migration。
 */
class MaintenancePermissionTest extends TestCase
{
    use CreatesMaintenanceFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-07 09:00:00'));
    }

    /** 建立一張「還沒回報結果」的工單，回傳 [工單, 設備, 計畫]。 */
    private function fixtures(): array
    {
        $device = $this->makeDevice('PERM-1');
        $plan = $this->makePlan($device);
        $order = MaintenanceOrder::create([
            'maintenance_plan_id' => $plan->id,
            'device_id' => $device->id,
            'device_category' => $device->category?->name,
            'source' => MaintenanceOrder::SOURCE_PERIODIC,
            'status' => MaintenanceOrder::STATUS_PENDING,
            'scheduled_date' => now()->toDateString(),
        ]);

        return [$order, $device, $plan];
    }

    /**
     * 每個內建身分在每一類頁面的預期結果（true = 進得去、false = 403）。
     * 欄位順序：view（查看類）、manage（管理類）、report（回報結果）、ai（AI 預防保養）。
     */
    public static function roleMatrix(): array
    {
        return [
            'admin 全開' => ['admin', true, true, true, true],
            'it_manager 全開' => ['it_manager', true, true, true, true],
            'technician 看＋回報' => ['technician', true, false, true, false],
            'executive 只看' => ['executive', true, false, false, false],
            'teacher 都不行' => ['teacher', false, false, false, false],
        ];
    }

    #[DataProvider('roleMatrix')]
    public function test_each_default_role_only_reaches_the_pages_it_is_allowed_to(
        string $slug, bool $view, bool $manage, bool $report, bool $ai
    ): void {
        [$order, $device, $plan] = $this->fixtures();
        $this->actingAs($this->makeUser($slug));

        $expect = fn (bool $allowed) => $allowed ? 200 : 403;

        // 查看類
        $this->get(route('maintenance-items.index'))->assertStatus($expect($view));
        $this->get(route('maintenance-plans.index'))->assertStatus($expect($view));
        $this->get(route('maintenance-orders.index'))->assertStatus($expect($view));
        $this->get(route('maintenance-orders.show', $order))->assertStatus($expect($view));
        $this->get(route('device-profile.index'))->assertStatus($expect($view));
        $this->get(route('device-profile.show', $device))->assertStatus($expect($view));
        $this->get(route('maintenance-completion-rate.index'))->assertStatus($expect($view));

        // 管理類
        $this->get(route('maintenance-items.create'))->assertStatus($expect($manage));
        $this->get(route('maintenance-plans.create'))->assertStatus($expect($manage));
        $this->get(route('maintenance-plans.edit', $plan))->assertStatus($expect($manage));

        // 回報結果
        $this->get(route('maintenance-orders.results.create', $order))->assertStatus($expect($report));

        // AI 預防保養
        $this->get(route('preventive-candidates.index'))->assertStatus($expect($ai));
        $this->get(route('ai-settings.edit'))->assertStatus($expect($ai));
    }

    public function test_write_actions_are_blocked_without_the_matching_permission(): void
    {
        [$order, , $plan] = $this->fixtures();

        // 維修人員：能看、能回報，但不能改計畫、不能由計畫建工單、不能掃描 AI。
        $this->actingAs($this->makeUser('technician'));
        $this->post(route('maintenance-items.store'), ['name' => 'x'])->assertForbidden();
        $this->patch(route('maintenance-plans.toggle-status', $plan))->assertForbidden();
        $this->post(route('maintenance-plans.create-order', $plan))->assertForbidden();
        $this->post(route('preventive-candidates.scan'))->assertForbidden();
        $this->assertSame(1, MaintenanceOrder::count(), '被擋下時不可以建立新工單');

        // 主管：只能看，連回報結果的送出動作也被擋。
        $this->actingAs($this->makeUser('executive'));
        $this->post(route('maintenance-orders.results.store', $order), ['result' => 'ok', 'executed_by' => 'x'])->assertForbidden();
        $this->assertNull($order->fresh()->result, '被擋下時不可以寫入保養結果');
    }

    public function test_the_four_permissions_are_independent_of_each_other(): void
    {
        [$order] = $this->fixtures();

        // 只勾「回報結果」：能開回報頁，但看不到工單列表。
        $role = Role::create(['name' => '只回報', 'slug' => 'report_only', 'is_system' => false, 'permissions' => ['maintenance.report']]);
        $this->actingAs(User::factory()->create(['role_id' => $role->id]));
        $this->get(route('maintenance-orders.results.create', $order))->assertOk();
        $this->get(route('maintenance-orders.index'))->assertForbidden();

        // 只勾「AI 預防保養」：能開 AI 頁，但不能看保養項目。
        $role = Role::create(['name' => '只 AI', 'slug' => 'ai_only', 'is_system' => false, 'permissions' => ['ai-maintenance.manage']]);
        $this->actingAs(User::factory()->create(['role_id' => $role->id]));
        $this->get(route('preventive-candidates.index'))->assertOk();
        $this->get(route('maintenance-items.index'))->assertForbidden();
    }

    public function test_navigation_links_follow_the_permissions(): void
    {
        $links = [
            'maintenance-items.index' => ['admin' => true, 'it_manager' => true, 'technician' => true, 'executive' => true, 'teacher' => false],
            'device-profile.index' => ['admin' => true, 'it_manager' => true, 'technician' => true, 'executive' => true, 'teacher' => false],
            'preventive-candidates.index' => ['admin' => true, 'it_manager' => true, 'technician' => false, 'executive' => false, 'teacher' => false],
        ];

        foreach (['admin', 'it_manager', 'technician', 'executive', 'teacher'] as $slug) {
            $this->actingAs($this->makeUser($slug));
            $page = $this->get(route('dashboard'))->assertOk();

            foreach ($links as $routeName => $expected) {
                $href = 'href="'.route($routeName).'"';
                $expected[$slug]
                    ? $page->assertSee($href, false)
                    : $page->assertDontSee($href, false);
            }
        }
    }

    public function test_action_buttons_are_hidden_when_the_user_cannot_use_them(): void
    {
        [$order, , $plan] = $this->fixtures();
        $createOrderUrl = route('maintenance-plans.create-order', $plan);
        $reportUrl = route('maintenance-orders.results.create', $order);

        // 維修人員：看得到「回報結果」，看不到計畫的「建立工單」與「新增保養項目」。
        $this->actingAs($this->makeUser('technician'));
        $this->get(route('maintenance-orders.index'))->assertSee($reportUrl, false);
        $this->get(route('maintenance-orders.show', $order))->assertSee($reportUrl, false);
        $this->get(route('maintenance-plans.index'))->assertDontSee($createOrderUrl, false);
        $this->get(route('maintenance-items.index'))->assertDontSee(route('maintenance-items.create'), false);

        // 主管：只能看，連「回報結果」都沒有。
        $this->actingAs($this->makeUser('executive'));
        $this->get(route('maintenance-orders.index'))->assertDontSee($reportUrl, false);
        $this->get(route('maintenance-orders.show', $order))->assertDontSee($reportUrl, false);

        // 設備管理員：全部看得到。
        $this->actingAs($this->makeUser('it_manager'));
        $this->get(route('maintenance-plans.index'))->assertSee($createOrderUrl, false);
        $this->get(route('maintenance-items.index'))->assertSee(route('maintenance-items.create'), false);
        $this->get(route('maintenance-orders.index'))->assertSee($reportUrl, false);
    }

    public function test_every_maintenance_permission_is_in_the_catalog_and_the_defaults_are_as_decided(): void
    {
        foreach (['maintenance.view', 'maintenance.manage', 'maintenance.report', 'ai-maintenance.manage'] as $code) {
            $this->assertTrue(PermissionCatalog::exists($code), $code);
        }

        $this->assertEqualsCanonicalizing(
            ['maintenance.view', 'maintenance.manage', 'maintenance.report', 'ai-maintenance.manage'],
            array_values(array_intersect(PermissionCatalog::defaultsFor('it_manager'), PermissionCatalog::GROUPS['maintenance']))
        );
        $this->assertEqualsCanonicalizing(['maintenance.view', 'maintenance.report'], array_values(array_intersect(PermissionCatalog::defaultsFor('technician'), PermissionCatalog::GROUPS['maintenance'])));
        $this->assertSame(['maintenance.view'], array_values(array_intersect(PermissionCatalog::defaultsFor('executive'), PermissionCatalog::GROUPS['maintenance'])));
        $this->assertSame([], array_values(array_intersect(PermissionCatalog::defaultsFor('teacher'), PermissionCatalog::GROUPS['maintenance'])));
    }

    public function test_backfill_migration_adds_the_new_permissions_without_touching_the_others_and_is_repeatable(): void
    {
        // 模擬「舊資料庫」：內建身分只有升級前的權限（自訂過一個額外權限）。
        $old = ['repairs.create', 'repairs.process', 'repairs.assignable'];
        Role::create(['name' => '維修人員', 'slug' => 'technician', 'is_system' => true, 'permissions' => $old]);
        Role::create(['name' => '資訊組主管', 'slug' => 'it_manager', 'is_system' => true, 'permissions' => ['devices.manage']]);
        Role::create(['name' => '主管', 'slug' => 'executive', 'is_system' => true, 'permissions' => null]);
        Role::create(['name' => '教師', 'slug' => 'teacher', 'is_system' => true, 'permissions' => ['repairs.create']]);
        Role::create(['name' => '自訂', 'slug' => 'custom_x', 'is_system' => false, 'permissions' => ['repairs.create']]);

        $migration = require base_path('database/migrations/2026_10_08_110000_add_maintenance_permissions_to_system_roles.php');
        $migration->up();
        $migration->up();   // 重跑一次，不能出現重複項目

        $get = fn (string $slug) => Role::where('slug', $slug)->first()->permissions;

        $this->assertEqualsCanonicalizing(array_merge($old, ['maintenance.view', 'maintenance.report']), $get('technician'));
        $this->assertEqualsCanonicalizing(['devices.manage', 'maintenance.view', 'maintenance.manage', 'maintenance.report', 'ai-maintenance.manage'], $get('it_manager'));
        $this->assertSame(['maintenance.view'], $get('executive'));
        $this->assertSame(['repairs.create'], $get('teacher'), '教師預設沒有保養權限');
        $this->assertSame(['repairs.create'], $get('custom_x'), '自訂身分不會被動到');

        $this->assertSame(
            count(array_unique($get('technician'))),
            count($get('technician')),
            '不可以有重複的權限'
        );

        $migration->down();
        $this->assertEqualsCanonicalizing($old, $get('technician'));
        $this->assertSame([], $get('executive'));
    }
}
