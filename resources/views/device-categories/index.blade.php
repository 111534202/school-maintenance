{{-- 設備類別主檔列表頁（對應 DeviceCategoryController::index）：名稱搜尋 + 資料表格 + 分頁。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 套用主版面。 --}}
@extends('layouts.app')

@section('title', '設備類別管理')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">設備類別管理</h3>
        <a href="{{ route('device-categories.create') }}" class="btn btn-primary">新增類別</a>
    </div>

    {{-- 搜尋表單：用 GET 送出，關鍵字會出現在網址上。 --}}
    <form method="GET" action="{{ route('device-categories.index') }}" class="row g-2 mb-3">
        <div class="col-8 col-md-4">
            <input type="text" name="keyword" class="form-control form-control-sm" placeholder="搜尋類別名稱" value="{{ request('keyword') }}">
        </div>
        <div class="col-4 col-md-2">
            <button type="submit" class="btn btn-sm btn-outline-secondary w-100">搜尋</button>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            {{-- 資料表格（有框線）。 --}}
            <table class="table table-bordered table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>類別名稱</th>
                        <th>使用中設備數</th>
                        <th class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- 逐筆列出類別；一筆都沒有時改顯示 @empty 的提示。 --}}
                    @forelse ($categories as $category)
                        <tr>
                            <td>{{ $category->name }}</td>
                            {{-- 這個類別底下有幾台設備（Controller 用 withCount 先算好）。 --}}
                            <td>{{ $category->devices_count }}</td>
                            <td class="text-end">
                                <a href="{{ route('device-categories.edit', $category) }}" class="btn btn-sm btn-outline-primary">編輯</a>
                                {{-- 刪除按鈕：送出前先確認；還有設備在用的類別，後端會擋下並說明。 --}}
                                <form method="POST" action="{{ route('device-categories.destroy', $category) }}" class="d-inline" onsubmit="return confirm('確定要刪除此類別嗎？');">
                                    @csrf
                                    {{-- 用隱藏欄位把 POST 偽裝成 DELETE。 --}}
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">刪除</button>
                                </form>
                            </td>
                        </tr>
                    {{-- 沒有任何資料時顯示的提示列。 --}}
                    @empty
                        <tr><td colspan="3" class="text-center text-muted py-4">尚無設備類別</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- 分頁按鈕。 --}}
    <div class="mt-3">{{ $categories->links() }}</div>
@endsection
