<?php

namespace App\Http\Controllers;

use App\Models\Part;
use App\Models\PartPurchase;
use App\Services\AttachmentUploader;
use App\Services\AuditLogger;
use App\Services\PartStockService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 零件採購紀錄：新增（入庫並加庫存）、修改、刪除，可上傳發票或收據。
 * 權限與零件庫存相同（parts.manage，見 routes/web.php）。
 * 數量與庫存的連動都交給 PartStockService，這裡只負責表單與附件。
 */
class PartPurchaseController extends Controller
{
    public function __construct(private PartStockService $stock, private AttachmentUploader $uploader)
    {
    }

    public function create(Part $part)
    {
        return view('parts.purchases.form', ['part' => $part, 'purchase' => new PartPurchase(['purchase_date' => now()])]);
    }

    public function store(Request $request, Part $part)
    {
        $validated = $this->validated($request);

        $purchase = DB::transaction(function () use ($request, $part, $validated) {
            $purchase = $this->stock->receive($part, $this->purchaseData($validated));
            $this->storeInvoice($request, $purchase);

            return $purchase;
        });

        AuditLogger::log('created', $purchase, $this->purchaseData($validated), "新增零件採購：{$part->part_code} {$part->name} × {$purchase->quantity}");

        return redirect()->route('parts.show', $part)
            ->with('success', "已新增採購紀錄，庫存增加 {$purchase->quantity}。");
    }

    public function edit(Part $part, PartPurchase $purchase)
    {
        abort_unless($purchase->part_id === $part->id, 404);

        return view('parts.purchases.form', compact('part', 'purchase'));
    }

    public function update(Request $request, Part $part, PartPurchase $purchase)
    {
        abort_unless($purchase->part_id === $part->id, 404);

        $validated = $this->validated($request);

        try {
            DB::transaction(function () use ($request, $purchase, $validated) {
                $this->stock->update($purchase, $this->purchaseData($validated));

                if ($request->hasFile('invoice')) {
                    $this->storeInvoice($request, $purchase, replace: true);   // 上傳新檔案：取代原本的附件
                } elseif ($request->boolean('remove_invoice')) {
                    $purchase->deleteAttachments();                             // 勾選「刪除目前的附件」
                }
            });
        } catch (DomainException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        AuditLogger::log('updated', $purchase, $this->purchaseData($validated), "修改零件採購：{$part->part_code} {$part->name}");

        return redirect()->route('parts.show', $part)->with('success', '採購紀錄已更新。');
    }

    public function destroy(Part $part, PartPurchase $purchase)
    {
        abort_unless($purchase->part_id === $part->id, 404);

        try {
            $this->stock->delete($purchase);
        } catch (DomainException $exception) {
            return redirect()->route('parts.show', $part)->with('error', $exception->getMessage());
        }

        AuditLogger::log('deleted', $part, ['purchase_date' => $purchase->purchase_date->toDateString(), 'quantity' => $purchase->quantity],
            "刪除零件採購：{$part->part_code} {$part->name} × {$purchase->quantity}");

        return redirect()->route('parts.show', $part)
            ->with('success', "已刪除採購紀錄，庫存減少 {$purchase->quantity}。");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'purchase_date' => ['required', 'date', 'before_or_equal:today'],
            'vendor_name' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_price' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
            // 附件（發票、收據等）：一個檔案，PDF 或圖片，最多 10 MB。
            'invoice' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'remove_invoice' => ['nullable', 'boolean'],
        ], [
            'purchase_date.before_or_equal' => '採購日期不能晚於今天。',
        ]);
    }

    private function purchaseData(array $validated): array
    {
        return [
            'purchase_date' => $validated['purchase_date'],
            'vendor_name' => $validated['vendor_name'],
            'quantity' => (int) $validated['quantity'],
            'unit_price' => (int) $validated['unit_price'],
            'note' => $validated['note'] ?? null,
        ];
    }

    /** 儲存附件；修改時上傳新檔案會取代舊的附件。 */
    private function storeInvoice(Request $request, PartPurchase $purchase, bool $replace = false): void
    {
        if (! $request->hasFile('invoice')) {
            return;
        }

        if ($replace) {
            $purchase->deleteAttachments();
        }

        $this->uploader->storeMany($purchase, [$request->file('invoice')]);
    }
}
