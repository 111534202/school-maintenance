<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\Device;
use App\Models\DeviceCategory;
use App\Models\KnowledgeBase;
use App\Models\MaintenanceItem;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Models\Major;
use App\Models\Part;
use App\Models\RepairRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 列表頁的查詢次數不能隨著資料變多而變多（N+1 問題：每一列都多查一次資料庫，資料一多頁面就變慢）。
 * 做法：先放少量資料量一次查詢次數，再補到很多筆，同一頁的查詢次數不能增加（容許差 2 次，例如分頁的計數查詢）。
 * 新增列表頁時，把它的資料產生方式加進 pages()，就會自動被檢查。
 */
class ListQueryCountTest extends TestCase
{
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    private function queriesFor(string $route): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route($route))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /** 每個頁面：路由名稱 => 「再多產生幾筆資料」的做法。 */
    private function pages(): array
    {
        $category = DeviceCategory::create(['name' => '投影機']);
        $classroom = Classroom::create(['campus' => '本校區', 'building' => 'A棟', 'floor' => '1F', 'classroom_code' => 'A101', 'name' => 'A101 教室', 'type' => '一般教室', 'is_active' => true, 'reservation_status' => 'normal']);
        $device = fn (int $i) => Device::create(['device_code' => 'DEV-' . $i . '-' . uniqid(), 'device_category_id' => $category->id, 'classroom_id' => $classroom->id, 'status' => 'normal', 'is_core' => false]);
        $device(0);

        return [
            'devices.index' => fn (int $i) => $device($i),
            'users.index' => fn (int $i) => User::factory()->create(),
            'audit-logs.index' => fn (int $i) => AuditLog::create(['user_id' => User::inRandomOrder()->value('id'), 'action' => 'created', 'description' => "紀錄 {$i}"]),
            'repairs.index' => fn (int $i) => RepairRequest::factory()->create(['classroom_id' => $classroom->id, 'requester_id' => User::inRandomOrder()->value('id')]),
            'parts.index' => fn (int $i) => Part::create(['name' => "零件 {$i}", 'unit' => '個', 'safety_stock' => 1]),
            'classrooms.index' => fn (int $i) => Classroom::create(['campus' => '本校區', 'building' => 'B棟', 'floor' => '1F', 'classroom_code' => 'B' . $i . uniqid(), 'name' => "B{$i}", 'type' => '一般教室', 'is_active' => true, 'reservation_status' => 'normal']),
            'maintenance-orders.index' => fn (int $i) => MaintenanceOrder::factory()->create(),
            'maintenance-items.index' => fn (int $i) => MaintenanceItem::factory()->create(),
            'maintenance-plans.index' => fn (int $i) => MaintenancePlan::factory()->create(),
            'knowledge-base.index' => fn (int $i) => KnowledgeBase::factory()->create(),
            'departments.index' => fn (int $i) => Department::create(['name' => '部門 ' . $i . uniqid(), 'department_code' => 'D' . $i . uniqid(), 'is_active' => true]),
            'majors.index' => fn (int $i) => Major::create(['name' => '科系 ' . $i, 'major_code' => 'M' . $i . uniqid(), 'abbreviation' => '科', 'grade_count' => 3, 'class_count' => 2, 'is_active' => true]),
        ];
    }

    public function test_list_pages_do_not_query_once_per_row(): void
    {
        $this->loginAsAnyUser();

        foreach ($this->pages() as $route => $make) {
            for ($i = 1; $i <= 2; $i++) {
                $make($i);
            }
            $few = $this->queriesFor($route);

            for ($i = 3; $i <= 14; $i++) {
                $make($i);
            }
            $many = $this->queriesFor($route);

            $this->assertLessThanOrEqual($few + 2, $many, "{$route}：資料從少量增加到 14 筆，查詢次數從 {$few} 次增加到 {$many} 次，疑似每一列都多查一次資料庫（N+1）。");
        }
    }
}
