{{-- 新增報修頁（對應 RepairRequestController::create / store）。三個區塊：設備（可掃描條碼或 QR 自動帶入）、問題描述、附件。 --}}
{{-- 掃描與自動帶入的 JavaScript 在 repairs/_device-scan.blade.php，掃描時呼叫 RepairRequestController::deviceLookup 查設備。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 套用主版面。 --}}
@extends('layouts.app')

@section('title', __('repair_requests.create.title'))

@section('content')
    <div class="page-narrow">
        <h1 class="h4 mb-4"><i class="bi bi-plus-circle me-2"></i>{{ __('repair_requests.create.title') }}</h1>

        {{-- 從知識庫「仍無法排除，前往報修」過來時，顯示提示（說明已看過哪篇文章）。 --}}
        @if ($fromKnowledgeBase)
            <div class="alert alert-warning d-flex gap-2 align-items-start">
                <i class="bi bi-info-circle mt-1"></i>
                <div>{{ __('repair_requests.create.from_kb_notice', ['title' => $fromKnowledgeBase->title]) }}</div>
            </div>
        @endif

        {{-- 表單送出到 POST /repairs（RepairRequestController::store）。enctype="multipart/form-data" 是上傳檔案的表單必備設定，少了它附件會送不出去。 --}}
        <form method="POST" action="{{ route('repairs.store') }}" enctype="multipart/form-data">
            {{-- 表單防偽 token（CSRF）：Laravel 要求所有 POST 表單都要有。 --}}
            @csrf

            {{-- 區塊一：設備。掃描或輸入設備條碼，JS 會自動查詢並帶入設備資訊與標題建議，
                 device_id 是真正寫進資料庫的外鍵；沒有條碼的設備才需要自己填文字描述。 --}}
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white form-section-title"><i class="bi bi-pc-display me-2"></i>{{ __('repair_requests.create.section_device') }}</div>
                <div class="card-body">
                    <label for="device_code_input" class="form-label">{{ __('repair_requests.create.device_code_label') }}</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
                        {{-- 設備條碼輸入框：手動輸入或用掃描器輸入，離開欄位時會自動查詢設備（見 _device-scan）。 --}}
                        <input type="text" class="form-control" id="device_code_input" autocomplete="off"
                            placeholder="{{ __('repair_requests.create.device_code_placeholder') }}"
                            value="{{ $device->device_code ?? '' }}">
                        {{-- 用手機／筆電鏡頭掃描設備上的 QR 或條碼貼紙。 --}}
                        <button type="button" class="btn btn-outline-primary" id="scan_camera_btn"
                            data-bs-toggle="modal" data-bs-target="#scanModal"
                            title="{{ __('repair_requests.create.scan_button') }}" aria-label="{{ __('repair_requests.create.scan_button') }}">
                            <i class="bi bi-camera"></i>
                        </button>
                    </div>
                    <div class="form-text">{{ __('repair_requests.create.device_code_hint') }}</div>
                    {{-- 查不到設備時顯示的紅字錯誤（預設隱藏，由 JavaScript 控制顯示）。 --}}
                    <div id="device_lookup_error" class="text-danger small mt-2" style="display:none;">
                        <i class="bi bi-exclamation-circle me-1"></i>{{ __('repair_requests.create.device_lookup_error') }}
                    </div>
                    {{-- 查到設備時顯示的綠色確認框（預設隱藏；從設備入口頁帶 ?device= 過來時一開始就會顯示）。 --}}
                    <div id="device_display" class="alert alert-success py-2 mt-3 mb-0" style="{{ $device ? '' : 'display:none;' }}">
                        <i class="bi bi-check-circle me-1"></i>{{ __('repair_requests.create.device_display_label') }}<strong id="device_display_text">{{ $device?->device_code }} {{ $device?->category?->name }} {{ $device?->brand }} {{ $device?->model }}（{{ $device?->classroom?->room_name ?? $device?->classroom?->room_code }}）</strong>
                    </div>
                    {{-- 隱藏欄位：真正送給後端的設備編號（JavaScript 查到設備後會填入）。 --}}
                    <input type="hidden" id="device_id" name="device_id" value="{{ $device->id ?? '' }}">

                    <hr class="my-4">

                    <div class="row g-3">
                        <div class="col-md-7">
                            <label for="device_note" class="form-label">{{ __('repair_requests.create.device_note_label') }}</label>
                            {{-- 沒有條碼的設備：手動描述設備與地點，當作後備資料。 --}}
                            <input type="text" class="form-control" id="device_note" name="device_note" value="{{ old('device_note') }}">
                        </div>
                        <div class="col-md-5">
                            <label for="location" class="form-label">{{ __('repair_requests.create.location_label') }}</label>
                            <input type="text" class="form-control" id="location" name="location" value="{{ old('location') }}">
                        </div>
                    </div>
                    <div class="form-text mt-2">{{ __('repair_requests.create.device_note_hint') }}</div>
                </div>
            </div>

            {{-- 區塊二：問題描述 --}}
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white form-section-title"><i class="bi bi-exclamation-triangle me-2"></i>{{ __('repair_requests.create.section_issue') }}</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="title" class="form-label">{{ __('repair_requests.create.title_label') }}</label>
                            {{-- 報修標題：預設值依序是 ① 驗證失敗帶回的內容 ② 知識庫文章標題 ③ 依設備自動建議的標題（教室 + 類別 + 故障）。 --}}
                            <input type="text" class="form-control" id="title" name="title"
                                value="{{ old('title', $fromKnowledgeBase?->title ?? ($device ? trim(($device->classroom->room_code ?? '') . ' ' . ($device->category->name ?? '') . '故障') : '')) }}" required>
                        </div>
                        <div class="col-12">
                            <label for="description" class="form-label">{{ __('repair_requests.create.description_label') }}</label>
                            <textarea class="form-control" id="description" name="description" rows="5" required>{{ old('description', $fromKnowledgeBase ? __('repair_requests.create.description_prefill', ['title' => $fromKnowledgeBase->title]) : '') }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="impact_level" class="form-label">{{ __('repair_requests.create.impact_level_label') }}</label>
                            {{-- 影響程度：輕微／中等／嚴重（預設中等）。 --}}
                            <select class="form-select" id="impact_level" name="impact_level" required>
                                <option value="low" @selected(old('impact_level') === 'low')>{{ __('repair_requests.impact_level.low') }}</option>
                                <option value="medium" @selected(old('impact_level', 'medium') === 'medium')>{{ __('repair_requests.impact_level.medium') }}</option>
                                <option value="high" @selected(old('impact_level') === 'high')>{{ __('repair_requests.impact_level.high') }}</option>
                            </select>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            {{-- 核取方塊沒勾時瀏覽器不會送出欄位，靠這個隱藏欄位確保一定送出 0 或 1。 --}}
                            <input type="hidden" name="affects_class" value="0">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" role="switch" id="affects_class" name="affects_class" value="1"
                                    {{ old('affects_class') ? 'checked' : '' }}>
                                <label class="form-check-label" for="affects_class">{{ __('repair_requests.create.affects_class_label') }}</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 區塊三：附件 --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white form-section-title"><i class="bi bi-paperclip me-2"></i>{{ __('repair_requests.create.section_attachments') }}</div>
                <div class="card-body">
                    <label for="attachments" class="form-label">{{ __('repair_requests.create.attachments_label') }}</label>
                    {{-- 附件：可一次選多個檔案（欄位名稱以 [] 結尾 = 陣列）；accept 只是提示瀏覽器的檔案類型，真正的限制（最多 5 個、單檔 20MB）在後端 StoreRepairRequestRequest。 --}}
                    <input type="file" class="form-control" id="attachments" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.pdf,.mp4,.mov,.webm">
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a class="btn btn-outline-secondary" href="{{ route('repairs.index') }}"><i class="bi bi-x-lg me-1"></i>{{ __('common.buttons.cancel') }}</a>
                <button class="btn btn-primary" type="submit"><i class="bi bi-send me-1"></i>{{ __('repair_requests.create.submit') }}</button>
            </div>
        </form>
    </div>

    {{-- 引入「掃描設備條碼」的視窗與 JavaScript。 --}}
    @include('repairs._device-scan')
@endsection
