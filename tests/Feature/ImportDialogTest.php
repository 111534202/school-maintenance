<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRolesAndUsers;
use Tests\TestCase;

/**
 * 批量匯入改成在設備／用戶主檔的對話框裡進行：右下角「批量匯入」用對話框打開，頁首沒有「回列表」，
 * 出錯時留在對話框裡（網址帶著 modal=1），不會整頁跳走。
 */
class ImportDialogTest extends TestCase
{
    use InteractsWithRolesAndUsers;
    use RefreshDatabase;

    public function test_the_import_button_opens_a_dialog(): void
    {
        $this->actingAs($this->makeUserWithRole('admin', '系統管理員'));

        $this->get(route('devices.index'))->assertOk()
            ->assertSee('href="' . route('imports.index', 'devices') . '" data-modal-frame data-modal-title="批量匯入設備"', false);
        $this->get(route('users.index'))->assertOk()
            ->assertSee('href="' . route('imports.index', 'users') . '" data-modal-frame data-modal-title="批量匯入用戶"', false);
    }

    public function test_the_import_page_has_no_back_to_list_button(): void
    {
        $this->actingAs($this->makeUserWithRole('admin', '系統管理員'));

        $this->get(route('imports.index', ['type' => 'devices', 'modal' => 1]))->assertOk()
            ->assertDontSee('回列表')
            ->assertSee('data-modal-stay', false);   // 下載範本留在對話框裡
    }

    public function test_errors_inside_the_dialog_stay_inside_the_dialog(): void
    {
        $this->actingAs($this->makeUserWithRole('admin', '系統管理員'));

        $this->post(route('imports.confirm', 'devices'), ['token' => 'expired', 'modal' => 1])
            ->assertRedirect(route('imports.index', ['type' => 'devices', 'modal' => 1]))
            ->assertSessionHas('error');
    }
}
