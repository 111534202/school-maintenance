<?php

namespace Tests\Feature;

use App\Exports\TableExport;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\Device;
use App\Models\DeviceCategory;
use App\Models\ImportBatch;
use App\Models\RepairRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 批量匯入（設備、用戶）：範本下載、預覽逐列檢查、確認寫入、略過錯誤列、權限、密碼不外洩。
 */
class BulkImportTest extends TestCase
{
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');   // 預覽暫存檔寫到假的磁碟，不弄髒真正的 storage
    }

    private function csv(array $rows): UploadedFile
    {
        $content = "\xEF\xBB\xBF" . implode("\n", array_map(fn ($r) => implode(',', $r), $rows));

        return UploadedFile::fake()->createWithContent('import.csv', $content);
    }

    private function seedMasters(): void
    {
        DeviceCategory::create(['name' => '投影機']);
        Classroom::create(['campus' => '本校區', 'building' => 'A棟', 'floor' => '1F', 'classroom_code' => 'A101', 'name' => 'A101 教室', 'type' => '一般教室', 'is_active' => true]);
        Classroom::create(['campus' => '本校區', 'building' => 'A棟', 'floor' => '2F', 'classroom_code' => 'A201', 'name' => 'A201 教室', 'type' => '一般教室', 'is_active' => false]);
    }

    private const DEVICE_HEAD = ['設備編號*', '資產編號', '類別*', '品牌', '型號', '序號', '保固到期日', '教室*', '狀態'];

    private function preview(string $type, UploadedFile $file)
    {
        return $this->post(route('imports.preview', $type), ['file' => $file]);
    }

    // ---------- 設備 ----------

    public function test_device_preview_marks_good_and_bad_rows_without_writing_anything(): void
    {
        $this->loginAsAnyUser();
        $this->seedMasters();
        Device::create(['device_code' => 'DEV-EXIST', 'device_category_id' => DeviceCategory::first()->id, 'classroom_id' => Classroom::first()->id, 'status' => 'normal']);

        $response = $this->preview('devices', $this->csv([
            self::DEVICE_HEAD,
            ['DEV-001', '', '投影機', 'Epson', 'EB-X', 'SN1', '2027/12/31', 'A101', '正常'],
            ['DEV-002', '', '電視', '', '', '', '', 'A101', ''],            // 類別不存在
            ['DEV-003', '', '投影機', '', '', '', '', 'A201', ''],          // 教室已停用
            ['DEV-EXIST', '', '投影機', '', '', '', '', 'A101', ''],        // 編號已存在
            ['DEV-001', '', '投影機', '', '', '', '', 'A101', ''],          // 檔案內重複
            ['DEV-006', '', '投影機', '', '', '', '', 'A101', '維修中'],    // 狀態看不懂（待維修由報修單判斷，不能匯入）
            ['DEV-007', '', '投影機', '', '', '', '2027-13-45', 'A101', ''], // 日期格式錯
        ]))->assertOk();

        $response->assertSee('可匯入 1 列')->assertSee('有問題 6 列')
            ->assertSee('找不到類別「電視」')->assertSee('已停用，不能放新設備')->assertSee('的設備編號，請換一個')->assertSee('在檔案裡重複')->assertSee('看不懂')->assertSee('格式不對');
        $this->assertSame(1, Device::count());   // 預覽不寫入（只有原本那一台）
    }

    public function test_device_confirm_imports_only_good_rows_and_logs(): void
    {
        $this->loginAsAnyUser();
        $this->seedMasters();

        $file = $this->csv([
            self::DEVICE_HEAD,
            ['DEV-001', 'A-1', '投影機', 'Epson', 'EB-X', 'SN1', '2027/12/31', 'A101', '正常'],
            ['DEV-002', '', '投影機', '', '', '', '', 'A101', '已淘汰'],
            ['DEV-BAD', '', '不存在的類別', '', '', '', '', 'A101', ''],
        ]);
        $token = $this->tokenFrom($this->preview('devices', $file));

        $this->post(route('imports.confirm', 'devices'), ['token' => $token])
            ->assertRedirect(route('devices.index'))->assertSessionHas('success');

        $this->assertSame(2, Device::count());
        $device = Device::firstWhere('device_code', 'DEV-001');
        $this->assertSame('2027-12-31', $device->warranty_end_date->toDateString());
        $this->assertSame('retired', Device::firstWhere('device_code', 'DEV-002')->status);
        $this->assertDatabaseMissing('devices', ['device_code' => 'DEV-BAD']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'created', 'loggable_type' => Device::class, 'loggable_id' => $device->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'created', 'loggable_type' => null]);   // 整批的摘要紀錄
        // 確認後暫存檔刪除，不能重複確認。
        $this->assertSame([], Storage::disk('local')->files('imports'));
        $this->post(route('imports.confirm', 'devices'), ['token' => $token])->assertRedirect(route('imports.index', 'devices'))->assertSessionHas('error');
        $this->assertSame(2, Device::count());
    }

    public function test_excel_file_works_too_and_excel_dates_are_understood(): void
    {
        $this->loginAsAnyUser();
        $this->seedMasters();

        Excel::store(new TableExport('匯入資料', self::DEVICE_HEAD, [['DEV-XLSX', '', '投影機', '', '', '', '2027-06-30', 'A101', '']]), 'tmp-import.xlsx', 'local');
        $file = new UploadedFile(Storage::disk('local')->path('tmp-import.xlsx'), 'devices.xlsx', null, null, true);
        $token = $this->tokenFrom($this->preview('devices', $file)->assertSee('可匯入 1 列'));

        $this->post(route('imports.confirm', 'devices'), ['token' => $token])->assertSessionHas('success');
        $this->assertSame('2027-06-30', Device::firstWhere('device_code', 'DEV-XLSX')->warranty_end_date->toDateString());
        Storage::disk('local')->delete('tmp-import.xlsx');
    }

    public function test_missing_required_header_or_empty_file_is_rejected(): void
    {
        $this->loginAsAnyUser();
        $this->seedMasters();

        $this->preview('devices', $this->csv([['設備編號*', '品牌'], ['X', 'Y']]))->assertRedirect()->assertSessionHas('error');
        $this->assertStringContainsString('找不到必要的欄位標題', session('error'));

        $this->preview('devices', $this->csv([self::DEVICE_HEAD]))->assertSessionHas('error');
        $this->post(route('imports.preview', 'devices'), [])->assertSessionHasErrors('file');
        $this->post(route('imports.preview', 'devices'), ['file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertSessionHasErrors('file');
    }

    public function test_template_download_has_headers_example_and_reference_sheets(): void
    {
        $this->loginAsAnyUser();
        $this->seedMasters();
        Excel::fake();
        Excel::matchByRegex();

        $this->get(route('imports.template', 'devices'))->assertOk();
        Excel::assertDownloaded('/設備匯入範本\.xlsx/');
        Excel::fake();
        $this->get(route('imports.template', 'users'))->assertOk();
    }

    // ---------- 用戶 ----------

    private const USER_HEAD = ['姓名*', '帳號*', 'Email*', '電話', '身分*', '部門', '初始密碼', '啟用'];

    public function test_user_import_creates_accounts_that_must_change_password_and_never_logs_passwords(): void
    {
        $this->loginAsAnyUser();
        $teacher = $this->makeRole('teacher2', '教師二');
        Department::create(['name' => '資訊組', 'department_code' => 'IT', 'is_active' => true]);

        $token = $this->tokenFrom($this->preview('users', $this->csv([
            self::USER_HEAD,
            ['王小明', 'wang.xm', 'wang@example.edu', '0912', '教師二', '資訊組', 'Init-Pass-123', '是'],
            ['李小華', 'li.xh', 'li@example.edu', '', 'teacher2', '', '', ''],
        ]))->assertSee('可匯入 2 列')->assertDontSee('Init-Pass-123'));

        $this->post(route('imports.confirm', 'users'), ['token' => $token])->assertRedirect(route('users.index'));

        $wang = User::firstWhere('account', 'wang.xm');
        $li = User::firstWhere('account', 'li.xh');
        $this->assertTrue($wang->is_password_change_required && $li->is_password_change_required);
        $this->assertTrue(Hash::check('Init-Pass-123', $wang->password));
        $this->assertFalse(Hash::check('', $li->password));           // 沒填密碼：隨機密碼，沒有人知道
        $this->assertSame($teacher->id, $li->role_id);
        $this->assertTrue($li->is_active);
        $this->assertNotNull($wang->department_id);
        $this->assertStringNotContainsString('Init-Pass-123', AuditLog::query()->get()->toJson(JSON_UNESCAPED_UNICODE));

        // 用初始密碼登入後，一定要先設定新密碼。
        auth()->logout();
        $this->post(route('login'), ['login' => 'wang.xm', 'password' => 'Init-Pass-123'])->assertRedirect();
        $this->get(route('dashboard'))->assertRedirect(route('password.change'));
    }

    public function test_user_rows_with_problems_are_reported(): void
    {
        $this->loginAsAnyUser();
        $this->makeRole('teacher2', '教師二');
        User::factory()->create(['account' => 'taken', 'email' => 'taken@example.edu']);

        $this->preview('users', $this->csv([
            self::USER_HEAD,
            ['甲', 'ok.user', 'ok@example.edu', '', '教師二', '', '', ''],
            ['乙', 'ab', 'b@example.edu', '', '教師二', '', '', ''],            // 帳號太短
            ['丙', 'taken', 'c@example.edu', '', '教師二', '', '', ''],         // 帳號已有人
            ['丁', 'dup.mail', 'taken@example.edu', '', '教師二', '', '', ''],  // Email 已有人
            ['戊', 'e.user', 'not-an-email', '', '教師二', '', '', ''],
            ['己', 'f.user', 'f@example.edu', '', '不存在的身分', '', '', ''],
            ['庚', 'g.user', 'g@example.edu', '', '教師二', '不存在的部門', '', ''],
            ['辛', 'h.user', 'h@example.edu', '', '教師二', '', '123', ''],     // 密碼太短
            ['壬', 'ok.user', 'i@example.edu', '', '教師二', '', '', ''],       // 檔案內帳號重複
        ]))->assertSee('可匯入 1 列')->assertSee('有問題 8 列')
            ->assertSee('帳號要 3～30 字')->assertSee('已經有人使用')->assertSee('格式不對')->assertSee('找不到身分')->assertSee('找不到啟用中的部門')->assertSee('至少 8 碼')->assertSee('在檔案裡重複');
    }

    // ---------- 權限與安全 ----------

    public function test_import_requires_the_matching_master_permission(): void
    {
        $this->actingAs($this->makeItManager());   // 有設備主檔權限、沒有用戶主檔權限
        $this->get(route('imports.index', 'devices'))->assertOk();
        $this->get(route('imports.index', 'users'))->assertForbidden();
        $this->get(route('imports.template', 'users'))->assertForbidden();
        $this->post(route('imports.preview', 'users'), ['file' => $this->csv([self::USER_HEAD])])->assertForbidden();

        $this->actingAs($this->makeUserWithRole('teacher', '教師'));
        $this->get(route('imports.index', 'devices'))->assertForbidden();
        $this->get('/import/unknown')->assertNotFound();
    }

    public function test_another_user_cannot_confirm_someone_elses_preview(): void
    {
        $this->loginAsAnyUser();
        $this->seedMasters();
        $token = $this->tokenFrom($this->preview('devices', $this->csv([self::DEVICE_HEAD, ['DEV-001', '', '投影機', '', '', '', '', 'A101', '', '']])));

        $this->actingAs($this->makeItManager());
        $this->post(route('imports.confirm', 'devices'), ['token' => $token])->assertRedirect(route('imports.index', 'devices'))->assertSessionHas('error');
        $this->assertSame(0, Device::count());
        Cache::flush();
    }

    public function test_import_buttons_appear_on_the_master_lists(): void
    {
        $this->loginAsAnyUser();

        $this->get(route('devices.index'))->assertSee(route('imports.index', 'devices'), false);
        $this->get(route('users.index'))->assertSee(route('imports.index', 'users'), false);
    }

    // ---------- 整批復原 ----------

    public function test_undoing_a_device_import_disables_unused_devices_but_keeps_ones_with_history(): void
    {
        $this->loginAsAnyUser();
        $this->seedMasters();
        $token = $this->tokenFrom($this->preview('devices', $this->csv([
            self::DEVICE_HEAD,
            ['DEV-001', '', '投影機', '', '', '', '', 'A101', '正常', ''],
            ['DEV-002', '', '投影機', '', '', '', '', 'A101', '正常', ''],
        ])));
        $this->post(route('imports.confirm', 'devices'), ['token' => $token]);
        $batch = ImportBatch::first();
        $this->assertSame(2, $batch->imported_count);
        $this->assertSame($batch->id, Device::firstWhere('device_code', 'DEV-001')->import_batch_id);

        // DEV-002 已經有報修紀錄：復原時要保留。
        RepairRequest::factory()->create(['device_id' => Device::firstWhere('device_code', 'DEV-002')->id]);

        $this->post(route('imports.undo', ['devices', $batch]))->assertRedirect(route('imports.index', 'devices'))->assertSessionHas('success');

        $this->assertNull(Device::firstWhere('device_code', 'DEV-001'));                     // 已停用（軟刪除）
        $this->assertNotNull(Device::withTrashed()->firstWhere('device_code', 'DEV-001'));   // 資料還在
        $this->assertNotNull(Device::firstWhere('device_code', 'DEV-002'));                  // 有紀錄，保留
        $this->assertTrue($batch->fresh()->isUndone());
        $this->assertDatabaseHas('audit_logs', ['action' => 'deleted', 'loggable_type' => Device::class]);

        // 同一批不能復原第二次。
        $this->post(route('imports.undo', ['devices', $batch]))->assertSessionHas('error');
    }

    public function test_undoing_a_user_import_deletes_unused_accounts_but_keeps_ones_who_logged_in(): void
    {
        $this->loginAsAnyUser();
        $this->makeRole('teacher2', '教師二');
        $token = $this->tokenFrom($this->preview('users', $this->csv([
            self::USER_HEAD,
            ['王小明', 'wang.xm', 'wang@example.edu', '', '教師二', '', 'Init-Pass-123', ''],
            ['李小華', 'li.xh', 'li@example.edu', '', '教師二', '', 'Init-Pass-123', ''],
        ])));
        $this->post(route('imports.confirm', 'users'), ['token' => $token]);
        $batch = ImportBatch::first();
        User::firstWhere('account', 'li.xh')->forceFill(['last_login_at' => now()])->save();   // 李小華已經登入過

        $this->post(route('imports.undo', ['users', $batch]))->assertSessionHas('success');

        $this->assertNull(User::firstWhere('account', 'wang.xm'));
        $this->assertNotNull(User::withTrashed()->firstWhere('account', 'wang.xm'));   // 軟刪除，可還原
        $this->assertNotNull(User::firstWhere('account', 'li.xh'));
    }

    public function test_undo_checks_permission_and_batch_type(): void
    {
        $this->loginAsAnyUser();
        $batch = ImportBatch::create(['import_type' => 'users', 'name' => 'x.csv', 'imported_count' => 0, 'skipped_count' => 0]);

        $this->post(route('imports.undo', ['devices', $batch]))->assertNotFound();   // 種類對不上

        auth()->logout();
        $teacher = $this->makeUserWithRole('teacher', '教師');
        $this->actingAs($teacher)->post(route('imports.undo', ['users', $batch]))->assertForbidden();
        $this->assertFalse($batch->fresh()->isUndone());
    }

    public function test_import_page_lists_recent_batches_with_an_undo_button(): void
    {
        $this->loginAsAnyUser();
        ImportBatch::create(['import_type' => 'devices', 'name' => 'phase1.csv', 'imported_count' => 3, 'skipped_count' => 1]);

        $this->get(route('imports.index', 'devices'))->assertSee('phase1.csv')->assertSee('成功 3、略過 1')->assertSee('整批復原');
    }

    private function tokenFrom($response): string
    {
        preg_match('/name="token" value="([a-f0-9-]{36})"/', $response->getContent(), $m);
        $this->assertNotEmpty($m, '預覽畫面沒有帶出 token');

        return $m[1];
    }
}
