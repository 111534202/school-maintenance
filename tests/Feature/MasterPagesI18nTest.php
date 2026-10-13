<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Device;
use App\Models\DeviceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 多語系（規格書 3.1.2：UI Label 需讀取多語系配置檔）：
 * 教室、設備、設備類別三組主檔頁面的文字都來自翻譯檔，英文環境下不會露出中文，
 * 而且中英文翻譯檔的鍵完全一致（少了哪一邊的翻譯，這裡會失敗提醒）。
 */
class MasterPagesI18nTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    /** 把巢狀的翻譯陣列展開成「a.b.c」形式的鍵清單，方便比對兩種語言。 */
    private function keysOf(string $locale, string $file): array
    {
        $keys = array_keys(Arr::dot(require base_path("lang/$locale/$file.php")));
        sort($keys);

        return $keys;
    }

    public function test_chinese_and_english_files_have_exactly_the_same_keys(): void
    {
        foreach (['classrooms', 'devices', 'device_categories'] as $file) {
            $this->assertSame($this->keysOf('zh_TW', $file), $this->keysOf('en', $file), "lang/$file.php 中英文的鍵不一致");
        }
    }

    // 英文環境下，三組頁面都顯示英文文字，沒有露出翻譯鍵名（例如 classrooms.table.code），也沒有殘留中文標籤。
    public function test_pages_render_in_english_without_chinese_labels_or_raw_keys(): void
    {
        $this->loginAsAnyUser();
        $room = Classroom::create(['campus' => 'Main', 'building' => 'A', 'floor' => '1', 'room_code' => 'A1', 'room_name' => 'Room A1']);
        $category = DeviceCategory::create(['name' => 'Projector']);
        $device = Device::create(['device_code' => 'D-1', 'device_category_id' => $category->id, 'classroom_id' => $room->id, 'status' => 'repairing']);

        $pages = [
            route('classrooms.index') => 'Classrooms', route('classrooms.create') => 'Add Classroom', route('classrooms.edit', $room) => 'Edit Classroom',
            route('devices.index') => 'Devices', route('devices.create') => 'Add Device', route('devices.edit', $device) => 'Edit Device',
            route('devices.show', $device) => 'Device Details', route('devices.entry', $device) => 'Need help?',
            route('device-categories.index') => 'Device Categories', route('device-categories.create') => 'Add Device Category',
            route('device-categories.edit', $category) => 'Edit Device Category',
        ];

        foreach ($pages as $url => $expected) {
            $html = $this->withSession(['locale' => 'en'])->get($url)->assertOk()->getContent();
            $this->assertStringContainsString($expected, $html, "英文頁面沒有顯示「{$expected}」：$url");
            $this->assertDoesNotMatchRegularExpression('/\b(classrooms|devices|device_categories)\.[a-z_]+\.[a-z_]+\b/', strip_tags($html), "露出翻譯鍵名：$url");
        }

        // 設備狀態也跟著語言變：英文顯示 Under repair，不會是中文的「維修中」。
        $html = $this->withSession(['locale' => 'en'])->get(route('devices.index'))->getContent();
        $this->assertStringContainsString('Under repair', $html);
        $this->assertStringNotContainsString('維修中', $html);
    }

    // 操作成功的訊息也走翻譯檔：英文環境下顯示英文。
    public function test_flash_messages_follow_the_language(): void
    {
        $this->loginAsAnyUser();

        $this->withSession(['locale' => 'en'])
            ->post(route('device-categories.store'), ['name' => 'Switch'])
            ->assertSessionHas('success', 'Device category added.');
        $this->withSession(['locale' => 'zh_TW'])
            ->post(route('device-categories.store'), ['name' => '交換器'])
            ->assertSessionHas('success', '設備類別已新增。');
    }
}
