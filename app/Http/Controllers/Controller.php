<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

/**
 * 所有 Controller 的共同父類別（abstract = 只能被繼承，不能單獨使用）。
 * 目前沒有需要全部 Controller 共用的程式，所以是空的；
 * 如果之後有「每支 Controller 都想用的方法」，寫在這裡，其他 Controller 就都能直接呼叫。
 */
abstract class Controller
{
    /**
     * 在列表頁直接按的動作（停用、刪除、還原、核准…）做完後回到「原本那個列表」，連篩選條件一起帶回去，
     * 不要掉回沒有篩選的完整列表（例如在「只看異常設備」的列表按停用，回來還是只看異常設備）。
     * 上一頁不是這個列表（例如從對話框、詳細頁送出）就回列表的預設網址。
     *
     * @param  array<string, mixed>  $parameters
     */
    protected function backToList(string $routeName, array $parameters = []): RedirectResponse
    {
        $listUrl = route($routeName, $parameters);
        $previous = url()->previous();

        $samePage = parse_url($previous, PHP_URL_HOST) === parse_url($listUrl, PHP_URL_HOST)
            && parse_url($previous, PHP_URL_PATH) === parse_url($listUrl, PHP_URL_PATH);

        if (! $samePage) {
            return redirect()->to($listUrl);
        }

        // 去掉頁碼回第一頁：刪除或停用「最後一頁的最後一筆」後，原本的頁碼可能已經不存在（會看到空白頁）。
        $query = [];
        parse_str((string) parse_url($previous, PHP_URL_QUERY), $query);
        unset($query['page']);

        return redirect()->to($query ? $listUrl . '?' . http_build_query($query) : $listUrl);
    }
}
