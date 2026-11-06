<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>零件清單</title>
    {{-- 零件庫存列表「匯出 Excel／PDF」的 PDF：只含預覽視窗勾選的零件與欄位。字型設定同成本分析報表 PDF。 --}}
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
        @page { margin: 24px 28px 42px; }
        body { font-family: 'Noto Sans TC', sans-serif; font-size: 10px; line-height: 1.6; color: #222; }
        h1 { font-size: 18px; text-align: center; margin: 0 0 6px; }
        p { margin: 3px 0; }
        .muted { color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #bbb; padding: 4px 6px; text-align: left; vertical-align: top; word-wrap: break-word; }
        th { background-color: #eee; font-weight: bold; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .number { text-align: right; }
        .footer { position: fixed; bottom: -25px; left: 0; right: 0; text-align: center; color: #666; font-size: 8px; }
        .footer .page-number:after { content: counter(page); }
        .footer .page-count:after { content: counter(pages); }
    </style>
</head>
<body>
    <h1>零件清單</h1>
    <p class="muted">匯出時間：{{ now()->format('Y-m-d H:i') }}　共 {{ $rows->count() }} 項{{ filled($keyword) ? '　搜尋：' . $keyword : '' }}</p>

    <table>
        <thead>
            <tr>
                @foreach ($columns as $key => $label)
                    <th class="{{ in_array($key, \App\Support\PartExportColumns::NUMERIC, true) ? 'number' : '' }}">{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    @foreach ($columns as $key => $label)
                        <td class="{{ in_array($key, \App\Support\PartExportColumns::NUMERIC, true) ? 'number' : '' }}">{{ $row[$key] }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">零件清單｜第 <span class="page-number"></span> 頁，共 <span class="page-count"></span> 頁</div>
</body>
</html>
