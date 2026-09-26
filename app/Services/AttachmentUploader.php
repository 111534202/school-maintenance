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
    /** @param  UploadedFile[]  $files */
    public function storeMany(Model $attachable, array $files): void
    {
        foreach ($files as $file) {
            $path = $file->store('attachments', 'public');

            $attachable->attachments()->create([
                'disk_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
            ]);
        }
    }
}
