{{-- 設備入口頁：掃描設備上的 QR Code（或輸入 /d/設備編號）後看到的畫面（對應 DeviceEntryController::show）。 --}}
{{-- 左邊顯示設備資料，右邊提供「前往報修」與「自助排除知識庫」。任何登入者都能看。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 套用主版面。 --}}
@extends('layouts.app')

@section('title', $device->device_code . ' ' . __('devices.entry.title_suffix'))

@section('content')
    <h1 class="h4 mb-4"><i class="bi bi-qr-code-scan me-2"></i>{{ $device->device_code }}</h1>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-body">
                    {{-- dl / dt / dd 是 HTML 的「名稱／內容」清單，Bootstrap 把它排成左右兩欄。 --}}
                    <dl class="row mb-0 gy-2">
                        <dt class="col-sm-4 text-muted fw-normal">{{ __('devices.form.code') }}</dt>
                        <dd class="col-sm-8 mb-0">{{ $device->device_code }}</dd>

                        {{-- 以下依序顯示：類別、品牌／型號、所在教室（含所屬部門）、目前狀態。 --}}
                        <dt class="col-sm-4 text-muted fw-normal">{{ __('devices.form.category') }}</dt>
                        <dd class="col-sm-8 mb-0">{{ $device->category->name ?? __('devices.no_value') }}</dd>

                        <dt class="col-sm-4 text-muted fw-normal">{{ __('devices.show.brand_model') }}</dt>
                        <dd class="col-sm-8 mb-0">{{ $device->brand }} {{ $device->model }}</dd>

                        <dt class="col-sm-4 text-muted fw-normal">{{ __('devices.form.classroom') }}</dt>
                        <dd class="col-sm-8 mb-0">
                            {{ $device->classroom->room_code ?? __('devices.no_value') }} - {{ $device->classroom->room_name ?? '' }}
                            （{{ $device->classroom->department->name ?? __('devices.no_department') }}）
                        </dd>

                        {{-- 目前狀態徽章；核心設備再加一個「核心設備」徽章。 --}}
                        <dt class="col-sm-4 text-muted fw-normal">{{ __('devices.entry.current_status') }}</dt>
                        <dd class="col-sm-8 mb-0">
                            <span class="badge {{ \App\Models\Device::statusBadgeClass($device->status) }}">{{ \App\Models\Device::statusLabel($device->status) }}</span>
                            @if ($device->is_core)
                                <span class="badge text-bg-warning">{{ __('devices.entry.core_device') }}</span>
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h6 mb-3">{{ __('devices.entry.need_help') }}</h2>

                    {{-- 報修路由存在才顯示可點的「前往報修」按鈕（網址已帶好這台設備）；否則顯示停用的按鈕。 --}}
                    @if ($repairUrl)
                        <a href="{{ $repairUrl }}" class="btn btn-danger w-100 mb-2"><i class="bi bi-tools me-1"></i>{{ __('devices.entry.go_repair') }}</a>
                    @else
                        <button class="btn btn-outline-secondary w-100 mb-2" disabled><i class="bi bi-tools me-1"></i>{{ __('devices.entry.go_repair_unavailable') }}</button>
                    @endif

                    {{-- 知識庫路由存在就顯示連結；否則顯示幾條通用的「自助排除小提醒」。 --}}
                    @if ($kbUrl)
                        <a href="{{ $kbUrl }}" class="btn btn-outline-primary w-100"><i class="bi bi-book me-1"></i>{{ __('devices.entry.view_kb') }}</a>
                    @else
                        <div>
                            <p class="small text-muted mb-1">{{ __('devices.entry.tips_title') }}</p>
                            <ul class="small text-muted mb-0">
                                <li>{{ __('devices.entry.tip_power') }}</li>
                                <li>{{ __('devices.entry.tip_reboot') }}</li>
                                <li>{{ __('devices.entry.tip_report') }}</li>
                            </ul>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
