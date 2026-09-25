<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>零件列表</title>
</head>
<body>
    <h1>零件列表</h1>

    <form method="GET" action="/parts">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="搜尋零件名稱">
        <button type="submit">搜尋</button>
    </form>

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
                    <th>操作</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($parts as $part)
                    <tr>
                        <td>{{ $part->id }}</td>
                        <td>{{ $part->name }}</td>
                        <td>{{ $part->specification ?? '—' }}</td>
                        <td>{{ $part->unit_price }}</td>
                        <td>
                            {{ $part->current_stock }}

                            @if ($part->current_stock < $part->safety_stock)
                                <strong>⚠ 低庫存</strong>
                            @endif
                        </td>
                        <td>{{ $part->safety_stock }}</td>
                        <td>
                            <a href="/parts/{{ $part->id }}/edit">修改</a>

                            <form method="POST" action="/parts/{{ $part->id }}" style="display:inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit">刪除</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>