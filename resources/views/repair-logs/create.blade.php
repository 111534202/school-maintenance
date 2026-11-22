{{-- 維修填單頁（對應 RepairLogController::create / store）：維修人員處理完填故障原因、處置方式、起訖時間、使用的零件與照片。 --}}
{{-- 送出後案件狀態自動從「已派工」變成「待驗收」。（Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 套用主版面。 --}}
{{-- 維修填單頁：維修原因、處置方式、起訖時間、備品明細與附件。 --}}
@extends('layouts.app')

@section('title', __('repair_logs.create_title'))

@section('content')
    <div class="page-narrow">
        <h1 data-modal-hide class="tb-title mb-1">
            {{ __('repair_logs.create_title') }}
        </h1>

        <p class="text-muted mb-4">
            {{ __('repair_logs.case_line', [
                'title' => $repairRequest->title,
                'location' => $repairRequest->device->device_code
                    ?? $repairRequest->device_note
                    ?? $repairRequest->locationLabel()
                    ?? __('repair_logs.not_filled_location')
            ]) }}
        </p>

        <form method="POST"
              action="{{ route('repair-logs.store', $repairRequest) }}"
              enctype="multipart/form-data">
            @csrf

            {{-- 報修時沒有設備（例如電燈壞了沒有條碼可掃）：維修人員要補選維修的設備才能送出（必填）。
                 可輸入設備編號、類別、教室搜尋，只能從清單裡選；送出後報修單會記上這台設備。 --}}
            @if ($repairRequest->device_id === null)
                @php
                    $selectedDevice = $devices->firstWhere('id', (int) old('device_id'));
                    $deviceLabel = fn ($d) => $d->device_code . '（' . trim(($d->category?->name ?? '') . ' ' . ($d->classroom?->name ?? '')) . '）';
                @endphp
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white form-section-title"><i class="bi bi-pc-display me-2"></i>維修的設備</div>
                    <div class="card-body">
                        <div class="position-relative">
                            <label for="device_search" class="form-label">設備</label>
                            <input type="text" id="device_search" class="form-control @error('device_id') is-invalid @enderror" required
                                placeholder="輸入設備編號、類別或教室搜尋" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false"
                                value="{{ $selectedDevice ? $deviceLabel($selectedDevice) : '' }}">
                            <input type="hidden" id="device_id" name="device_id" autocomplete="off" value="{{ $selectedDevice?->id }}">
                            <div id="device_options" class="list-group position-absolute w-100 shadow-sm" role="listbox" style="display:none; z-index:1050; max-height:240px; overflow-y:auto;">
                                @foreach ($devices as $device)
                                    <button type="button" class="list-group-item list-group-item-action text-start" role="option"
                                        data-id="{{ $device->id }}" data-label="{{ $deviceLabel($device) }}"
                                        data-search="{{ mb_strtolower($deviceLabel($device) . ' ' . $device->brand . ' ' . $device->model . ' ' . $device->asset_code) }}">{{ $deviceLabel($device) }}</button>
                                @endforeach
                                <div id="device_no_results" class="list-group-item text-muted" style="display:none;">找不到符合的設備</div>
                            </div>
                            <div class="form-text">報修時沒有掃到設備（{{ $repairRequest->device_note ?: ($repairRequest->locationLabel() ?? '未填寫位置') }}），請補上實際維修的設備。</div>
                            @error('device_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
                <script>
                // 維修的設備選擇器：輸入關鍵字篩選，或點開清單直接選；只能選清單裡的設備（沒選就清空）。
                document.addEventListener('DOMContentLoaded', function () {
                    const search = document.getElementById('device_search');
                    const hidden = document.getElementById('device_id');
                    const list = document.getElementById('device_options');
                    const noResults = document.getElementById('device_no_results');
                    const options = Array.from(list.querySelectorAll('button[data-id]'));
                    function closeList() { list.style.display = 'none'; search.setAttribute('aria-expanded', 'false'); }
                    function choose(option) { hidden.value = option.dataset.id; search.value = option.dataset.label; search.classList.remove('is-invalid'); closeList(); }
                    function filter() {
                        const keyword = hidden.value ? '' : search.value.trim().toLowerCase();
                        let shown = 0;
                        options.forEach(function (option) {
                            const matched = !keyword || option.dataset.search.includes(keyword);
                            option.style.display = matched ? '' : 'none';
                            if (matched) { shown++; }
                        });
                        noResults.style.display = shown === 0 ? '' : 'none';
                        list.style.display = '';
                        search.setAttribute('aria-expanded', 'true');
                    }
                    search.addEventListener('focus', filter);
                    search.addEventListener('input', function () { hidden.value = ''; filter(); });
                    search.addEventListener('blur', function () { closeList(); if (!hidden.value) { search.value = ''; } });
                    search.addEventListener('keydown', function (event) {
                        if (event.key === 'Escape') { closeList(); }
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            const first = options.find(function (o) { return o.style.display !== 'none'; });
                            if (first) { choose(first); }
                        }
                    });
                    options.forEach(function (option) { option.addEventListener('mousedown', function (event) { event.preventDefault(); choose(option); }); });
                });
                </script>
            @endif

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white form-section-title"><i class="bi bi-wrench-adjustable me-2"></i>維修處理資料</div>
                <div class="card-body">

                    <div class="row g-3">
                        <div class="col-12">
                            <label for="cause" class="form-label">
                                {{ __('repair_logs.form.cause') }}
                            </label>
                            <textarea
                                class="form-control @error('cause') is-invalid @enderror"
                                id="cause"
                                name="cause"
                                rows="3"
                                required>{{ old('cause') }}</textarea>
                            @error('cause')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label for="resolution" class="form-label">
                                {{ __('repair_logs.form.resolution') }}
                            </label>
                            <textarea
                                class="form-control @error('resolution') is-invalid @enderror"
                                id="resolution"
                                name="resolution"
                                rows="3"
                                required>{{ old('resolution') }}</textarea>
                            @error('resolution')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="started_at" class="form-label">
                                {{ __('repair_logs.form.started_at') }}
                            </label>
                            <input
                                type="datetime-local"
                                class="form-control @error('started_at') is-invalid @enderror"
                                id="started_at"
                                name="started_at"
                                value="{{ old('started_at') }}"
                                min="{{ $repairRequest->created_at?->format('Y-m-d\TH:i') }}"
                                required>
                            @error('started_at')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="ended_at" class="form-label">
                                {{ __('repair_logs.form.ended_at') }}
                            </label>
                            <input
                                type="datetime-local"
                                class="form-control @error('ended_at') is-invalid @enderror"
                                id="ended_at"
                                name="ended_at"
                                value="{{ old('ended_at') }}"
                                min="{{ $repairRequest->created_at?->format('Y-m-d\TH:i') }}"
                                required>
                            @error('ended_at')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- 正式零件使用明細：預設沒有任何一列，按「新增使用紀錄」才加一列（選擇器見 parts/_usage-picker）。 --}}
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white form-section-title"><i class="bi bi-box-seam me-2"></i>零件使用明細</div>
                <div class="card-body">

                    @include('parts._usage-picker', ['parts' => $parts])
                </div>
            </div>

            {{-- 附件（維修前後照片／影片）。用了哪些零件填在上面的零件明細（自由填寫的「使用備品說明」已拿掉）。 --}}
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white form-section-title"><i class="bi bi-paperclip me-2"></i>附件</div>
                <div class="card-body">
                    <div>
                        <label for="attachments" class="form-label">
                            {{ __('repair_logs.form.attachments') }}
                        </label>

                        <input
                            type="file"
                            class="form-control @error('attachments') is-invalid @enderror"
                            id="attachments"
                            name="attachments[]"
                            multiple
                            accept="{{ \App\Support\AttachmentLimit::ACCEPT }}"
                            data-compress-images
                            data-max-files="{{ \App\Support\AttachmentLimit::MAX_FILES }}" data-max-total-mb="{{ \App\Support\AttachmentLimit::MAX_TOTAL_KB / 1024 }}">

                        <div class="form-text">
                            {{ \App\Support\AttachmentLimit::hint() }}。
                        </div>

                        @error('attachments')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror

                        @foreach (old('attachments', []) as $index => $unused)
                            @error("attachments.$index")
                                <div class="text-danger small">{{ $message }}</div>
                            @enderror
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a
                    class="btn btn-outline-secondary"
                    href="{{ route('repairs.show', $repairRequest) }}">
                    <i class="bi bi-x-lg me-1"></i>{{ __('common.buttons.cancel') }}
                </a>

                <button class="btn btn-primary" type="submit">
                    <i class="bi bi-send me-1"></i>{{ __('repair_logs.submit') }}
                </button>
            </div>
        </form>

        <script>
        // 送出前先在畫面上檢查時間（結束不能早於開始、都不能晚於現在），有錯就不送出：
        // 送出後才被擋下的話，瀏覽器不能把剛選的附件自動填回去，要重新選一次。
        (function () {
            const started = document.getElementById('started_at');
            const ended = document.getElementById('ended_at');
            if (!started || !ended) return;
            const pad = (n) => String(n).padStart(2, '0');
            const now = new Date(Date.now() + 5 * 60 * 1000);   // 和後端一樣容許 5 分鐘誤差
            const max = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate()) + 'T' + pad(now.getHours()) + ':' + pad(now.getMinutes());
            started.max = max;
            ended.max = max;
            function check() {
                ended.setCustomValidity(started.value && ended.value && ended.value < started.value ? '結束時間不能早於開始時間。' : '');
            }
            started.addEventListener('change', check);
            ended.addEventListener('change', check);
            check();
        })();
        </script>
    </div>
@endsection
