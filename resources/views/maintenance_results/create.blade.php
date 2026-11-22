@extends('layouts.app')

@section('title', '回報保養結果')

@section('content')
    <h1 data-modal-hide class="tb-title mb-3">回報保養結果</h1>

    <div class="card mb-3">
        <div class="card-body">
            <p class="mb-1"><strong>工單編號：</strong>{{ $order->displayNumber() }}</p>
            <p class="mb-1"><strong>來源計畫：</strong>@if ($order->maintenancePlan)@include('maintenance_orders._plan_name', ['plan' => $order->maintenancePlan])@else — @endif</p>
            {{-- 跟保養紀錄一樣：「設備編號（類別）」，設備編號可點到設備履歷；沒有設備時只顯示類別。 --}}
            <p class="mb-0"><strong>設備 / 類別：</strong>@include('maintenance_orders._device_category', ['order' => $order])</p>
        </div>
    </div>

    <form action="{{ route('maintenance-orders.results.store', $order) }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="card shadow-sm">
            <div class="card-header bg-white form-section-title"><i class="bi bi-clipboard-check me-2"></i>保養結果</div>
            <div class="card-body">

        <div class="mb-3">
            <label class="form-label">保養結果 <span class="text-danger">*</span></label>
            <div>
                <div class="form-check form-check-inline">
                    <input type="radio" name="result" id="result_ok" value="ok" class="form-check-input"
                        {{ old('result') !== 'ng' ? 'checked' : '' }}>
                    <label for="result_ok" class="form-check-label">OK 正常</label>
                </div>
                <div class="form-check form-check-inline">
                    <input type="radio" name="result" id="result_ng" value="ng" class="form-check-input"
                        {{ old('result') === 'ng' ? 'checked' : '' }}>
                    <label for="result_ng" class="form-check-label">NG 異常</label>
                </div>
            </div>
            @error('result')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
            <div class="form-text">OK＝整張工單都正常，直接完成。NG＝要填寫說明並轉報修，工單改成「已轉報修」：工單只有一台設備就為那台建一張報修單；
                工單有設備類別或好幾個對象，就勾選裡面異常的設備，每台各建一張報修單。</div>
        </div>

        {{-- 有設備類別或好幾個對象的工單回報 NG：從工單的對象裡（還在使用、沒淘汰）的設備勾出異常的（可複選）。選 OK 時隱藏、也不會送出。
             工單只有一台設備時不用選，NG 就是那台。 --}}
        @if ($needsNgChoice)
            @php $oldNgIds = array_map('intval', (array) old('ng_device_ids', [])); @endphp
            <div class="mb-3" id="ngDevicesBlock" @if (old('result') !== 'ng') hidden @endif>
                <label class="form-label">NG 的設備（可複選） <span class="text-danger">*</span></label>
                @if ($ngDevices->isEmpty())
                    <p class="text-muted small mb-0">這張工單目前沒有可報修的設備（沒有設備，或都已淘汰／停用），NG 會建立一張不帶設備的報修單。</p>
                @else
                    @if ($ngDevices->count() > 6)
                        <input type="search" class="form-control form-control-sm mb-2" id="ngDeviceFilter" placeholder="輸入設備編號、教室搜尋" autocomplete="off">
                    @endif
                    <div class="border rounded p-2 @error('ng_device_ids') border-danger @enderror" style="max-height: 220px; overflow-y: auto;">
                        @foreach ($ngDevices as $ngDevice)
                            @php $ngLabel = $ngDevice->device_code . '（' . trim(($ngDevice->category?->name ?? '') . ' ' . ($ngDevice->classroom?->name ?? '')) . '）'; @endphp
                            <div class="form-check" data-ng-device data-search="{{ mb_strtolower($ngLabel) }}">
                                <input type="checkbox" class="form-check-input" name="ng_device_ids[]" value="{{ $ngDevice->id }}" id="ng_device_{{ $ngDevice->id }}"
                                    @checked(in_array($ngDevice->id, $oldNgIds, true))>
                                <label class="form-check-label" for="ng_device_{{ $ngDevice->id }}">{{ $ngLabel }}</label>
                            </div>
                        @endforeach
                    </div>
                @endif
                @error('ng_device_ids')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                @error('ng_device_ids.*')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
        @endif


        <div class="mb-3">
            <label class="form-label">執行人</label>
            {{-- 執行人固定是登入帳號（寫入時由系統帶入 users.id），所以只顯示、不讓人改。 --}}
            {{-- 跟零件資料的「目前庫存」一樣：灰色不能改的輸入框，下面是同樣顏色、帶鎖頭的說明。 --}}
            <input type="text" class="form-control" value="{{ auth()->user()->name }}" disabled>
            <div class="form-text text-warning-emphasis"><i class="bi bi-lock"></i> 由登入帳號自動帶入，不能手動修改</div>
        </div>

        <div class="mb-3">
            <label for="executed_at" class="form-label">執行時間 <span class="text-danger">*</span></label>
            <input type="datetime-local" name="executed_at" id="executed_at"
                class="form-control @error('executed_at') is-invalid @enderror"
                value="{{ old('executed_at', now()->format('Y-m-d\TH:i')) }}" required>
            @error('executed_at')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-4">
            <label for="note" class="form-label">說明 <span class="text-danger" id="noteRequiredMark" @if (old('result') !== 'ng') hidden @endif>*</span></label>
            <textarea name="note" id="note" class="form-control @error('note') is-invalid @enderror"
                rows="3" maxlength="2000" placeholder="NG 時必填：異常狀況（會帶進轉出的報修單）">{{ old('note') }}</textarea>
            @error('note')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        {{-- 保養用料（可不填）：和維修紀錄共用同一個零件選擇器；送出時依先進先出扣庫存，庫存不足會擋下。 --}}
        <div class="mb-4">
            <label class="form-label">零件使用明細</label>
            @include('parts._usage-picker', ['parts' => $parts])
        </div>

        {{-- 附件（可不填）：保養前後照片、檢查單等；和報修共用同一套規則（最多 5 個、加起來 5 MB，jpg/png/pdf/mp4/mov/webm）。 --}}
        <div class="mb-2">
            <label for="attachments" class="form-label">附件（選填，{{ \App\Support\AttachmentLimit::hint() }}）</label>
            <input type="file" class="form-control @error('attachments') is-invalid @enderror @error('attachments.*') is-invalid @enderror" id="attachments" name="attachments[]" multiple
                accept="{{ \App\Support\AttachmentLimit::ACCEPT }}" data-compress-images data-max-files="{{ \App\Support\AttachmentLimit::MAX_FILES }}" data-max-total-mb="{{ \App\Support\AttachmentLimit::MAX_TOTAL_KB / 1024 }}">
            @error('attachments')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            @error('attachments.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        </div>
        </div>

        {{-- 取消／送出按鈕放在外框外面、靠右。 --}}
        <div class="d-flex justify-content-end gap-2 mt-3">
            <a href="{{ route('maintenance-orders.show', $order) }}" class="btn btn-outline-secondary"><i class="bi bi-x-lg me-1"></i>取消</a>
            <button type="submit" class="btn btn-primary">送出</button>
        </div>
    </form>

    <script>
    // 選 NG 時說明改成必填（標紅色星號）；整類設備的工單另外顯示「NG 的設備」勾選（選 OK 時隱藏、停用，不會送出）。
    (function () {
        const mark = document.getElementById('noteRequiredMark');
        const note = document.getElementById('note');
        const block = document.getElementById('ngDevicesBlock');
        const filter = document.getElementById('ngDeviceFilter');
        function sync() {
            const ng = document.getElementById('result_ng').checked;
            mark.hidden = !ng;
            note.required = ng;
            if (block) {
                block.hidden = !ng;
                block.querySelectorAll('input[name="ng_device_ids[]"]').forEach(function (input) { input.disabled = !ng; });
            }
        }
        document.querySelectorAll('input[name="result"]').forEach(function (radio) { radio.addEventListener('change', sync); });
        if (filter) {
            filter.addEventListener('input', function () {
                const keyword = filter.value.trim().toLowerCase();
                block.querySelectorAll('[data-ng-device]').forEach(function (row) { row.hidden = keyword !== '' && !row.dataset.search.includes(keyword); });
            });
        }
        sync();

        // 執行時間的選擇器不限制可選的日期，按「送出」時才檢查不能晚於現在（和後端一樣容許 5 分鐘誤差）：
        // 先在畫面上擋下，不用送出後才發現、附件又要重新選。
        const executed = document.getElementById('executed_at');
        if (executed && executed.form) {
            const pad = (n) => String(n).padStart(2, '0');
            const checkExecuted = () => {
                const t = new Date(Date.now() + 5 * 60 * 1000);
                const latest = t.getFullYear() + '-' + pad(t.getMonth() + 1) + '-' + pad(t.getDate()) + 'T' + pad(t.getHours()) + ':' + pad(t.getMinutes());
                executed.setCustomValidity(executed.value && executed.value > latest ? '執行時間不能晚於現在的時間。' : '');
            };
            executed.addEventListener('input', () => executed.setCustomValidity(''));
            executed.form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((btn) => btn.addEventListener('click', checkExecuted));
        }
    })();
    </script>
@endsection
