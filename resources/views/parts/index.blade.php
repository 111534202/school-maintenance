<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>零件列表</title>
</head>
<body>
    <h1>零件列表</h1>

    @if ($parts->isEmpty())
        <p>目前沒有零件資料。</p>
    @else
        <table border="1">
            <thead>
                <tr>
                    <th>編號</th>
                    <th>零件名稱</th>
                    <th>規格</th>
                    <th>單價</th>
                    <th>目前庫存</th>
                    <th>安全庫存</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($parts as $part)
                    <tr>
                        <td>{{ $part->id }}</td>
                        <td>{{ $part->name }}</td>
                        <td>{{ $part->specification ?? '—' }}</td>
                        <td>{{ $part->unit_price }}</td>
                        <td>{{ $part->current_stock }}</td>
                        <td>{{ $part->safety_stock }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>