{{-- 設備入口頁：掃描設備上的 QR Code（或輸入 /d/設備編號）後看到的畫面（對應 DeviceEntryController::show）。 --}}
{{-- 左邊顯示設備資料，右邊提供「前往報修」與「自助排除知識庫」。任何登入者都能看。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 套用主版面。 --}}
@extends('layouts.app')

@section('title', $device->device_code . ' 設備入口')

@section('content')
    <h3 class="mb-3">{{ $device->device_code }}</h3>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card p-4">
                {{-- dl / dt / dd 是 HTML 的「名稱／內容」清單，Bootstrap 把它排成左右兩欄。 --}}
                <dl class="row mb-0">
                    <dt class="col-sm-4">設備編號</dt>
                    <dd class="col-sm-8">{{ $device->device_code }}</dd>

                    {{-- 以下依序顯示：類別、品牌／型號、所在教室（含所屬部門）、目前狀態。 --}}
                    <dt class="col-sm-4">類別</dt>
                    <dd class="col-sm-8">{{ $device->category->name ?? '－' }}</dd>

                    <dt class="col-sm-4">品牌 / 型號</dt>
                    <dd class="col-sm-8">{{ $device->brand }} {{ $device->model }}</dd>

                    <dt class="col-sm-4">所在教室</dt>
                    <dd class="col-sm-8">
                        {{ $device->classroom->room_code ?? '－' }} - {{ $device->classroom->room_name ?? '' }}
                        （{{ $device->classroom->department->name ?? '未指定部門' }}）
                    </dd>

                    {{-- 目前狀態徽章；核心設備再加一個「核心設備」徽章。 --}}
                    <dt class="col-sm-4">目前狀態</dt>
                    <dd class="col-sm-8">
                        <span class="badge bg-info text-dark">{{ \App\Models\Device::statusLabel($device->status) }}</span>
                        @if ($device->is_core)
                            <span class="badge bg-warning text-dark">核心設備</span>
                        @endif
                    </dd>
                </dl>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card p-4">
                <h6 class="mb-3">需要協助嗎？</h6>

                {{-- 報修路由存在才顯示可點的「前往報修」按鈕（網址已帶好這台設備）；否則顯示停用的按鈕。 --}}
                @if ($repairUrl)
                    <a href="{{ $repairUrl }}" class="btn btn-danger w-100 mb-2">前往報修</a>
                @else
                    <button class="btn btn-outline-secondary w-100 mb-2" disabled>前往報修（功能尚未上線）</button>
                @endif

                {{-- 知識庫路由存在就顯示連結；否則顯示幾條通用的「自助排除小提醒」。 --}}
                @if ($kbUrl)
                    <a href="{{ $kbUrl }}" class="btn btn-outline-primary w-100 mb-3">查看自助排除知識庫</a>
                @else
                    <div class="mb-3">
                        <p class="small text-muted mb-1">自助排除小提醒：</p>
                        <ul class="small text-muted mb-0">
                            <li>先確認電源與連接線是否正常</li>
                            <li>重新開機一次再測試</li>
                            <li>仍無法排除，請使用上方報修按鈕</li>
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
