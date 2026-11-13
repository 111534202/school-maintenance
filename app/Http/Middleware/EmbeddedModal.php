<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 「在畫面中」開啟的表單（網址帶 ?modal=1，例如零件履歷裡的「修改採購」）：
 * 頁面用精簡版面放在目前頁面的對話框（iframe）裡；送出後如果成功、原本要跳到別頁（網址不再帶 modal=1），
 * 就改成回傳一小段通知，請外面的頁面自己跳過去（成功訊息仍然會顯示），對話框跟著關掉。
 * 驗證失敗會回到原本的表單網址（還帶著 modal=1），所以錯誤照樣顯示在對話框裡。
 * 前端的部分在 layouts/app.blade.php 底部（data-modal-frame 連結、#frameModal）。
 */
class EmbeddedModal
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->boolean('modal') || ! $response instanceof RedirectResponse) {
            return $response;
        }

        $target = $response->getTargetUrl();
        parse_str((string) parse_url($target, PHP_URL_QUERY), $query);
        if (! empty($query['modal'])) {
            return $response;   // 回到對話框裡的表單（例如驗證失敗）
        }

        return response()->view('layouts.modal-done', ['url' => $target]);
    }
}
