<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>修改零件</title>
</head>
<body>
    <h1>修改零件</h1>

    @if ($errors->any())
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="/parts/{{ $part->id }}">
        @csrf
        @method('PUT')

        <div>
            <label>零件名稱：</label>
            <input type="text" name="name" value="{{ $part->name }}">
        </div>

        <div>
            <label>零件規格：</label>
            <input type="text" name="specification" value="{{ $part->specification }}">
        </div>

        <div>
            <label>單價：</label>
            <input type="number" name="unit_price" value="{{ $part->unit_price }}">
        </div>

        <div>
            <label>目前庫存：</label>
            <input type="number" name="current_stock" value="{{ $part->current_stock }}">
        </div>

        <div>
            <label>安全庫存：</label>
            <input type="number" name="safety_stock" value="{{ $part->safety_stock }}">
        </div>

        <button type="submit">儲存修改</button>
    </form>

    <p>
        <a href="/parts">回到零件列表</a>
    </p>
</body>
</html>