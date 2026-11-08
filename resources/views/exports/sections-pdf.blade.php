<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    {{-- 「勾選區塊與欄位」匯出的通用 PDF（零件履歷等）：$title、$meta（標題下方的說明文字）、$sections（見 App\Support\ExportSections）。 --}}
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
    <h1>{{ $title }}</h1>
    @foreach ($meta ?? [] as $line)
        <p>{{ $line }}</p>
    @endforeach

    @php($numbers = ['一', '二', '三', '四', '五', '六'])
    @foreach ($sections as $section)
        <h2>{{ $numbers[$loop->index] ?? $loop->iteration }}、{{ $section['title'] }}</h2>
        <table class="data">
            <thead>
                <tr>
                    @foreach ($section['headings'] as $i => $heading)
                        <th class="{{ in_array($i, $section['numeric'], true) ? 'number' : '' }}">{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($section['rows'] as $row)
                    <tr>
                        @foreach ($row as $i => $value)
                            <td class="{{ in_array($i, $section['numeric'], true) ? 'number' : '' }}">{{ \App\Support\ExportSections::display($value) }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($section['headings']) }}" class="no-data">沒有資料。</td></tr>
                @endforelse
            </tbody>
        </table>
    @endforeach

    <div class="footer">
        {{ $title }}｜第 <span class="page-number"></span>
        頁，共 <span class="page-count"></span> 頁
    </div>
</body>
</html>
