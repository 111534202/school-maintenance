<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * 附件上傳第一版（依《第 2 週個人工作計畫》第 1 項）。
 * 統一存到 storage/app/public/attachments，報修單與維修紀錄共用這支服務，
 * 避免兩個 Controller 各寫一份上傳邏輯。
 */
class AttachmentUploader
{
    /**
     * 把上傳的檔案存進硬碟，並在 attachments 表建立對應的紀錄。
     *
     * $attachable 可以是 RepairRequest（報修單的附件）或 RepairLog（維修前後
     * 照片），只要那個 Model 有定義 attachments() 這個多型關聯（morphMany）
     * 都可以用這支方法上傳，不用兩個 Controller 各寫一份重複的程式碼。
     *
     * @param  UploadedFile[]  $files  使用者上傳的檔案陣列（來自 $request->file('attachments')）
     */
    public function storeMany(Model $attachable, array $files): void
    {
        foreach ($files as $file) {
            // ->store() 會把檔案存到 storage/app/public/attachments，
            // 並自動產生一個不會重複的隨機檔名，回傳存檔後的相對路徑。
            $path = $file->store('attachments', 'public');

            $attachable->attachments()->create([
                'disk_path' => $path,
                'original_name' => $file->getClientOriginalName(), // 使用者電腦上原本的檔名
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
            ]);
        }
    }
}
