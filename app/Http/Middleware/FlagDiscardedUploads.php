<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 表單有上傳附件、但送出後驗證失敗（例如時間不對、漏填）退回表單時：
 * 瀏覽器基於安全規定不能把剛選的檔案自動填回去，所以記一個旗標 uploads_discarded，
 * 表單頁的附件欄位下面會顯示「剛剛選的附件沒有保留，請重新選擇」（見 layouts/partials/required-fields）。
 */
class FlagDiscardedUploads
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof RedirectResponse && $request->hasSession() && $this->hasUploadedFiles($request)
            && in_array('errors', (array) $request->session()->get('_flash.new', []), true)) {
            $request->session()->flash('uploads_discarded', true);
        }

        return $response;
    }

    private function hasUploadedFiles(Request $request): bool
    {
        $found = false;
        $files = $request->allFiles();
        array_walk_recursive($files, function ($file) use (&$found) {
            $found = $found || ($file !== null);
        });

        return $found;
    }
}
