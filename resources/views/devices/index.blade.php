{{-- 設備主檔列表頁（對應 DeviceController::index）：關鍵字、教室、類別、狀態篩選 + 資料表格 + 分頁。 --}}
{{-- 主控台的「設備狀態圖」點擊會帶 ?status=xxx 進到這一頁。（Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 套用主版面（導覽列、側邊選單都由它提供）。 --}}
@extends('layouts.app')

@section('title', '設備主檔')

{{-- 從這裡開始是「放進主版面中間」的內容，到 @endsection 結束。 --}}
@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">設備主檔</h3>
        <a href="{{ route('devices.create') }}" class="btn btn-primary">新增設備</a>
    </div>

    {{-- 篩選表單：用 GET 送出，條件會出現在網址上。 --}}
    <form method="GET" action="{{ route('devices.index') }}" class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <input type="text" name="keyword" class="form-control form-control-sm" placeholder="設備編號/資產編號/品牌/型號" value="{{ request('keyword') }}">
        </div>
        <div class="col-6 col-md-3">
            <select name="classroom_id" class="form-select form-select-sm">
                <option value="">所有教室</option>
                {{-- 教室下拉選單的選項。 --}}
                @foreach ($classrooms as $classroom)
                    <option value="{{ $classroom->id }}" @selected(request('classroom_id') == $classroom->id)>{{ $classroom->room_code }} - {{ $classroom->room_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-3">
            <select name="device_category_id" class="form-select form-select-sm">
                <option value="">所有類別</option>
                {{-- 設備類別下拉選單的選項。 --}}
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(request('device_category_id') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <select name="status" class="form-select form-select-sm">
                <option value="">所有狀態</option>
                {{-- 狀態下拉選單的選項：列出 Device::STATUSES 的每個狀態，顯示名稱用 Device::statusLabel() 轉成中文。 --}}
                @foreach (\App\Models\Device::STATUSES as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ \App\Models\Device::statusLabel($status) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-1">
            <button type="submit" class="btn btn-sm btn-outline-secondary w-100">篩選</button>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            {{-- 資料表格（有框線）。 --}}
            <table class="table table-bordered table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>設備編號</th>
                        <th>類別</th>
                        <th>品牌/型號</th>
                        <th>所在教室</th>
                        <th>狀態</th>
                        <th>核心設備</th>
                        <th class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- 逐筆列出設備；一筆都沒有時改顯示 @empty 的提示。 --}}
                    @forelse ($devices as $device)
                        <tr>
                            <td><a href="{{ route('devices.show', $device) }}">{{ $device->device_code }}</a></td>
                            <td>{{ $device->category->name ?? '－' }}</td>
                            <td>{{ $device->brand }} {{ $device->model }}</td>
                            <td>{{ $device->classroom->room_name ?? '－' }}</td>
                            <td><span class="badge bg-info text-dark">{{ \App\Models\Device::statusLabel($device->status) }}</span></td>
                            <td>
                                {{-- 核心設備才顯示「核心」徽章。 --}}
                                @if ($device->is_core)
                                    <span class="badge bg-warning text-dark">核心</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('devices.show', $device) }}" class="btn btn-sm btn-outline-secondary">詳細</a>
                                <a href="{{ route('devices.edit', $device) }}" class="btn btn-sm btn-outline-primary">編輯</a>
                                {{-- 停用按鈕：送出前先跳出確認視窗；停用 = 狀態改 disabled 並軟刪除。 --}}
                                <form method="POST" action="{{ route('devices.disable', $device) }}" class="d-inline" onsubmit="return confirm('確定要停用此設備嗎？');">
                                    @csrf
                                    {{-- 用隱藏欄位把 POST 偽裝成 PATCH。 --}}
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">停用</button>
                                </form>
                            </td>
                        </tr>
                    {{-- 沒有任何資料時顯示的提示列。 --}}
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">尚無符合條件的設備</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- 分頁按鈕。 --}}
    <div class="mt-3">{{ $devices->links() }}</div>
@endsection
