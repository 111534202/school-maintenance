{{-- 設備詳細頁（對應 DeviceController::show）：左邊顯示設備資料，右邊顯示 QR Code 與下載、預覽按鈕。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 套用主版面。 --}}
@extends('layouts.app')

@section('title', '設備詳細資料')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">設備詳細資料</h3>
        <div>
            <a href="{{ route('devices.edit', $device) }}" class="btn btn-outline-primary">編輯</a>
            <a href="{{ route('devices.index') }}" class="btn btn-outline-secondary">返回列表</a>
        </div>
    </div>

    <div class="row g-4">
    <div class="col-lg-8">
    <div class="card p-4">
        {{-- dl / dt / dd 是 HTML 的「名稱／內容」清單：dt 是欄位名稱、dd 是內容，Bootstrap 把它排成左右兩欄。 --}}
        <dl class="row mb-0">
            <dt class="col-sm-4">設備編號</dt>
            <dd class="col-sm-8">{{ $device->device_code }}</dd>

            <dt class="col-sm-4">資產編號</dt>
            {{-- ?? '－'：這個欄位沒有資料時顯示全形破折號，畫面不會留白。 --}}
            <dd class="col-sm-8">{{ $device->asset_code ?? '－' }}</dd>

            <dt class="col-sm-4">類別</dt>
            <dd class="col-sm-8">{{ $device->category->name ?? '－' }}</dd>

            <dt class="col-sm-4">品牌 / 型號</dt>
            <dd class="col-sm-8">{{ $device->brand }} {{ $device->model }}</dd>

            <dt class="col-sm-4">序號</dt>
            <dd class="col-sm-8">{{ $device->serial_number ?? '－' }}</dd>

            <dt class="col-sm-4">保固期限</dt>
            {{-- 保固期限格式化成「年-月-日」；optional() 讓沒填保固期限時不會報錯。 --}}
            <dd class="col-sm-8">{{ optional($device->warranty_until)->format('Y-m-d') ?? '－' }}</dd>

            <dt class="col-sm-4">所在教室</dt>
            <dd class="col-sm-8">
                {{ $device->classroom->room_code ?? '－' }} - {{ $device->classroom->room_name ?? '' }}
                （{{ $device->classroom->department->name ?? '未指定部門' }}）
            </dd>

            <dt class="col-sm-4">狀態</dt>
            <dd class="col-sm-8"><span class="badge bg-info text-dark">{{ \App\Models\Device::statusLabel($device->status) }}</span></dd>

            <dt class="col-sm-4">核心設備</dt>
            <dd class="col-sm-8">
                @if ($device->is_core)
                    <span class="badge bg-warning text-dark">是</span>
                @else
                    <span class="text-muted">否</span>
                @endif
            </dd>
        </dl>
    </div>
    </div>

    <div class="col-lg-4">
        <div class="card p-4 text-center">
            {{-- QR Code 區：圖片來自 devices.qrcode 路由（即時產生的 SVG）。 --}}
            <h6 class="mb-3">設備 QR Code</h6>
            <img src="{{ route('devices.qrcode', $device) }}" alt="設備 QR Code" class="img-fluid mb-3" style="max-width: 220px; margin: 0 auto;">
            <p class="text-muted small mb-3">掃描後開啟本設備的自助入口頁，內容只依設備代碼即時查詢，不會寫死教室等資料。</p>
            {{-- 下載按鈕：download 屬性讓瀏覽器把圖片存成檔案（檔名用設備編號）。 --}}
            <a href="{{ route('devices.qrcode', $device) }}" class="btn btn-outline-secondary btn-sm" download="{{ $device->device_code }}-qrcode.svg">下載 QR Code</a>
            {{-- 預覽入口頁：在新分頁開啟掃描後會看到的畫面。 --}}
            <a href="{{ route('devices.entry', $device) }}" class="btn btn-outline-primary btn-sm mt-2" target="_blank">預覽入口頁</a>
        </div>
    </div>
    </div>
@endsection
