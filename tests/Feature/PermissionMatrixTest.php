<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ClassroomReservation;
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
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 權限矩陣＋煙霧測試：每一個身分（含「什麼權限都沒有」的身分）逐一打開系統裡每一個需要登入的頁面。
 *
 * 檢查兩件事：
 * 1. 路由上標了 can:權限 的頁面，有權限的人不能被擋（403）、沒有權限的人一定要被擋（403）。
 * 2. 任何身分打開任何頁面都不能出現伺服器錯誤（500）——這同時是全站的煙霧測試。
 *
 * 新增頁面時不用改這個檔案：它自己讀路由清單；只有「網址帶參數」的頁面需要在 fixtures() 補一筆示範資料。
 * 想把完整矩陣輸出成 JSON 檢視：PERMISSION_MATRIX_DUMP=檔案路徑 php artisan test --filter=PermissionMatrixTest
 */
class PermissionMatrixTest extends TestCase
{
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    /** 不是一般頁面的路由：檔案下載（需要實體檔案）、語言切換、舊網址轉址、公開檔案。 */
    private const SKIP_URIS = ['storage/{path}', 'locale/{locale}', 'repair-requests/{path?}', 'attachments/{attachment}', 'backups/{name}/download', 'up'];

    /** @return array<string, User> */
    private function users(): array
    {
        $nobody = Role::create(['name' => '什麼權限都沒有', 'role_code' => 'nobody', 'is_system' => false, 'permissions' => []]);

        return [
            'admin' => $this->makeUserWithRole('admin', '系統管理員'),
            'it_manager' => $this->makeUserWithRole('it_manager', '設備管理員'),
            'technician' => $this->makeUserWithRole('technician', '維修人員'),
            'teacher' => $this->makeUserWithRole('teacher', '教師'),
            'executive' => $this->makeUserWithRole('executive', '主管'),
            'nobody' => User::factory()->create(['role_id' => $nobody->id]),
        ];
    }

    /** 網址參數名稱 => 示範資料（讓帶參數的頁面也能打開）。 */
    private function fixtures(User $owner): array
    {
        $category = DeviceCategory::create(['name' => '投影機']);
        $classroom = Classroom::create(['campus' => '本校區', 'building' => 'A棟', 'floor' => '1F', 'classroom_code' => 'A101', 'name' => 'A101 教室', 'type' => '一般教室', 'is_active' => true]);
        $device = Device::create(['device_code' => 'DEV-001', 'device_category_id' => $category->id, 'classroom_id' => $classroom->id, 'status' => 'normal']);
        $part = Part::create(['part_code' => 'P-001', 'name' => 'HDMI 線', 'unit' => '條', 'safety_stock' => 1]);
        $purchase = $part->purchases()->create(['purchase_date' => now()->toDateString(), 'vendor_name' => '甲廠商', 'quantity' => 5, 'unit_price' => 100, 'remaining_quantity' => 5]);
        $reservation = ClassroomReservation::create([
            'classroom_id' => $classroom->id, 'requester_id' => $owner->id, 'class_name' => '一年甲班', 'reservation_date' => now()->addDay()->toDateString(),
            'start_time' => '08:10', 'end_time' => '09:00', 'purpose' => '班級活動', 'status' => 'pending',
        ]);

        return [
            'classroom' => $classroom,
            'department' => Department::create(['name' => '資訊組', 'department_code' => 'IT', 'is_active' => true]),
            'device_category' => $category,
            'device' => $device,
            'major' => Major::create(['name' => '資訊科', 'major_code' => 'CS', 'abbreviation' => '資', 'grade_count' => 5, 'class_count' => 4, 'is_active' => true]),
            'part' => $part,
            'purchase' => $purchase,
            'scrap' => $part->scraps()->create(['scrap_date' => now()->toDateString(), 'quantity' => 1, 'reason' => '損壞', 'recorded_by' => $owner->id]),
            'repair_request' => RepairRequest::factory()->create(['requester_id' => $owner->id, 'classroom_id' => $classroom->id]),
            'maintenanceItem' => MaintenanceItem::factory()->create(),
            'maintenancePlan' => MaintenancePlan::factory()->create(),
            'maintenanceOrder' => MaintenanceOrder::factory()->create(),
            'knowledge_base' => KnowledgeBase::factory()->create(),
            'reservation' => $reservation,
            'role' => Role::create(['name' => '示範身分', 'role_code' => 'demo_role', 'is_system' => false, 'permissions' => []]),
            'user' => User::factory()->create(),
            'type' => 'devices',
            'format' => 'excel',
        ];
    }

