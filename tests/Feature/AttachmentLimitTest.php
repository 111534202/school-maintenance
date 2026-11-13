<?php

namespace Tests\Feature;

use App\Models\RepairRequest;
use App\Services\DatabaseBackup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 附件上限：一筆最多 5 個檔案、加起來 5 MB 以內，單一檔案不另外限制大小；
 * 資料備份會把附件檔案一起備份、還原。
 */
class AttachmentLimitTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    private function payload(array $files): array
    {
        return [
            'title' => 'A101 投影機燈泡燒壞', 'device_note' => 'A101 投影機', 'description' => '畫面全暗。',
            'impact_level' => 'high', 'is_class_affected' => '1', 'attachments' => $files,
        ];
    }

    public function test_one_large_file_is_fine_as_long_as_the_total_is_within_five_megabytes(): void
    {
        Storage::fake('local');
        $this->loginAsAnyUser();

        $this->post(route('repairs.store'), $this->payload([UploadedFile::fake()->create('clip.mp4', 4800, 'video/mp4')]))
            ->assertSessionHasNoErrors();
        $this->assertCount(1, RepairRequest::firstOrFail()->attachments);
    }

    public function test_files_adding_up_to_more_than_five_megabytes_are_rejected(): void
    {
        Storage::fake('local');
        $this->loginAsAnyUser();

        $this->post(route('repairs.store'), $this->payload([
            UploadedFile::fake()->create('a.pdf', 2000, 'application/pdf'),
            UploadedFile::fake()->create('b.pdf', 2000, 'application/pdf'),
            UploadedFile::fake()->create('c.pdf', 1500, 'application/pdf'),
        ]))->assertSessionHasErrors('attachments');
        $this->assertSame(0, RepairRequest::count());
    }

    public function test_more_than_five_files_are_rejected(): void
    {
        Storage::fake('local');
        $this->loginAsAnyUser();

        $this->post(route('repairs.store'), $this->payload(array_map(fn ($i) => UploadedFile::fake()->image("p{$i}.jpg"), range(1, 6))))
            ->assertSessionHasErrors('attachments');
    }

    public function test_backups_include_attachment_files_and_restore_them(): void
    {
        $this->app->useStoragePath(sys_get_temp_dir() . '/attach-backup-' . uniqid());
        $backup = app(DatabaseBackup::class);
        $dir = $backup->attachmentsDirectory();
        File::ensureDirectoryExists($dir);
        file_put_contents("{$dir}/invoice.pdf", 'original');

        $name = $backup->create('manual');
        $this->assertStringEndsWith('.zip', $name);
        $this->assertTrue($backup->all()->first()['with_attachments']);

        // 有人刪了附件、又多上傳一個：還原後回到備份當時的樣子。
        unlink("{$dir}/invoice.pdf");
        file_put_contents("{$dir}/later.jpg", 'later');
        $backup->restore($name);

        $this->assertSame('original', file_get_contents("{$dir}/invoice.pdf"));
        $this->assertFileDoesNotExist("{$dir}/later.jpg");
        File::deleteDirectory(dirname($dir, 3));
    }
}
