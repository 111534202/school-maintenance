{{-- 設備詳細頁（對應 DeviceController::show）：左邊顯示設備資料（含進行中工單數），右邊顯示 QR Code；
     QR Code 標題旁的下載按鈕會跳出小視窗，可以選「新分頁開啟」「下載 JPG」「下載 SVG」。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 套用主版面。 --}}
@extends('layouts.app')

@section('title', __('devices.show.title'))

@section('content')
    <div class="page-header mb-4">
        <h1 data-modal-hide class="tb-title mb-0">{{ __('devices.show.title') }}</h1>
        {{-- 在設備主檔的對話框裡打開（?modal=1）時不放「修改」按鈕（修改在列表的鉛筆按鈕）。 --}}
        @unless (request()->boolean('modal') || $device->status === 'retired')
            <div class="d-inline-flex gap-1">
                <a class="btn btn-outline-primary icon-btn" href="{{ route('devices.edit', $device) }}"
                    title="{{ __('common.buttons.edit') }}" aria-label="{{ __('common.buttons.edit') }}"><i class="bi bi-pencil"></i></a>
            </div>
        @endunless
        {{-- 已淘汰的設備資料鎖住；不小心淘汰時，系統管理員可以「取消淘汰」改回正常。 --}}
        @if ($device->status === 'retired' && auth()->user()->isAdmin())
            <form method="POST" action="{{ route('devices.unretire', $device) }}" data-confirm="確定要取消淘汰，把 {{ $device->device_code }} 改回「正常」嗎？" data-confirm-ok="取消淘汰" data-confirm-variant="primary">
                @csrf
                @method('PATCH')
                <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-arrow-counterclockwise me-1"></i>取消淘汰</button>
            </form>
        @endif
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

                        <dt class="col-sm-4 text-muted fw-normal">{{ __('devices.form.warranty_end_date') }}</dt>
                        {{-- 保固期限格式化成「年-月-日」；optional() 讓沒填保固期限時不會報錯。 --}}
                        <dd class="col-sm-8 mb-0">{{ optional($device->warranty_end_date)->format('Y-m-d') ?? __('devices.no_value') }}</dd>

                        <dt class="col-sm-4 text-muted fw-normal">{{ __('devices.form.classroom') }}</dt>
                        <dd class="col-sm-8 mb-0">
                            {{ $device->classroom->classroom_code ?? __('devices.no_value') }} - {{ $device->classroom->name ?? '' }}
                            （{{ $device->classroom->department->name ?? __('devices.no_department') }}）
                        </dd>


                        <dt class="col-sm-4 text-muted fw-normal">{{ __('devices.form.status') }}</dt>
                        <dd class="col-sm-8 mb-0"><span class="badge {{ \App\Models\Device::statusBadgeClass($device->display_status) }}">{{ \App\Models\Device::statusLabel($device->display_status) }}</span></dd>

                        {{-- 進行中工單：大於 0 顯示藍色徽章，點了到報修看板（關鍵字帶設備編號，只看未結案），開在整個頁面。 --}}
                        <dt class="col-sm-4 text-muted fw-normal">進行中工單</dt>
                        <dd class="col-sm-8 mb-0">
                            @if ($device->open_repairs_count > 0)
                                <a class="badge tb-pill tb-pill-info text-decoration-none" target="_top" href="{{ route('repairs.index', ['keyword' => $device->device_code, 'open' => 1]) }}"
                                    title="{{ __('devices.links.view_repairs') }}"><i class="bi bi-tools me-1"></i>{{ $device->open_repairs_count }}</a>
                            @else
                                <span class="text-muted">0</span>
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    {{-- QR Code 區：圖片來自 devices.qrcode 路由（即時產生的 SVG）。標題旁的下載按鈕打開下面的小視窗。 --}}
                    <div class="d-flex justify-content-center align-items-center gap-2 mb-3">
                        <h2 class="h6 mb-0">{{ __('devices.show.qr_title') }}</h2>
                        <button type="button" class="btn btn-outline-primary btn-sm icon-btn" id="qrDownloadOpen"
                            title="{{ __('devices.show.qr_download') }}" aria-label="{{ __('devices.show.qr_download') }}"><i class="bi bi-download"></i></button>
                    </div>
                    <img id="qrImage" src="{{ route('devices.qrcode', $device) }}" alt="{{ __('devices.show.qr_alt') }}" class="img-fluid mb-3" style="max-width: 220px; margin: 0 auto;">
                    <p class="text-muted small mb-0">{{ __('devices.show.qr_hint') }}</p>
                </div>
            </div>
        </div>
    </div>
    {{-- 下載 QR Code 的小視窗：新分頁開啟（看大圖、用瀏覽器列印）、下載 JPG（一般圖片，圖下方附設備編號）、下載 SVG（向量檔，放大印刷不會糊）。 --}}
    <dialog id="qrDownloadDialog" class="tb-confirm-dialog" aria-labelledby="qrDownloadTitle">
        <form method="dialog">
            <h2 id="qrDownloadTitle" class="tb-confirm-title">{{ __('devices.show.qr_download') }}</h2>
            <p class="tb-confirm-message text-muted small">{{ $device->device_code }} 的 QR Code，要怎麼開啟或下載？</p>
            <div class="d-grid gap-2 mb-3">
                <a class="btn btn-outline-primary" href="{{ route('devices.qrcode', $device) }}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>新分頁開啟</a>
                <button type="button" class="btn btn-outline-primary" id="qrDownloadJpg"><i class="bi bi-file-earmark-image me-1"></i>下載成 JPG</button>
                <a class="btn btn-outline-primary" href="{{ route('devices.qrcode', $device) }}" download="{{ $device->device_code }}-qrcode.svg"><i class="bi bi-filetype-svg me-1"></i>下載成 SVG（印刷用）</a>
            </div>
            <div class="tb-confirm-actions">
                <button type="submit" class="btn btn-outline-secondary">{{ __('common.buttons.cancel') }}</button>
            </div>
        </form>
    </dialog>
    <script>
    (function () {
        var dialog = document.getElementById('qrDownloadDialog');
        var open = document.getElementById('qrDownloadOpen');
        if (!dialog || !open) { return; }
        open.addEventListener('click', function () { dialog.showModal(); });
        // 點選項後關閉視窗（新分頁、下載 SVG 用瀏覽器原本的行為）。
        dialog.querySelectorAll('a').forEach(function (a) { a.addEventListener('click', function () { dialog.close(); }); });

        // 下載 JPG：把 QR Code（SVG）畫到白底的畫布上，下方寫設備編號，再存成 JPG。
        document.getElementById('qrDownloadJpg').addEventListener('click', function () {
            var code = @json($device->device_code);
            var img = new Image();
            img.onload = function () {
                var size = 600, pad = 40, text = 70;
                var canvas = document.createElement('canvas');
                canvas.width = size + pad * 2;
                canvas.height = size + pad * 2 + text;
                var ctx = canvas.getContext('2d');
                ctx.fillStyle = '#fff';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                ctx.imageSmoothingEnabled = false;
                ctx.drawImage(img, pad, pad, size, size);
                ctx.fillStyle = '#000';
                ctx.font = 'bold 40px sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText(code, canvas.width / 2, size + pad + text - 15);
                var link = document.createElement('a');
                link.download = code + '-qrcode.jpg';
                link.href = canvas.toDataURL('image/jpeg', 0.95);
                document.body.appendChild(link);
                link.click();
                link.remove();
                dialog.close();
            };
            img.src = document.getElementById('qrImage').src;
        });
    })();
    </script>
@endsection
