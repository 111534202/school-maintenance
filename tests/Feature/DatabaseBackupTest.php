<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Services\DatabaseBackup;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 資料備份：每週日自動備份、立即備份、下載、還原（還原前先自動備份）、保留天數；只有 backups.manage 能用。
 */
class DatabaseBackupTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        // 測試用自己的資料夾，不碰到真正的備份。
        $this->app->useStoragePath(sys_get_temp_dir() . '/backup-test-' . uniqid());
        $this->dir = app(DatabaseBackup::class)->directory();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(dirname($this->dir, 2));
        parent::tearDown();
    }

    private function admin()
    {
        return $this->makeUserWithRole('admin', '系統管理員');
    }

    public function test_only_people_with_the_backup_permission_can_open_the_page(): void
    {
        $this->actingAs($this->makeUserWithPermissions(['parts.manage']));
        $this->get(route('backups.index'))->assertForbidden();
        $this->post(route('backups.store'))->assertForbidden();

        $this->actingAs($this->admin());
        $this->get(route('backups.index'))->assertOk()->assertSee('資料備份')->assertSee('立即備份')->assertSee('每週日凌晨 2 點');
    }

    public function test_back_up_now_creates_a_file_that_can_be_downloaded(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('backups.store'))->assertRedirect(route('backups.index'));

        $backups = app(DatabaseBackup::class)->all();
        $this->assertCount(1, $backups);
        $this->assertSame('manual', $backups[0]['reason']);
        // 還原表單要先確認（第二批會換成全站的確認視窗）。
        $this->get(route('backups.index'))->assertSee('手動備份')->assertSee('把所有資料還原到')->assertSee('return confirm(this.dataset.msg)', false);
        $this->get(route('backups.download', $backups[0]['name']))->assertOk();
        $this->get(route('backups.download', '..%2F..%2F.env'))->assertNotFound();
    }

    public function test_restore_brings_data_back_and_keeps_a_backup_of_the_current_data(): void
    {
        $this->actingAs($this->admin());
        Department::create(['name' => '資訊組', 'department_code' => 'IT']);
        $name = app(DatabaseBackup::class)->create('manual');

        // 有人亂改：改名、新增、刪除
        Department::where('department_code', 'IT')->update(['name' => '被改掉的名字']);
        Department::create(['name' => '多出來的部門', 'department_code' => 'XX']);

        $this->post(route('backups.restore', $name))->assertRedirect(route('backups.index'))->assertSessionHas('success');

        $this->assertSame('資訊組', Department::where('department_code', 'IT')->value('name'));
        $this->assertNull(Department::where('department_code', 'XX')->first());
        $this->assertTrue(app(DatabaseBackup::class)->all()->contains('reason', 'before-restore'));
    }

    public function test_old_backups_are_removed_after_the_keep_days_but_the_newest_is_kept(): void
    {
        $backup = app(DatabaseBackup::class);
        foreach (['20260801-020000', '20260901-020000', '20261101-020000'] as $stamp) {
            file_put_contents("{$this->dir}/backup-{$stamp}-scheduled.json.gz", gzencode('{"tables":{}}'));
        }
        $this->travelTo('2026-11-09 03:00:00');

        $this->assertSame(2, $backup->prune(30));
        $this->assertSame(['backup-20261101-020000-scheduled.json.gz'], $backup->all()->pluck('name')->all());

        // 全部都過期時，最新的一份仍然保留；0 天＝永遠不刪。
        $this->travelTo('2027-06-01 03:00:00');
        $this->assertSame(0, $backup->prune(30));
        $this->assertSame(0, $backup->prune(0));
        $this->assertCount(1, $backup->all());
    }

    public function test_keep_days_can_be_changed_on_the_page(): void
    {
        $this->actingAs($this->admin());
        $this->assertSame(30, app(DatabaseBackup::class)->keepDays());

        $this->post(route('backups.settings'), ['keep_days' => 180])->assertRedirect(route('backups.index'));
        $this->assertSame(180, app(DatabaseBackup::class)->keepDays());
        $this->post(route('backups.settings'), ['keep_days' => -1])->assertSessionHasErrors('keep_days');
    }

    public function test_backups_are_scheduled_every_sunday_at_two_am(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command ?? '', 'backup:run'));
        $this->assertNotNull($event);
        $this->assertSame('0 2 * * 0', $event->expression);
    }
}
