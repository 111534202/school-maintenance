<?php

namespace App\Http\Controllers;

use App\Models\Part;
use App\Models\PartScrap;
use App\Services\AuditLogger;
use App\Services\PartStockService;
use DomainException;
use Illuminate\Http\Request;

/**
 * 零件報廢：零件還沒用就壞掉、過期或遺失時登記報廢（零件履歷的「報廢紀錄」）。
 * 報廢會從庫存扣掉，扣哪一批跟用料一樣先進先出；登記後就定案（唯讀），不能修改或取消。加回原本的批次。
 * 庫存的增減都交給 PartStockService。
 */
class PartScrapController extends Controller
{
    public function __construct(private PartStockService $stock)
    {
    }

    public function create(Part $part)
    {
        return view('parts.scraps.create', ['part' => $part, 'reasons' => PartScrap::COMMON_REASONS]);
    }

    /** 報廢紀錄詳細（唯讀，在零件履歷的對話框開啟）：登記後就定案，只能查看。 */
    public function show(Part $part, PartScrap $scrap)
    {
        abort_unless($scrap->part_id === $part->id, 404);   // 網址上的零件和報廢紀錄要對得上
        $scrap->load(['recorder', 'batches.purchase']);

        return view('parts.scraps.show', compact('part', 'scrap'));
    }

    public function store(Request $request, Part $part)
    {
        $validated = $request->validate([
            'scrap_date' => ['required', 'date', 'before_or_equal:today'],
            'scrap_time' => ['nullable', 'date_format:H:i'],
            'quantity' => ['required', 'integer', 'min:1', 'max:' . max(1, $part->current_stock)],
            'reason' => ['required', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'scrap_date.required' => '請填寫報廢日期。',
            'scrap_date.before_or_equal' => '報廢日期不能晚於今天。',
            'scrap_time.date_format' => '時間格式不正確。',
            'quantity.required' => '請填寫報廢數量。',
            'quantity.min' => '報廢數量至少要 1。',
            'quantity.max' => "報廢數量不能超過目前庫存（{$part->withUnit($part->current_stock)}）。",
            'reason.required' => '請填寫報廢原因。',
            'reason.max' => '報廢原因最多 50 個字。',
            'note.max' => '備註最多 255 個字。',
        ]);

        try {
            $scrap = $this->stock->scrap($part, $validated, $request->user()?->id);
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['quantity' => $exception->getMessage()]);
        }
        // （依日期重新計算後庫存不足的錯誤，畫面會用彈出視窗顯示，見 layouts/app。）

        AuditLogger::log('created', $scrap, $validated, "零件報廢：{$part->part_code} {$part->name} × {$scrap->quantity}（{$scrap->reason}）");

        return redirect()->route('parts.show', $part)
            ->with('success', "已登記報廢 {$part->withUnit($scrap->quantity)}，庫存已扣除。");
    }
}
