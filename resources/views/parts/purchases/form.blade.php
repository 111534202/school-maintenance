@extends('layouts.app')

@php($editing = $purchase->exists)

@section('title', ($editing ? '修改採購紀錄' : '新增採購紀錄') . '：' . $part->part_code)

@section('content')
    <h1 class="h3 mb-1">{{ $editing ? '修改採購紀錄' : '新增採購紀錄' }}</h1>
    <p class="text-muted mb-3">{{ $part->displayName() }}　目前庫存 {{ $part->withUnit($part->current_stock) }}</p>

    @if ($editing && $purchase->isUsed())
        <div class="alert alert-warning">
            這一批已經有 {{ $purchase->usedQuantity() }} 個被維修或保養用掉，數量與單價不能再修改；日期、廠商、備註與發票仍可修改。
        </div>
    @endif

    <div class="card p-4" style="max-width: 560px;">
        <form method="POST" enctype="multipart/form-data"
            action="{{ $editing ? route('parts.purchases.update', [$part, $purchase]) : route('parts.purchases.store', $part) }}">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            @php($locked = $editing && $purchase->isUsed())

            <div class="mb-3">
                <label for="purchase_date" class="form-label">採購日期 <span class="text-danger">*</span></label>
                <input type="date" id="purchase_date" name="purchase_date" max="{{ now()->toDateString() }}" required
                    class="form-control @error('purchase_date') is-invalid @enderror"
                    value="{{ old('purchase_date', $purchase->purchase_date?->format('Y-m-d')) }}">
                @error('purchase_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label for="vendor_name" class="form-label">廠商 <span class="text-danger">*</span></label>
                <input type="text" id="vendor_name" name="vendor_name" maxlength="255" required
                    class="form-control @error('vendor_name') is-invalid @enderror"
                    value="{{ old('vendor_name', $purchase->vendor_name) }}">
                @error('vendor_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="row g-3">
                <div class="col-sm-6">
                    <label for="quantity" class="form-label">數量 <span class="text-danger">*</span></label>
                    <input type="number" id="quantity" name="quantity" min="1" required @readonly($locked)
                        class="form-control @error('quantity') is-invalid @enderror"
                        value="{{ old('quantity', $purchase->quantity) }}">
                    @error('quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-6">
                    <label for="unit_price" class="form-label">單價（元） <span class="text-danger">*</span></label>
                    <input type="number" id="unit_price" name="unit_price" min="0" required @readonly($locked)
                        class="form-control @error('unit_price') is-invalid @enderror"
                        value="{{ old('unit_price', $purchase->unit_price) }}">
                    @error('unit_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            {{-- 總價提示緊貼在數量／單價下面（跟附件欄位下方的說明一樣的間距）。 --}}
            <div class="form-text mb-3" id="totalHint">總價＝數量×單價，系統自動計算。</div>

            {{-- 附件：一個檔案最多 2 MB、一筆最多 5 個（上限在 PartPurchaseController 的 MAX_FILE_KB、MAX_ATTACHMENTS）。
                 修改時，原本的附件各自可以勾選刪除；新選的檔案加在後面。 --}}
            @php($maxAttachments = \App\Http\Controllers\PartPurchaseController::MAX_ATTACHMENTS)
            @php($currentAttachments = $editing ? $purchase->attachments : collect())
            <div class="mb-3">
                <label for="invoices" class="form-label">附件 <span class="text-muted small ms-1">發票、收據等，PDF 或圖片</span></label>
                @if ($currentAttachments->isNotEmpty())
                    <ul class="list-unstyled small mb-2">
                        @foreach ($currentAttachments as $attachment)
                            <li class="d-flex flex-wrap align-items-center gap-3 mb-1">
                                <a href="{{ route('attachments.show', $attachment) }}" target="_blank" rel="noopener">附件 {{ $loop->iteration }}：查看</a>
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" value="{{ $attachment->id }}" id="remove_attachment_{{ $attachment->id }}"
                                        name="remove_attachments[]" @checked(in_array($attachment->id, (array) old('remove_attachments', [])))>
                                    <label class="form-check-label text-danger" for="remove_attachment_{{ $attachment->id }}">刪除</label>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <input type="file" id="invoices" name="invoices[]" multiple accept=".pdf,.jpg,.jpeg,.png"
                    data-max-mb="2" data-max-files="{{ $maxAttachments - $currentAttachments->count() }}" data-max-files-total="{{ $maxAttachments }}"
                    class="form-control @error('invoices') is-invalid @enderror @error('invoices.*') is-invalid @enderror">
                <div class="form-text">一個檔案上限 2 MB，一筆最多 {{ $maxAttachments }} 個檔案。</div>
                @error('invoices')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @foreach ($errors->get('invoices.*') as $messages)
                    <div class="invalid-feedback">{{ $messages[0] }}</div>
                @endforeach
            </div>

            <div class="mb-4">
                <label for="note" class="form-label">備註</label>
                <input type="text" id="note" name="note" maxlength="255"
                    class="form-control @error('note') is-invalid @enderror"
                    value="{{ old('note', $purchase->note) }}">
                @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <button type="submit" class="btn btn-primary">{{ $editing ? '儲存' : '新增採購並加入庫存' }}</button>
            <a href="{{ route('parts.show', $part) }}" class="btn btn-outline-secondary">取消</a>
        </form>
    </div>

    <script>
        // 即時顯示總價，方便核對發票金額。
        (function () {
            const qty = document.getElementById('quantity');
            const price = document.getElementById('unit_price');
            const hint = document.getElementById('totalHint');
            function update() {
                const total = (parseInt(qty.value, 10) || 0) * (parseInt(price.value, 10) || 0);
                hint.textContent = '總價＝數量×單價：NT$ ' + total.toLocaleString();
            }
            qty.addEventListener('input', update);
            price.addEventListener('input', update);
            if (qty.value && price.value) update();

            // 還能再上傳幾個附件：上限－（原本的附件－勾選刪除的）；版面底部的檢查會用 data-max-files。
            const files = document.getElementById('invoices');
            const removes = document.querySelectorAll('input[name="remove_attachments[]"]');
            function updateFileLimit() {
                const kept = removes.length - Array.prototype.filter.call(removes, function (c) { return c.checked; }).length;
                files.dataset.maxFiles = String(parseInt(files.dataset.maxFilesTotal, 10) - kept);
            }
            removes.forEach(function (c) { c.addEventListener('change', updateFileLimit); });
            updateFileLimit();
        })();
    </script>
@endsection
