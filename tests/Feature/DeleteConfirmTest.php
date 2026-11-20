<?php

namespace Tests\Feature;

use App\Models\Part;
use App\Models\PartPurchase;
use App\Models\PartScrap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 刪除一律先跳出二次確認視窗：表單帶 data-confirm，版面上有共用的 #confirmActionDialog。
 */
class DeleteConfirmTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRolesAndUsers;

    public function test_every_delete_form_in_the_views_asks_for_confirmation(): void
    {
        $missing = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'))) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $html = file_get_contents($file->getPathname());
            // 每一個含 @method('DELETE') 的表單開頭標籤都要有 data-confirm。
            preg_match_all('/<form\b[^>]*>(?:(?!<\/form>).)*?@method\([\'"]DELETE[\'"]\)/s', $html, $matches);
            foreach ($matches[0] as $form) {
                $tag = substr($form, 0, strpos($form, '>') + 1);
                if (! str_contains($tag, 'data-confirm=')) {
                    $missing[] = str_replace(resource_path('views') . '/', '', $file->getPathname()) . ': ' . trim($tag);
                }
            }
        }
        $this->assertSame([], $missing, "這些刪除表單沒有二次確認：\n" . implode("\n", $missing));
    }

    public function test_part_page_delete_buttons_use_the_confirm_dialog(): void
    {
        $this->actingAs($this->makeUserWithPermissions(['parts.manage']));
        $part = Part::create(['part_code' => 'HDMI-2', 'name' => 'HDMI 線', 'specification' => '2公尺', 'unit' => '條', 'safety_stock' => 1]);
        // 沒有庫存、沒有任何紀錄的零件才有「刪除零件」（有庫存或紀錄時按鈕是灰的），刪除一樣先跳出確認視窗。

        $this->get(route('parts.show', $part))
            ->assertOk()
            ->assertSee('id="confirmActionDialog"', false)
            ->assertSee('請問確定要刪除零件「HDMI-2 HDMI 線」嗎？')
            ->assertDontSee('return confirm(', false);
    }
}
