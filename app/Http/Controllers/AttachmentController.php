<?php

// 命名空間：這個類別所在的位置，要跟資料夾路徑對得上。
namespace App\Http\Controllers;

use App\Models\Attachment;                   // 附件資料表模型
use App\Models\RepairLog;                    // 維修紀錄資料表模型
use App\Models\RepairRequest;                // 報修單資料表模型
use Illuminate\Support\Facades\Gate;         // 權限判斷：Gate::authorize 不通過就直接回 403
use Illuminate\Support\Facades\Storage;      // Laravel 的檔案儲存功能

/**
 * 附件下載（照片、影片、PDF）：檔案存在私有磁碟，不能直接用網址開，一律從這裡輸出。
 *
 * 規則：能開這個附件的人，就是「能檢視它所屬那張報修單」的人（規則見 App\Policies\RepairRequestPolicy：
 * 能派工的人與管理員看全部，其他人只看自己報修的或指派給自己的）。維修紀錄上的照片也一樣，
 * 看它所屬的那張報修單。沒登入會被導到登入頁，沒權限回 403。
 * 這符合規格書「資料需具備權限隔離，不得跨權限檢視」的要求。
 *
 * 網址：GET /attachments/{attachment}（見 routes/web.php，已套用登入與帳號啟用檢查）。
 */
class AttachmentController extends Controller
{
    /** 輸出附件檔案內容（圖片與 PDF 會直接在瀏覽器顯示）。 */
    public function show(Attachment $attachment)
    {
        // 找出這個附件最終屬於哪一張報修單：直接掛在報修單上的就是它；掛在維修紀錄上的，看維修紀錄所屬的報修單。
        $owner = $attachment->attachable;
        $repairRequest = $owner instanceof RepairLog ? $owner->repairRequest : $owner;

        // 找不到所屬的報修單（資料異常）就當作不存在，不要冒險輸出。
        abort_unless($repairRequest instanceof RepairRequest, 404);

        // 沒有權限看這張報修單 → 403。
        Gate::authorize('view', $repairRequest);

        // 檔案在哪個磁碟；兩個磁碟都找不到（檔案被移走或遺失）→ 404。
        $disk = $attachment->storageDisk();
        abort_if($disk === null, 404);

        // response()：把檔案內容輸出給瀏覽器，並帶上原本的檔名與類型。
        // nosniff：禁止瀏覽器自己猜檔案類型，避免把上傳的檔案當成網頁執行。
        return Storage::disk($disk)->response($attachment->disk_path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
