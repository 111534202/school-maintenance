<?php

namespace Tests\Feature;

use App\Models\RepairLog;
use App\Models\RepairRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 附件的存取控管：檔案存在私有磁碟，只有「能檢視所屬報修單」的人能開
 * （規則見 App\Policies\RepairRequestPolicy；規格書要求資料需具備權限隔離）。
 */
class AttachmentAccessTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    protected function setUp(): void
    {
        parent::setUp();

        // 兩個磁碟都換成假的（只在記憶體／暫存裡），不會動到真正的檔案。
        Storage::fake('local');
        Storage::fake('public');
    }

    /** 輔助方法：替一張報修單放一個附件檔案（存在私有磁碟），回傳附件。 */
    private function attach(RepairRequest|RepairLog $owner, string $disk = 'local')
    {
        Storage::disk($disk)->put('attachments/photo.jpg', 'FAKE-JPEG-BYTES');

        return $owner->attachments()->create([
            'disk_path' => 'attachments/photo.jpg', 'original_name' => '故障照片.jpg',
            'mime_type' => 'image/jpeg', 'size_bytes' => 15,
        ]);
    }

    // 沒登入的人開附件網址，會被導到登入頁。
    public function test_guests_are_sent_to_login(): void
    {
        $attachment = $this->attach(RepairRequest::factory()->create());

        $this->get(route('attachments.show', $attachment))->assertRedirect(route('login'));
    }

    // 報修人本人、被指派的維修人員、能派工的主管、系統管理員都能開。
    public function test_people_who_may_see_the_case_can_open_its_attachment(): void
    {
        $reporter = $this->makeUserWithRole('teacher', '教師');
        $technician = $this->makeTechnician();
        $case = RepairRequest::factory()->create(['reporter_id' => $reporter->id, 'assigned_to' => $technician->id]);
        $attachment = $this->attach($case);

        foreach ([$reporter, $technician, $this->makeUserWithRole('executive', '主管'), $this->makeUserWithRole('admin', '系統管理員')] as $user) {
            $response = $this->actingAs($user)->get(route('attachments.show', $attachment));
            $response->assertOk();
            $this->assertSame('FAKE-JPEG-BYTES', $response->streamedContent());
        }
    }

    // 跟這張單無關的教師、維修人員打不開（403）。這是原本會漏的地方：以前檔案是公開網址，不用登入就能開。
    public function test_people_unrelated_to_the_case_cannot_open_its_attachment(): void
    {
        $reporter = $this->makeUserWithRole('teacher', '教師');
        $case = RepairRequest::factory()->create(['reporter_id' => $reporter->id]);
        $attachment = $this->attach($case);

        $this->actingAs($this->makeUserWithRole('teacher', '教師'))->get(route('attachments.show', $attachment))->assertForbidden();
        $this->actingAs($this->makeTechnician())->get(route('attachments.show', $attachment))->assertForbidden();
    }

    // 維修紀錄上的照片，跟著它所屬的那張報修單走同樣的規則。
    public function test_repair_log_attachments_follow_the_parent_case(): void
    {
        $technician = $this->makeTechnician();
        $case = RepairRequest::factory()->create(['assigned_to' => $technician->id]);
        $log = RepairLog::factory()->create(['repair_request_id' => $case->id]);
        $attachment = $this->attach($log);

        $this->actingAs($technician)->get(route('attachments.show', $attachment))->assertOk();
        $this->actingAs($this->makeTechnician())->get(route('attachments.show', $attachment))->assertForbidden();
    }

    // 輸出時帶上正確的檔案類型，並禁止瀏覽器自己猜類型（避免把上傳的檔案當網頁執行）。
    public function test_response_headers_are_safe(): void
    {
        $this->loginAsAnyUser();
        $attachment = $this->attach(RepairRequest::factory()->create());

        $response = $this->get(route('attachments.show', $attachment));

        $response->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('image/jpeg', $response->headers->get('Content-Type'));
    }

    // 檔案遺失（資料庫有紀錄、硬碟沒有檔案）→ 404，不會報錯。
    public function test_missing_file_returns_404(): void
    {
        $this->loginAsAnyUser();
        $attachment = RepairRequest::factory()->create()->attachments()->create([
            'disk_path' => 'attachments/gone.jpg', 'original_name' => 'gone.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 1,
        ]);

        $this->get(route('attachments.show', $attachment))->assertNotFound();
    }

    // 搬移前留在公開磁碟的舊檔案，仍然可以（有權限的人）透過這個路由開啟。
    public function test_legacy_files_still_on_the_public_disk_can_be_opened(): void
    {
        $this->loginAsAnyUser();
        $attachment = $this->attach(RepairRequest::factory()->create(), 'public');

        $this->get(route('attachments.show', $attachment))->assertOk();
    }

    // 新上傳的附件存在私有磁碟，公開磁碟裡不會有任何檔案（所以不可能用網址直接開）。
    public function test_new_uploads_go_to_the_private_disk_only(): void
    {
        $this->loginAsAnyUser();

        $this->post(route('repairs.store'), [
            'title' => '附件測試', 'description' => '描述', 'impact_level' => 'low', 'affects_class' => '0',
            'attachments' => [UploadedFile::fake()->image('broken.jpg')],
        ])->assertRedirect();

        $path = RepairRequest::firstWhere('title', '附件測試')->attachments->first()->disk_path;
        Storage::disk('local')->assertExists($path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    // migration 會把舊的公開檔案搬到私有磁碟，而且重複執行不會出錯，也能還原。
    public function test_migration_moves_legacy_files_to_the_private_disk(): void
    {
        $attachment = $this->attach(RepairRequest::factory()->create(), 'public');
        $migration = require base_path('database/migrations/2026_10_04_000001_move_attachments_to_private_disk.php');

        $migration->up();
        Storage::disk('local')->assertExists($attachment->disk_path);
        Storage::disk('public')->assertMissing($attachment->disk_path);

        $migration->up();   // 再執行一次：已經搬過，什麼都不會發生
        Storage::disk('local')->assertExists($attachment->disk_path);

        $migration->down();
        Storage::disk('public')->assertExists($attachment->disk_path);
        Storage::disk('local')->assertMissing($attachment->disk_path);
    }
}
