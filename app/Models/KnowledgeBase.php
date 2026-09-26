<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 自助排除知識庫的一篇文章。使用者設備出問題時，可以先來這裡查有沒有現成的
 * 排除步驟；照著做還是解決不了，才會跳到報修流程（見
 * KnowledgeBaseController::resolved() / RepairRequestController::create()
 * 裡的 from_kb 參數）。
 */
class KnowledgeBase extends Model
{
    use HasFactory;

    // 表名固定寫成 knowledge_base（單數），因為資料庫表名就是這樣建的，
    // 不是 Laravel 預設會猜的 knowledge_bases（複數）。
    protected $table = 'knowledge_base';

    protected $fillable = [
        'title',         // 標題
        'category',      // 分類（例如：投影機、電腦、網路）
        'symptom',       // 常見故障現象
        'solution',      // 自助排除步驟
        'is_published',  // 是否要顯示給使用者看（true=上架，false=先隱藏）
        'created_by',    // 建立者（users 表尚未合併，目前只是存數字 id，沒有真正關聯）
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];
}
