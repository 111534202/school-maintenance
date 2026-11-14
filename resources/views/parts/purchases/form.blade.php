@extends('layouts.app')

@php($editing = $purchase->exists)

@section('title', ($editing ? '修改採購紀錄' : '新增採購紀錄') . '：' . $part->part_code)

@section('content')
    <h1 data-modal-hide class="tb-title mb-1">{{ $editing ? '修改採購紀錄' : '新增採購紀錄' }}</h1>
    <p class="text-muted mb-3">{{ $part->name }}{{ $part->specification ? '（' . $part->specification . '）' : '' }}</p>

    <div>
        <form method="POST" enctype="multipart/form-data"
            action="{{ $editing ? route('parts.purchases.update', [$part, $purchase]) : route('parts.purchases.store', $part) }}">
            @csrf
            <div class="card shadow-sm">
                <div class="card-header bg-white form-section-title"><i class="bi bi-cart me-2"></i>採購資料</div>
                <div class="card-body">
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
                        class="form-control @if ($locked) bg-body-secondary @endif @error('quantity') is-invalid @enderror"
                        value="{{ old('quantity', $purchase->quantity) }}">
                    @error('quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-6">
                    <label for="unit_price" class="form-label">單價（元） <span class="text-danger">*</span></label>
                    <input type="number" id="unit_price" name="unit_price" min="0" required @readonly($locked)
                        class="form-control @if ($locked) bg-body-secondary @endif @error('unit_price') is-invalid @enderror"
                        value="{{ old('unit_price', $purchase->unit_price) }}">
                    @error('unit_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            {{-- 總價提示緊貼在數量／單價下面（跟附件欄位下方的說明一樣的間距）。
                 這一批已被用掉或報廢時：數量、單價變成灰底唯讀（跟「修改零件基本資料」的目前庫存一樣），原因顯示在總價下面。 --}}
            <div class="form-text {{ $locked ? '' : 'mb-3' }}" id="totalHint">總價＝數量×單價，系統自動計算。</div>
            @if ($locked)
                <div class="form-text text-warning-emphasis mb-3">
                    <i class="bi bi-lock"></i> 這批已有 {{ $purchase->usedQuantity() }} 個被維修、保養用掉或報廢，數量與單價不可再修改。
                </div>
            @endif

            {{-- 附件：一筆最多 5 個、加起來 5 MB 以內（上限在 App\Support\AttachmentLimit；保留的舊附件也算在總和裡）。
                 修改時，原本的附件各自可以勾選刪除；新選的檔案加在後面。 --}}
            @php($maxAttachments = \App\Http\Controllers\PartPurchaseController::MAX_ATTACHMENTS)
            @php($currentAttachments = $editing ? $purchase->attachments : collect())
            <div class="mb-3">
                <label for="invoices" class="form-label">附件 <span class="text-muted small ms-1">發票、收據等，PDF 或圖片</span></label>
                @if ($currentAttachments->isNotEmpty())
                    <ul class="list-unstyled small mb-2">
                        @foreach ($currentAttachments as $attachment)
                            <li class="d-flex flex-wrap align-items-center gap-3 mb-1">
                                <button type="button" class="btn btn-link btn-sm p-0" data-bs-toggle="modal" data-bs-target="#attachmentModal"
                                    data-url="{{ route('attachments.show', $attachment) }}" data-mime="{{ $attachment->mime_type }}" data-filename="{{ $attachment->file_name }}"
                                    data-title="附件 {{ $loop->iteration }}">附件 {{ $loop->iteration }}：查看</button>
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" value="{{ $attachment->id }}" id="remove_attachment_{{ $attachment->id }}"
                                        name="remove_attachments[]" data-bytes="{{ (int) $attachment->size_bytes }}" @checked(in_array($attachment->id, (array) old('remove_attachments', [])))>
                                    <label class="form-check-label text-danger" for="remove_attachment_{{ $attachment->id }}">刪除</label>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <input type="file" id="invoices" name="invoices[]" multiple accept=".pdf,.jpg,.jpeg,.png"
                    data-max-files="{{ $maxAttachments - $currentAttachments->count() }}" data-max-files-total="{{ $maxAttachments }}"
                    data-max-total-mb="{{ \App\Support\AttachmentLimit::MAX_TOTAL_KB / 1024 }}" data-existing-bytes="{{ (int) $currentAttachments->sum('size_bytes') }}"
                    class="form-control @error('invoices') is-invalid @enderror @error('invoices.*') is-invalid @enderror">
                <div class="form-text">一筆{{ \App\Support\AttachmentLimit::hint() }}（原本保留的附件也算在內）。</div>
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
            </div>
            </div>

            {{-- 取消／送出按鈕放在外框外面、靠右。 --}}
            <div class="d-flex justify-content-end gap-2 mt-3">
                <a href="{{ route('parts.show', $part) }}" class="btn btn-outline-secondary"><i class="bi bi-x-lg me-1"></i>取消</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>{{ $editing ? '儲存' : '新增採購並加入庫存' }}</button>
            </div>
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
                const keptBoxes = Array.prototype.filter.call(removes, function (c) { return !c.checked; });
                files.dataset.maxFiles = String(parseInt(files.dataset.maxFilesTotal, 10) - keptBoxes.length);
                // 保留的舊附件大小也算進 5 MB 總和。
                files.dataset.existingBytes = String(keptBoxes.reduce(function (sum, c) { return sum + (parseInt(c.dataset.bytes, 10) || 0); }, 0));
            }
            removes.forEach(function (c) { c.addEventListener('change', updateFileLimit); });
            updateFileLimit();
        })();
    </script>

    {{-- 修改時點「附件 n：查看」先在畫面中預覽（跟零件履歷一樣），不直接跳到新頁面。 --}}
    @if ($editing)
        @include('parts._attachment-modal')
    @endif
@endsection
