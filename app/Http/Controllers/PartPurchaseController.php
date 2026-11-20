<?php

namespace App\Http\Controllers;

use App\Models\Part;
use App\Models\PartPurchase;
use App\Services\AttachmentUploader;
use App\Support\AttachmentLimit;
use App\Services\AuditLogger;
use App\Services\PartStockService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 零件採購紀錄：新增（入庫並加庫存）、修改，可上傳發票或收據；採購紀錄是歷史紀錄，不提供刪除。
 * 權限與零件庫存相同（parts.manage，見 routes/web.php）。
 * 數量與庫存的連動都交給 PartStockService，這裡只負責表單與附件。
 */
class PartPurchaseController extends Controller
{
    /** 一筆採購最多幾個附件（全站共用 App\Support\AttachmentLimit：最多 5 個、加起來 5 MB 以內）。 */
    public const MAX_ATTACHMENTS = AttachmentLimit::MAX_FILES;

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

    /** 查看一筆採購紀錄（唯讀）：送出後的採購紀錄不能修改或刪除，只能查看。 */
    public function show(Part $part, PartPurchase $purchase): \Illuminate\Contracts\View\View
    {
        abort_unless($purchase->part_id === $part->id, 404);

        return view('parts.purchases.show', ['part' => $part, 'purchase' => $purchase->load('attachments')]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'purchase_date' => ['required', 'date', 'before_or_equal:today'],
            'vendor_name' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'unit_price' => ['required', 'integer', 'min:0', 'max:100000000'],
            'note' => ['nullable', 'string', 'max:255'],
            // 附件（發票、收據等）：PDF 或圖片，一筆最多 5 個、加起來 5 MB 以內（單檔不另外限制）。
            // invoices[] 是畫面上的多檔欄位；invoice（單一檔案）保留給舊的寫法。
            'invoices' => ['nullable', 'array', 'max:' . self::MAX_ATTACHMENTS, AttachmentLimit::totalSizeRule()],
            'invoices.*' => ['file', 'mimes:pdf,jpg,jpeg,png'],
            'invoice' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', AttachmentLimit::totalSizeRule()],
            'remove_invoice' => ['nullable', 'boolean'],
            'remove_attachments' => ['nullable', 'array'],
            'remove_attachments.*' => ['integer'],
        ], [
            'purchase_date.before_or_equal' => '採購日期不能晚於今天。',
            'invoices.max' => '一筆採購最多 ' . self::MAX_ATTACHMENTS . ' 個附件。',
            'invoices.*.mimes' => '附件只能是 PDF 或圖片（JPG、PNG）。',
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
