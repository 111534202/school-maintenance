<?php

namespace Tests\Feature;

use App\Models\RepairRequest;
use App\Support\AttachmentLimit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 報修照片：接受常見的手機照片格式、檔案欄位用「檔案類型」讓手機直接開相簿、報修單頁直接顯示縮圖。
 */
class RepairPhotoDisplayTest extends TestCase
{
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_webp_and_jpeg_photos_are_accepted_and_shown_as_thumbnails(): void
    {
        $teacher = $this->makeUserWithRole('teacher', '教師');
        $this->actingAs($teacher);

        $this->post(route('repairs.store'), [
            'title' => '照片測試', 'description' => '有照片', 'impact_level' => 'low', 'is_class_affected' => 0, 'device_note' => 'A101 投影機',
            'attachments' => [UploadedFile::fake()->image('front.jpg', 600, 400), UploadedFile::fake()->image('side.webp', 600, 400)],
        ])->assertRedirect();

        $repair = RepairRequest::firstOrFail();
        $this->assertSame(2, $repair->attachments()->count());

        $page = $this->get(route('repairs.show', $repair))->assertOk();
        foreach ($repair->attachments as $attachment) {
            $page->assertSee('<img src="' . $attachment->url() . '"', false);
            $this->get($attachment->url())->assertOk();   // 圖片本身讀得到
        }
    }

    public function test_the_file_inputs_use_mime_types_so_phones_open_the_photo_library(): void
    {
        $this->actingAs($this->makeUserWithRole('teacher', '教師'));

        $this->get(route('repairs.create'))->assertOk()->assertSee('accept="' . AttachmentLimit::ACCEPT . '"', false)->assertSee('data-compress-images', false);
    }

    public function test_other_file_types_are_still_refused(): void
    {
        $this->actingAs($this->makeUserWithRole('teacher', '教師'));

        $this->post(route('repairs.store'), [
            'title' => '壞檔案', 'description' => '不是照片', 'impact_level' => 'low', 'is_class_affected' => 0, 'device_note' => 'A101',
            'attachments' => [UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')],
        ])->assertSessionHasErrors('attachments.0');
        $this->assertSame(0, RepairRequest::count());
    }
}
