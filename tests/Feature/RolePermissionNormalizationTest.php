<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 第一正規化：身分勾選的權限不再存成 roles.permissions 的 JSON 陣列，改存 role_permissions（一列一個權限）。
 * 這裡確認三件事：
 *   1. 資料表結構：JSON 欄位不見了、role_permissions 有外鍵與不可重複的約束。
 *   2. 程式的用法沒變：$role->permissions 仍然讀寫「權限代碼的陣列」，Role::withPermission() 照常查詢。
 *   3. 轉換 migration：舊的 JSON 資料會搬進新資料表，退回時能組回 JSON。
 */
class RolePermissionNormalizationTest extends TestCase
{
    use DatabaseMigrations;

    private function role(string $slug, array $permissions = []): Role
    {
        return Role::create(['name' => $slug, 'role_code' => $slug, 'is_system' => false, 'permissions' => $permissions]);
    }

    public function test_the_json_column_is_gone_and_the_pivot_table_has_its_constraints(): void
    {
        $this->assertFalse(Schema::hasColumn('roles', 'permissions'));
        $this->assertTrue(Schema::hasTable('role_permissions'));

        $foreignKeys = collect(Schema::getForeignKeys('role_permissions'))->keyBy(fn ($fk) => $fk['columns'][0]);
        $this->assertSame('roles', $foreignKeys['role_id']['foreign_table']);

        $role = $this->role('custom', ['devices.manage']);
        $this->expectException(QueryException::class);   // 同一個身分的同一個權限不能存兩次
        RolePermission::create(['role_id' => $role->id, 'permission' => 'devices.manage']);
    }

    public function test_permissions_are_still_read_and_written_as_an_array(): void
    {
        $role = $this->role('custom', ['devices.manage', 'repairs.create', 'devices.manage']);   // 重複的會被去掉

        $this->assertEqualsCanonicalizing(['devices.manage', 'repairs.create'], $role->fresh()->permissions);
        $this->assertSame(2, DB::table('role_permissions')->where('role_id', $role->id)->count());

        // 更新：多的刪掉、缺的補上；就算身分本身的欄位沒變，只改權限也會存進去。
        $role->update(['permissions' => ['repairs.create', 'classrooms.manage']]);
        $this->assertEqualsCanonicalizing(['repairs.create', 'classrooms.manage'], $role->fresh()->permissions);

        $role->permissions = [];
        $role->save();
        $this->assertSame([], $role->fresh()->permissions);
        $this->assertSame(0, DB::table('role_permissions')->where('role_id', $role->id)->count());
    }

    public function test_permission_checks_and_the_scope_use_the_pivot_rows(): void
    {
        $technician = $this->role('technician', ['repairs.process', 'repairs.assignable']);
        $teacher = $this->role('teacher', ['repairs.accept']);
        $admin = $this->role('admin');   // 管理員的全開是隱含的，不用存任何一列

        $this->assertTrue($technician->hasPermission('repairs.process'));
        $this->assertFalse($technician->hasPermission('repairs.accept'));
        $this->assertTrue($admin->hasPermission('anything.at.all'));

        // withPermission 只列出「明確勾選」的身分，不含管理員。
        $this->assertSame(['technician'], Role::withPermission('repairs.assignable')->pluck('role_code')->all());
        $this->assertSame(['teacher'], Role::withPermission('repairs.accept')->pluck('role_code')->all());
    }

    public function test_deleting_a_role_removes_its_permission_rows(): void
    {
        $role = $this->role('custom', ['devices.manage', 'repairs.create']);

        $role->delete();

        $this->assertSame(0, DB::table('role_permissions')->count());
    }

    public function test_migration_moves_legacy_json_into_rows_and_back(): void
    {
        // 退回「這支 migration 和它之後的所有 migration」：roles.permissions 欄位回來。
        $rollbackSteps = fn () => (function () {
            $files = collect(glob(database_path('migrations/*.php')))->map(fn ($path) => basename($path))->sort()->values();

            return $files->count() - $files->search('2026_10_22_000001_normalize_role_permissions_into_own_table.php');
        })();
        Artisan::call('migrate:rollback', ['--step' => $rollbackSteps(), '--force' => true]);
        $this->assertTrue(Schema::hasColumn('roles', 'permissions'));
        $this->assertFalse(Schema::hasTable('role_permissions'));

        $now = now();
        $insert = fn (string $slug, ?string $json) => DB::table('roles')->insertGetId(['name' => $slug, 'slug' => $slug, 'is_system' => 0, 'permissions' => $json, 'created_at' => $now, 'updated_at' => $now]);
        $a = $insert('a', json_encode(['devices.manage', 'repairs.create', 'devices.manage']));   // 含重複
        $b = $insert('b', null);                                                                  // 沒有任何權限
        $c = $insert('c', json_encode([]));

        Artisan::call('migrate', ['--force' => true]);

        $this->assertFalse(Schema::hasColumn('roles', 'permissions'));
        $this->assertEqualsCanonicalizing(['devices.manage', 'repairs.create'], DB::table('role_permissions')->where('role_id', $a)->pluck('permission')->all());
        $this->assertSame(0, DB::table('role_permissions')->whereIn('role_id', [$b, $c])->count());

        // 再退回一次：權限組回 JSON。
        Artisan::call('migrate:rollback', ['--step' => $rollbackSteps(), '--force' => true]);
        $this->assertEqualsCanonicalizing(['devices.manage', 'repairs.create'], json_decode(DB::table('roles')->where('id', $a)->value('permissions'), true));
        $this->assertSame([], json_decode(DB::table('roles')->where('id', $c)->value('permissions'), true));
        Artisan::call('migrate', ['--force' => true]);   // 回到最新，讓測試結束時的清理正常
    }
}
