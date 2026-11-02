@extends('layouts.app')

@php($editing = $purchase->exists)

@section('title', ($editing ? '修改採購紀錄' : '新增採購紀錄') . '：' . $part->part_code)

@section('content')
    <h1 class="h3 mb-1">{{ $editing ? '修改採購紀錄' : '新增採購紀錄' }}</h1>
    <p class="text-muted mb-3">{{ $part->displayName() }}　目前庫存 {{ $part->current_stock }}</p>

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

            <div class="row g-3 mb-3">
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
            <div class="form-text mb-3" id="totalHint">總價＝數量×單價，系統自動計算。</div>

            @php($currentAttachment = $editing ? $purchase->attachments->first() : null)
            <div class="mb-3">
                <label for="invoice" class="form-label">附件</label>
                @if ($currentAttachment)
                    <div class="d-flex flex-wrap align-items-center gap-3 mb-2 small">
                        <span>目前已有附件：<a href="{{ route('attachments.show', $currentAttachment) }}" target="_blank" rel="noopener">查看</a></span>
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" value="1" id="remove_invoice" name="remove_invoice" @checked(old('remove_invoice'))>
                            <label class="form-check-label text-danger" for="remove_invoice">刪除目前的附件</label>
                        </div>
                    </div>
                @endif
                <input type="file" id="invoice" name="invoice" accept=".pdf,.jpg,.jpeg,.png"
                    class="form-control @error('invoice') is-invalid @enderror">
                <div class="form-text">發票、收據等，PDF 或圖片，最多 10 MB。@if ($currentAttachment)上傳新檔案會取代目前的附件。@endif</div>
                @error('invoice')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
        })();
    </script>
@endsection
