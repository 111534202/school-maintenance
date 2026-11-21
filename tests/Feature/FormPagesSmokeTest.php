<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Major;
use App\Models\Part;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 新增／編輯表單頁的煙霧測試：系統管理員打開每一個表單頁都要正常顯示（不能 500），並且帶出該筆資料。
 * 另外確認語言切換關閉時直接打網址不會切成英文。
 */
class FormPagesSmokeTest extends TestCase
{
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    public function test_master_data_form_pages_render_for_an_admin(): void
    {
        $this->loginAsAnyUser();
        $department = Department::create(['name' => '資訊組', 'department_code' => 'IT', 'is_active' => true]);
        $major = Major::create(['name' => '資訊科', 'major_code' => 'CS', 'abbreviation' => '資', 'grade_count' => 5, 'class_count' => 4, 'is_active' => true]);
        $part = Part::create(['part_code' => 'HDMI-2', 'name' => 'HDMI 線', 'specification' => '2公尺', 'unit' => '條', 'safety_stock' => 1]);

        $this->get(route('departments.create'))->assertOk();
        $this->get(route('departments.edit', $department))->assertOk()->assertSee('資訊組');
        $this->get(route('majors.create'))->assertOk();
        $this->get(route('majors.edit', $major))->assertOk()->assertSee('資訊科');
        $this->get(route('parts.create'))->assertOk();
        $this->get(route('parts.edit', $part))->assertOk()->assertSee('HDMI 線');
    }

    public function test_purchase_show_page_renders(): void
    {
        $this->loginAsAnyUser();
        $part = Part::create(['part_code' => 'HDMI-3', 'name' => 'HDMI 線', 'unit' => '條', 'safety_stock' => 1]);
        $purchase = $part->purchases()->create(['purchase_date' => now()->toDateString(), 'vendor_name' => '甲廠商', 'quantity' => 5, 'unit_price' => 100, 'remaining_quantity' => 5]);

        $this->get(route('parts.purchases.show', [$part, $purchase]))->assertOk()->assertSee('甲廠商');
    }

    public function test_reservation_export_and_locale_switch(): void
    {
        $this->loginAsAnyUser();

        $this->get(route('reservations.export'))->assertOk();

        // 語言切換預設關閉：打網址不會真的切成英文。
        $this->get(route('locale.switch', 'en'))->assertRedirect();
        $this->assertNotSame('en', session('locale'));
    }
}
