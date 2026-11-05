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
    /** 一筆採購最多幾個附件、每個檔案最大幾 KB（2 MB）。畫面上的說明文字與檢查也用這兩個值。 */
    public const MAX_ATTACHMENTS = 5;

    public const MAX_FILE_KB = 2048;

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
            $this->storeInvoices($request, $purchase);

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

        // 附件總數：原本的－勾選刪除的＋新上傳的，不能超過上限。
        $removeIds = $request->boolean('remove_invoice')
            ? $purchase->attachments()->pluck('id')->all()
            : array_map('intval', (array) $request->input('remove_attachments', []));
        $keep = $purchase->attachments()->whereNotIn('id', $removeIds)->count();
        if ($keep + count($this->uploadedFiles($request)) > self::MAX_ATTACHMENTS) {
            return back()->withInput()->withErrors(['invoices' => '一筆採購最多 ' . self::MAX_ATTACHMENTS . ' 個附件；目前保留 ' . $keep . ' 個，請先勾選刪除一些再上傳。']);
        }

        try {
            DB::transaction(function () use ($request, $purchase, $validated, $removeIds) {
                $this->stock->update($purchase, $this->purchaseData($validated));
                $purchase->deleteAttachments($removeIds);   // 勾選「刪除」的附件
                $this->storeInvoices($request, $purchase);  // 新上傳的附件加在後面
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
            // 附件（發票、收據等）：PDF 或圖片，一個檔案最多 2 MB，一筆最多 5 個。
            // invoices[] 是畫面上的多檔欄位；invoice（單一檔案）保留給舊的寫法。
            'invoices' => ['nullable', 'array', 'max:' . self::MAX_ATTACHMENTS],
            'invoices.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:' . self::MAX_FILE_KB],
            'invoice' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:' . self::MAX_FILE_KB],
            'remove_invoice' => ['nullable', 'boolean'],
            'remove_attachments' => ['nullable', 'array'],
            'remove_attachments.*' => ['integer'],
        ], [
            'purchase_date.before_or_equal' => '採購日期不能晚於今天。',
            'invoices.max' => '一筆採購最多 ' . self::MAX_ATTACHMENTS . ' 個附件。',
            'invoices.*.max' => '每個附件最多 2 MB。',
            'invoices.*.mimes' => '附件只能是 PDF 或圖片（JPG、PNG）。',
            'invoice.max' => '每個附件最多 2 MB。',
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

    /** 這次上傳的檔案（多檔欄位 invoices[] 與舊的單檔欄位 invoice 合在一起）。 */
    private function uploadedFiles(Request $request): array
    {
        return array_values(array_filter([...(array) $request->file('invoices', []), $request->file('invoice')]));
    }

    /** 儲存新上傳的附件（加在原本的附件後面）。 */
    private function storeInvoices(Request $request, PartPurchase $purchase): void
    {
        $files = $this->uploadedFiles($request);
        if ($files !== []) {
            $this->uploader->storeMany($purchase, $files);
        }
    }
}
