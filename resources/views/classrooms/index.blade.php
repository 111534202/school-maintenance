{{-- 教室主檔列表頁（對應 ClassroomController::index）：關鍵字、部門、啟用狀態篩選 + 資料表格 + 分頁。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 套用主版面（導覽列、側邊選單都由它提供）。 --}}
@extends('layouts.app')

@section('title', '教室主檔')

{{-- 從這裡開始是「放進主版面中間」的內容，到 @endsection 結束。 --}}
@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">教室主檔</h3>
        <a href="{{ route('classrooms.create') }}" class="btn btn-primary">新增教室</a>
    </div>

    {{-- 篩選表單：用 GET 送出，條件會出現在網址上；request('欄位') 把目前的條件帶回輸入框。 --}}
    <form method="GET" action="{{ route('classrooms.index') }}" class="row g-2 mb-3">
        <div class="col-6 col-md-4">
            <input type="text" name="keyword" class="form-control form-control-sm" placeholder="教室代碼/名稱" value="{{ request('keyword') }}">
        </div>
        <div class="col-6 col-md-3">
            <select name="department_id" class="form-select form-select-sm">
                <option value="">所有部門</option>
                {{-- 部門下拉選單的選項（Controller 傳進來的全部部門）。 --}}
                @foreach ($departments as $department)
                    {{-- @selected：這個選項和網址上的條件相同就標記為已選取。 --}}
                    <option value="{{ $department->id }}" @selected(request('department_id') == $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-3">
            <select name="is_active" class="form-select form-select-sm">
                <option value="">所有狀態</option>
                <option value="1" @selected(request('is_active') === '1')>啟用中</option>
                <option value="0" @selected(request('is_active') === '0')>已停用</option>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <button type="submit" class="btn btn-sm btn-outline-secondary w-100">篩選</button>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            {{-- 資料表格（有框線）。 --}}
            <table class="table table-bordered table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>教室代碼</th>
                        <th>教室名稱</th>
                        <th>所屬部門</th>
                        <th>位置</th>
                        <th>管理人</th>
                        <th>狀態</th>
                        <th class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- @forelse：逐筆列出教室；一筆都沒有時改顯示下面 @empty 的「尚無教室資料」。 --}}
                    @forelse ($classrooms as $classroom)
                        <tr>
                            <td>{{ $classroom->room_code }}</td>
                            <td>{{ $classroom->room_name }}</td>
                            <td>{{ $classroom->department->name ?? '－' }}</td>
                            <td>{{ $classroom->campus }} / {{ $classroom->building }} / {{ $classroom->floor }}</td>
                            <td>{{ $classroom->manager->name ?? '－' }}</td>
                            <td>
                                {{-- 啟用狀態徽章：綠色「啟用中」或灰色「已停用」。 --}}
                                @if ($classroom->is_active)
                                    <span class="badge bg-success">啟用中</span>
                                @else
                                    <span class="badge bg-secondary">已停用</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('classrooms.edit', $classroom) }}" class="btn btn-sm btn-outline-primary">編輯</a>
                                {{-- 啟用／停用切換按鈕（教室不提供刪除，只能停用）。 --}}
                                <form method="POST" action="{{ route('classrooms.toggle', $classroom) }}" class="d-inline">
                                    @csrf
                                    {{-- 用隱藏欄位把 POST 偽裝成 PATCH。 --}}
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                                        {{ $classroom->is_active ? '停用' : '啟用' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    {{-- 沒有任何資料時顯示的一列提示（colspan=7 讓它橫跨全部 7 欄）。 --}}
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">尚無教室資料</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- 分頁按鈕。 --}}
    <div class="mt-3">{{ $classrooms->links() }}</div>
@endsection
