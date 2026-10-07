<?php

use App\Models\Attachment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

// 把早期版本存在公開磁碟（storage/app/public）的附件檔案，搬到私有磁碟（storage/app/private）。
// 原因：附件要跟報修單一樣受權限控管，公開磁碟的檔案任何人拿到網址都能直接開，不用登入。
// 這支只搬「檔案」，資料表不變（disk_path 存的是不含磁碟名稱的相對路徑，搬了不用改）。
// 檔案不在 Git 裡，所以每個人的電腦各自執行 php artisan migrate 時，搬的是自己電腦上有的檔案；
// 沒有舊檔案（例如全新資料庫）就什麼都不做。可以重複執行，已經搬過的會略過。
// （migration 的基本觀念見 0001_01_01_000000_create_users_table.php 檔頭。）
return new class extends Migration
{
    // 套用變更：公開磁碟 → 私有磁碟。
    public function up(): void
    {
        $this->moveAll('public', 'local');
    }

    // 還原變更：私有磁碟 → 公開磁碟（回到舊的存放方式）。
    public function down(): void
    {
        $this->moveAll('local', 'public');
    }

    private function moveAll(string $from, string $to): void
    {
        foreach (Attachment::query()->cursor() as $attachment) {
            $path = $attachment->disk_path;

            // 來源有檔案、目的地還沒有，才搬；搬成功（寫入目的地）之後才刪掉來源，避免中途失敗遺失檔案。
            if (Storage::disk($from)->exists($path) && ! Storage::disk($to)->exists($path)) {
                $contents = Storage::disk($from)->get($path);

                if (Storage::disk($to)->put($path, $contents)) {
                    Storage::disk($from)->delete($path);
                }
            }
        }
    }
};
