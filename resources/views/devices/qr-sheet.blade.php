{{-- 設備 QR 貼紙列印頁（對應 DeviceController::qrSheet）：A4 一頁 3 欄 × 8 列 = 24 張貼紙，按「列印」直接印。 --}}
{{-- 獨立頁面（不套主版面）：畫面上有工具列，列印時工具列自動隱藏，只印貼紙。 --}}
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('devices.qr_sheet.title') }}</title>
    <link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
    <style>
        body { background: #f4f5f4; }
        .toolbar { position: sticky; top: 0; z-index: 5; background: #fff; border-bottom: 1px solid #dee2e6; padding: 12px 16px; }
        .sheet { display: grid; grid-template-columns: repeat(3, 63.5mm); gap: 0; width: 190.5mm; margin: 12px auto; background: #fff; }
        /* 貼紙尺寸 63.5 × 33.9 mm：和常見的 A4 24 格標籤紙（3×8）相同，可直接貼；沒有標籤紙用一般白紙印再剪也可以。 */
        .label { width: 63.5mm; height: 33.9mm; box-sizing: border-box; padding: 2.5mm; display: flex; gap: 2.5mm; align-items: center; border: 0.2mm dashed #bbb; overflow: hidden; break-inside: avoid; }
        .label .qr { flex: 0 0 26mm; width: 26mm; height: 26mm; }
        .label .qr svg { width: 100%; height: 100%; display: block; }
        .label .info { min-width: 0; line-height: 1.25; }
        .label .code { font-weight: 700; font-size: 11pt; word-break: break-all; }
        .label .meta { font-size: 7.5pt; color: #333; }
        @page { size: A4; margin: 10mm 9mm; }
        @media print {
            body { background: #fff; }
            .toolbar, .notice { display: none !important; }
            .sheet { margin: 0; width: auto; }
            .label { border-color: #ccc; }
        }
    </style>
</head>
<body>
    <div class="toolbar d-flex flex-wrap align-items-center gap-2">
        <button type="button" class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer me-1"></i>{{ __('devices.qr_sheet.print') }}</button>
        <a class="btn btn-outline-secondary" href="{{ route('devices.index', request()->query()) }}"><i class="bi bi-arrow-left me-1"></i>{{ __('devices.qr_sheet.back') }}</a>
        <span class="text-muted small">{{ __('devices.qr_sheet.summary', ['count' => $labels->count(), 'pages' => (int) ceil($labels->count() / 24)]) }}</span>
    </div>

    <div class="container notice mt-3" style="max-width: 190mm;">
        @if ($total > $limit)
            <div class="alert alert-warning">{{ __('devices.qr_sheet.too_many', ['total' => $total, 'limit' => $limit]) }}</div>
        @endif
        <div class="alert alert-info small mb-0">{{ __('devices.qr_sheet.tip') }}</div>
    </div>

    @if ($labels->isEmpty())
        <div class="container mt-4"><div class="alert alert-secondary">{{ __('devices.qr_sheet.empty') }}</div></div>
    @else
        <div class="sheet">
            @foreach ($labels as $label)
                <div class="label">
                    <div class="qr">{!! $label['svg'] !!}</div>
                    <div class="info">
                        <div class="code">{{ $label['device']->device_code }}</div>
                        <div class="meta">{{ $label['device']->category->name ?? '' }}</div>
                        <div class="meta">{{ trim(($label['device']->brand ?? '') . ' ' . ($label['device']->model ?? '')) }}</div>
                        <div class="meta">{{ $label['device']->classroom->name ?? '' }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</body>
</html>
