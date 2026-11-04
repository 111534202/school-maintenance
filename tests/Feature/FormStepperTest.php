<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesMaintenanceFixtures;
use Tests\TestCase;

/**
 * 分步驟表單（layouts/partials/form-stepper.blade.php）：
 * 長表單被切成幾個 data-step 區塊，而且所有原本的欄位都還在、送出按鈕也還在（只是分段顯示，欄位與驗證不變）。
 */
class FormStepperTest extends TestCase
{
    use CreatesMaintenanceFixtures;
    use RefreshDatabase;

    public static function forms(): array
    {
        // 欄位：路由名稱、預期的步驟數、這張表單原本就有的欄位 name
        return [
            '新增設備' => ['devices.create', 3, ['device_code', 'asset_code', 'device_category_id', 'classroom_id', 'brand', 'model', 'serial_number', 'warranty_end_date', 'status', 'is_core']],
            '新增教室' => ['classrooms.create', 3, ['department_id', 'manager_id', 'campus', 'building', 'floor', 'classroom_code', 'name', 'type']],
            '新增用戶' => ['users.create', 3, ['account', 'name', 'email', 'password']],
            '新增報修單' => ['repairs.create', 3, ['title', 'description', 'impact_level', 'location', 'attachments[]']],
            '新增身分' => ['roles.create', 2, ['name', 'permissions[]']],
            '新增保養計畫' => ['maintenance-plans.create', 3, ['name', 'device_category_id', 'cycle_days', 'start_date', 'is_active']],
        ];
    }

    #[DataProvider('forms')]
    public function test_long_forms_are_split_into_steps_without_losing_any_field(string $route, int $steps, array $fields): void
    {
        $html = $this->actingAs($this->makeUser('admin'))->get(route($route))->assertOk()->getContent();

        $this->assertSame($steps, substr_count($html, 'data-step="'), "$route 的步驟數");
        foreach ($fields as $field) {
            $this->assertStringContainsString('name="'.$field.'"', $html, "$route 少了欄位 $field");
        }
        // 送出按鈕與分步驟的程式都在
        $this->assertStringContainsString('type="submit"', $html);
        $this->assertStringContainsString('stepper-list', $html);
    }

    public function test_short_forms_stay_single_step(): void
    {
        // 欄位很少的表單不分步驟（分了反而多點好幾下）。
        $html = $this->actingAs($this->makeUser('admin'))->get(route('device-categories.create'))->assertOk()->getContent();

        $this->assertSame(0, substr_count($html, 'data-step="'));
    }

    public function test_edit_user_form_has_two_steps_because_password_is_only_for_new_users(): void
    {
        $admin = $this->makeUser('admin');
        $html = $this->actingAs($admin)->get(route('users.edit', $admin))->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, 'data-step="'));
    }
}
