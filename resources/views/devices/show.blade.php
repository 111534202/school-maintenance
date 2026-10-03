{{-- 設備詳細頁（對應 DeviceController::show）：左邊顯示設備資料，右邊顯示 QR Code 與下載、預覽按鈕。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 套用主版面。 --}}
@extends('layouts.app')

@section('title', __('devices.show.title'))

@section('content')
    <div class="page-header mb-4">
        <h1 class="h4 mb-0"><i class="bi bi-pc-display me-2"></i>{{ __('devices.show.title') }}</h1>
        <div class="d-inline-flex gap-1">
            <a class="btn btn-outline-secondary icon-btn" href="{{ route('devices.index') }}"
                title="{{ __('common.buttons.back_to_list') }}" aria-label="{{ __('common.buttons.back_to_list') }}"><i class="bi bi-arrow-left"></i></a>
            <a class="btn btn-outline-primary icon-btn" href="{{ route('devices.edit', $device) }}"
                title="{{ __('common.buttons.edit') }}" aria-label="{{ __('common.buttons.edit') }}"><i class="bi bi-pencil"></i></a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body">
                    {{-- dl / dt / dd 是 HTML 的「名稱／內容」清單：dt 是欄位名稱、dd 是內容，Bootstrap 把它排成左右兩欄。 --}}
                    <dl class="row mb-0 gy-2">
                        <dt class="col-sm-4 text-muted fw-normal">{{ __('devices.form.code') }}</dt>
                        <dd class="col-sm-8 mb-0">{{ $device->device_code }}</dd>

                        <dt class="col-sm-4 text-muted fw-normal">{{ __('devices.form.asset_code') }}</dt>
                        {{-- ?? 的用法：這個欄位沒有資料時顯示破折號，畫面不會留白。 --}}
                        <dd class="col-sm-8 mb-0">{{ $device->asset_code ?? __('devices.no_value') }}</dd>

                        <dt class="col-sm-4 text-muted fw-normal">{{ __('devices.form.category') }}</dt>
                        <dd class="col-sm-8 mb-0">{{ $device->category->name ?? __('devices.no_value') }}</dd>

                        <dt class="col-sm-4 text-muted fw-normal">{{ __('devices.show.brand_model') }}</dt>
                        <dd class="col-sm-8 mb-0">{{ $device->brand }} {{ $device->model }}</dd>

                        <dt class="col-sm-4 text-muted fw-normal">{{ __('devices.form.serial_number') }}</dt>
                        <dd class="col-sm-8 mb-0">{{ $device->serial_number ?? __('devices.no_value') }}</dd>

                        <dt class="col-sm-4 text-muted fw-normal">{{ __('devices.form.warranty_until') }}</dt>
                        {{-- 保固期限格式化成「年-月-日」；optional() 讓沒填保固期限時不會報錯。 --}}
                        <dd class="col-sm-8 mb-0">{{ optional($device->warranty_until)->format('Y-m-d') ?? __('devices.no_value') }}</dd>

                        <dt class="col-sm-4 text-muted fw-normal">{{ __('devices.form.classroom') }}</dt>
                        <dd class="col-sm-8 mb-0">
                            {{ $device->classroom->room_code ?? __('devices.no_value') }} - {{ $device->classroom->room_name ?? '' }}
                            （{{ $device->classroom->department->name ?? __('devices.no_department') }}）
                        </dd>

                        <dt class="col-sm-4 text-muted fw-normal">{{ __('devices.form.status') }}</dt>
                        <dd class="col-sm-8 mb-0"><span class="badge text-bg-info">{{ \App\Models\Device::statusLabel($device->status) }}</span></dd>

                        <dt class="col-sm-4 text-muted fw-normal">{{ __('devices.form.is_core') }}</dt>
                        <dd class="col-sm-8 mb-0">
                            @if ($device->is_core)
                                <span class="badge text-bg-warning">{{ __('devices.yes') }}</span>
                            @else
                                <span class="text-muted">{{ __('devices.no') }}</span>
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    {{-- QR Code 區：圖片來自 devices.qrcode 路由（即時產生的 SVG）。 --}}
                    <h2 class="h6 mb-3">{{ __('devices.show.qr_title') }}</h2>
                    <img src="{{ route('devices.qrcode', $device) }}" alt="{{ __('devices.show.qr_alt') }}" class="img-fluid mb-3" style="max-width: 220px; margin: 0 auto;">
                    <p class="text-muted small mb-3">{{ __('devices.show.qr_hint') }}</p>
                    <div class="d-flex flex-wrap justify-content-center gap-2">
                        {{-- 下載按鈕：download 屬性讓瀏覽器把圖片存成檔案（檔名用設備編號）。 --}}
                        <a href="{{ route('devices.qrcode', $device) }}" class="btn btn-outline-secondary btn-sm" download="{{ $device->device_code }}-qrcode.svg"><i class="bi bi-download me-1"></i>{{ __('devices.show.qr_download') }}</a>
                        {{-- 預覽入口頁：在新分頁開啟掃描後會看到的畫面。 --}}
                        <a href="{{ route('devices.entry', $device) }}" class="btn btn-outline-primary btn-sm" target="_blank"><i class="bi bi-box-arrow-up-right me-1"></i>{{ __('devices.show.qr_preview') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
