<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>新增零件</title>
</head>
<body>
    <h1>新增零件</h1>

    <form method="POST" action="/parts">
        @csrf

        <div>
            <label>零件名稱：</label>
            <input type="text" name="name">
        </div>

        <div>
            <label>零件規格：</label>
            <input type="text" name="specification">
        </div>

        <div>
            <label>單價：</label>
            <input type="number" name="unit_price">
        </div>

        <div>
            <label>目前庫存：</label>
            <input type="number" name="current_stock">
        </div>

        <div>
            <label>安全庫存：</label>
            <input type="number" name="safety_stock">
        </div>

        <button type="submit">新增零件</button>

        @if ($errors->any())
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif
    </form>

    <p>
        <a href="/parts">回到零件列表</a>
    </p>
</body>
</html>