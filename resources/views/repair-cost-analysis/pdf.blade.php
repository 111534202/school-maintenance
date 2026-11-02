<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>維修材料成本分析報表</title>
    <style>
        @font-face {
            font-family: 'Noto Sans TC';
            font-style: normal;
            font-weight: 400;
            src: url("file://{{ str_replace('\\', '/', resource_path('fonts/NotoSansTC/NotoSansTC-Regular.ttf')) }}") format("truetype");
        }

        @font-face {
            font-family: 'Noto Sans TC';
            font-style: normal;
            font-weight: 700;
            src: url("file://{{ str_replace('\\', '/', resource_path('fonts/NotoSansTC/NotoSansTC-Regular.ttf')) }}") format("truetype");
        }

        @page {
            margin: 24px 28px 42px;
        }

        body {
            font-family: 'Noto Sans TC', sans-serif;
            font-size: 10px;
            line-height: 1.6;
            color: #222;
        }

        h1 {
            font-size: 20px;
            text-align: center;
            margin: 0 0 6px;
        }

        h2 {
            font-size: 13px;
            margin: 20px 0 8px;
            padding-bottom: 4px;
            border-bottom: 1px solid #777;
        }

        p {
            margin: 3px 0;
        }

        .muted {
            color: #666;
        }

        .warning {
            border: 1px solid #b7791f;
            padding: 7px;
            margin: 12px 0;
        }

        .summary {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
        }

        .summary td {
            width: 33.33%;
            border: 1px solid #ccc;
            padding: 8px;
            vertical-align: top;
        }

        .metric-label {
            color: #555;
            font-size: 9px;
        }

        .metric-value {
            font-size: 15px;
            font-weight: bold;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        table.data th,
        table.data td {
            border: 1px solid #bbb;
            padding: 4px;
            text-align: left;
            vertical-align: top;
            word-wrap: break-word;
        }

        table.data th {
            background-color: #eee;
            font-weight: bold;
        }

        thead {
            display: table-header-group;
        }

        tr {
            page-break-inside: avoid;
        }

        .number {
            text-align: right !important;
        }

        .center {
            text-align: center !important;
        }

        .footer {
            position: fixed;
            bottom: -25px;
            left: 0;
            right: 0;
            text-align: center;
            color: #666;
            font-size: 8px;
        }

        .footer .page-number:after {
            content: counter(page);
        }

        .footer .page-count:after {
            content: counter(pages);
        }

        .no-data {
            text-align: center;
            color: #666;
            padding: 12px !important;
        }
    </style>
</head>
<body>
    <h1>維修材料成本分析報表</h1>
    <p class="center muted">依維修紀錄建立時間統計</p>
    <p>
        匯出時間：{{ now()->format('Y-m-d H:i:s') }}
    </p>
    <p>
        統計期間：
        {{ $startDate ?: '不限' }}
        至
        {{ $endDate ?: '不限' }}
    </p>
    <p>
        案件狀態：
        @php
            $statusLabels = [
                'pending' => '待處理',
                'assigned' => '已指派',
                'in_progress' => '處理中',
                'pending_review' => '待驗收',
                'completed' => '已完成',
            ];
        @endphp
        {{ $status ? ($statusLabels[$status] ?? $status) : '全部狀態' }}
    </p>

    <table class="summary">
        <tr>
            <td>
                <div class="metric-label">已知材料費小計</div>
                <div class="metric-value">
                    NT$ {{ number_format($knownSubtotal, 2) }}
                </div>
            </td>
            <td>
                <div class="metric-label">缺少單價的明細數</div>
                <div class="metric-value">
                    {{ number_format($missingPriceCount) }}
                </div>
            </td>
            <td>
                <div class="metric-label">涉及維修案件數</div>
                <div class="metric-value">
                    {{ number_format($repairRequestCount) }}
                </div>
            </td>
        </tr>
    </table>

    @if ($hasIncompleteCost)
        <div class="warning">
            注意：有 {{ $missingPriceCount }} 筆用料明細缺少歷史單價。
            已知材料費小計不包含這些缺價明細，因此不代表完整總成本。
        </div>
    @endif

    <h2>一、備品彙總</h2>

    <table class="data">
        <thead>
            <tr>
                <th style="width: 34%;">備品名稱</th>
                <th style="width: 18%;">使用總數量</th>
                <th style="width: 26%;">已知材料費小計</th>
                <th style="width: 22%;">缺少單價明細數</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($partUsageSummary as $item)
                <tr>
                    <td>{{ $item->part?->name ?? '零件資料不存在' }}</td>
                    <td class="number">
                        {{ number_format((int) $item->total_quantity) }}
                    </td>
                    <td class="number">
                        NT$ {{ number_format((float) $item->known_subtotal, 2) }}
                    </td>
                    <td class="number">
                        {{ number_format((int) $item->missing_price_count) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="no-data">此篩選條件下沒有用料資料。</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h2>二、用料明細</h2>

    <table class="data">
        <thead>
            <tr>
                <th style="width: 10%;">維修日期</th>
                <th style="width: 7%;">案件編號</th>
                <th style="width: 7%;">紀錄編號</th>
                <th style="width: 13%;">維修案件</th>
                <th style="width: 9%;">案件狀態</th>
                <th style="width: 11%;">設備</th>
                <th style="width: 11%;">教室／地點</th>
                <th style="width: 10%;">備品名稱</th>
                <th style="width: 5%;">數量</th>
                <th style="width: 8%;">歷史單價</th>
                <th style="width: 9%;">材料費小計</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($partUsages as $usage)
                @php
                    $log = $usage->repairLog;
                    $request = $log?->repairRequest;
                    $device = $request?->device;
                    $classroom = $request?->classroom ?? $device?->classroom;   // 報修單填的地點優先，沒有才用設備所在教室

                    $deviceLabel = $device
                        ? ($device->device_code ?: $device->asset_code ?: '設備資料')
                        : ($request?->device_note ?: '未指定設備');

                    $locationLabel = $classroom
                        ? trim(($classroom->classroom_code ?? '') . ' ' . ($classroom->name ?? ''))
                        : '未指定地點';

                    $priceMissing = $usage->unit_price === null;

                    $detailSubtotal = $priceMissing
                        ? null
                        : $usage->quantity * $usage->unit_price;
                @endphp
                <tr>
                    <td>{{ $log?->created_at?->format('Y-m-d') ?? '—' }}</td>
                    <td>{{ $request?->id ?? '—' }}</td>
                    <td>{{ $log?->id ?? '—' }}</td>
                    <td>{{ $request?->title ?? '案件資料不存在' }}</td>
                    <td>
                        @php
                            $requestStatus = $request?->status instanceof \App\Enums\RepairRequestStatus
                                ? $request->status->value
                                : ($request?->status ?? '');
                        @endphp
                        {{ $statusLabels[$requestStatus] ?? ($requestStatus !== '' ? $requestStatus : '—') }}
                    </td>
                    <td>{{ $deviceLabel }}</td>
                    <td>{{ $locationLabel }}</td>
                    <td>{{ $usage->part?->name ?? '零件資料不存在' }}</td>
                    <td class="number">{{ number_format((int) $usage->quantity) }}</td>
                    <td class="number">
                        @if ($priceMissing)
                            缺少單價
                        @else
                            {{ number_format((float) $usage->unit_price, 2) }}
                        @endif
                    </td>
                    <td class="number">
                        @if ($priceMissing)
                            缺少單價
                        @else
                            {{ number_format((float) $detailSubtotal, 2) }}
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="no-data">此篩選條件下沒有用料明細。</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        維修材料成本分析報表｜第 <span class="page-number"></span>
        頁，共 <span class="page-count"></span> 頁
    </div>
</body>
</html>