    /** @return list<string> 這個路由要求的權限（can:xxx 中介層） */
    private function requiredPermissions(Route $route): array
    {
        $needed = [];
        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'can:')) {
                $needed[] = substr($middleware, 4);
            }
        }

        return $needed;
    }

    public function test_every_role_can_or_cannot_open_every_page_exactly_as_its_permissions_say(): void
    {
        $users = $this->users();
        $fixtures = $this->fixtures($users['teacher']);

        $routes = [];
        foreach (RouteFacade::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true) || in_array($route->uri(), self::SKIP_URIS, true)) {
                continue;
            }
            if (! in_array('auth', $route->gatherMiddleware(), true)) {
                continue;   // 不需要登入的頁面（登入頁、免登入課表…）不在這份矩陣裡
            }
            $routes[] = $route;
        }
        $this->assertGreaterThan(40, count($routes), '路由清單讀不到，矩陣測試形同沒有在測。');

        $matrix = [];
        $problems = [];
        foreach ($routes as $route) {
            $parameters = [];
            foreach ($route->parameterNames() as $name) {
                if (! array_key_exists($name, $fixtures)) {
                    $problems[] = "{$route->uri()}：缺少網址參數「{$name}」的示範資料（請到 fixtures() 補上）";

                    continue 2;
                }
                $parameters[$name] = $fixtures[$name];
            }
            $url = route($route->getName(), $parameters);
            $needed = $this->requiredPermissions($route);

            foreach ($users as $roleName => $user) {
                $status = $this->actingAs($user)->get($url)->getStatusCode();
                $matrix[$route->uri()][$roleName] = $status;

                if ($status >= 500) {
                    $problems[] = "{$route->uri()}（{$roleName}）：伺服器錯誤 {$status}";
                }
                if ($needed) {
                    $allowed = collect($needed)->every(fn ($permission) => $user->can($permission));
                    // 報修單頁面除了身分權限，還要看「這張單是不是你的／指派給你的／現在是什麼狀態」，所以有權限仍可能被擋；這類不檢查「有權限卻被擋」。
                    $hasRecordRule = str_contains($route->uri(), '{repair_request}');
                    if ($allowed && $status === 403 && ! $hasRecordRule) {
                        $problems[] = "{$route->uri()}（{$roleName}）：有權限 " . implode('、', $needed) . ' 卻被擋下（403）';
                    }
                    if (! $allowed && $status !== 403) {
                        $problems[] = "{$route->uri()}（{$roleName}）：沒有權限 " . implode('、', $needed) . " 卻能進入（狀態 {$status}）";
                    }
                }
            }
        }

        if ($dump = env('PERMISSION_MATRIX_DUMP')) {
            file_put_contents($dump, json_encode($matrix, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }

        $this->assertSame([], $problems, implode("\n", $problems));
    }

    public function test_a_role_with_no_permissions_cannot_open_any_master_or_management_page(): void
    {
        $users = $this->users();
        $this->actingAs($users['nobody']);

        foreach (['users.index', 'roles.index', 'departments.index', 'classrooms.index', 'devices.index', 'device-categories.index', 'majors.index', 'parts.index', 'audit-logs.index', 'backups.index', 'mail-settings.edit', 'maintenance-orders.index', 'maintenance-plans.index', 'ai-maintenance.candidates.index'] as $name) {
            if (! RouteFacade::has($name)) {
                continue;
            }
            $this->get(route($name))->assertForbidden();
        }
    }
}
