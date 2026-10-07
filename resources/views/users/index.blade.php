{{-- 用戶主檔列表頁（對應 UserController::index）：篩選列 + 資料表格 + 分頁。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 【想新增一欄】在下面表格的 <thead> 加一個 <th>、在 <tbody> 的 <tr> 裡加對應的 <td>，欄位名稱的中英文放 lang/各語言資料夾/users.php 的 table 區塊。 --}}
{{-- 套用主版面（導覽列、側邊選單都由它提供）。 --}}
@extends('layouts.app')

{{-- 瀏覽器分頁標題。 --}}
@section('title', __('users.index_title'))

{{-- 從這裡開始是「放進主版面中間」的內容，到 @endsection 結束。 --}}
@section('content')
    {{-- 頁面標題列：左邊標題、右邊「新增用戶」按鈕。 --}}
    <div class="page-header mb-4">
        <h1 class="h4 mb-0"><i class="bi bi-people me-2"></i>{{ __('users.index_title') }}</h1>
        <a class="btn btn-primary" href="{{ route('users.create') }}">
            <i class="bi bi-plus-lg me-1"></i>{{ __('users.add_user') }}
        </a>
    </div>

    {{-- 篩選列：全部放同一排、不換行（視窗太窄時整列左右捲動），按鈕一律用圖示。 --}}
    <div class="card shadow-sm mb-3">
        <div class="card-body py-3">
            {{-- 篩選表單：用 GET 送出，條件會出現在網址上（例如 ?role_id=3），所以可以被書籤、圖表連結直接帶入。 --}}
            <form method="GET" action="{{ route('users.index') }}" class="filter-bar">
                <div>
                    {{-- 關鍵字（帳號／姓名／Email／電話）。request('keyword') 會把目前網址上的條件帶回輸入框。 --}}
                    <label for="keyword" class="form-label small mb-1">{{ __('users.filter.keyword') }}</label>
                    <input type="text" id="keyword" name="keyword" class="form-control form-control-sm" style="width: 15rem;"
                        value="{{ request('keyword') }}" placeholder="{{ __('users.filter.keyword_placeholder') }}">
                </div>
                <div>
                    {{-- 身分下拉選單：選項來自身分主檔（Controller 傳進來的 $roles）。 --}}
                    <label for="role_id" class="form-label small mb-1">{{ __('users.filter.role') }}</label>
                    <select id="role_id" name="role_id" class="form-select form-select-sm" style="width: 11rem;">
                        <option value="">{{ __('users.filter.role_all') }}</option>
                        @foreach ($roles as $role)
                            {{-- @selected：這個選項和網址上的條件相同就標記為「已選取」（兩邊都轉成文字再比較）。 --}}
                            <option value="{{ $role->id }}" @selected((string) request('role_id') === (string) $role->id)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    {{-- 部門下拉選單：選項來自部門主檔。 --}}
                    <label for="department_id" class="form-label small mb-1">{{ __('users.filter.department') }}</label>
                    <select id="department_id" name="department_id" class="form-select form-select-sm" style="width: 10rem;">
                        <option value="">{{ __('users.filter.department_all') }}</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected((string) request('department_id') === (string) $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    {{-- 狀態下拉選單：啟用中／已停用／已刪除。 --}}
                    <label for="status" class="form-label small mb-1">{{ __('users.filter.status') }}</label>
                    <select id="status" name="status" class="form-select form-select-sm" style="width: 8rem;">
                        <option value="">{{ __('users.filter.status_all') }}</option>
                        @foreach (['active', 'inactive', 'deleted'] as $statusOption)
                            <option value="{{ $statusOption }}" @selected(request('status') === $statusOption)>{{ __('users.status.' . $statusOption) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="d-inline-flex gap-1">
                    {{-- 篩選按鈕（漏斗圖示）。全站規則：按鈕一律用 Bootstrap Icons 圖示，不用 emoji。 --}}
                    <button class="btn btn-primary btn-sm icon-btn" type="submit"
                        title="{{ __('common.buttons.filter') }}" aria-label="{{ __('common.buttons.filter') }}"><i class="bi bi-funnel"></i></button>
                    {{-- 有套用任何篩選條件時，才顯示「清除篩選」按鈕。 --}}
                    @if (request('keyword') || request('role_id') || request('department_id') || request('status'))
                        <a class="btn btn-outline-secondary btn-sm icon-btn" href="{{ route('users.index') }}"
                            title="{{ __('common.buttons.clear_filter') }}" aria-label="{{ __('common.buttons.clear_filter') }}"><i class="bi bi-x-circle"></i></a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- 查不到任何用戶時顯示提示；否則顯示表格。 --}}
    @if ($users->isEmpty())
        <div class="alert alert-info mb-0">{{ __('users.empty_list') }}</div>
    @else
        <div class="card shadow-sm">
            <div class="table-responsive">
                {{-- 資料表格：table-bordered 有框線、table-hover 滑鼠移上去會變色（全站表格都要有框線）。 --}}
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('users.table.username') }}</th>
                            <th>{{ __('users.table.name') }}</th>
                            <th>{{ __('users.table.email') }}</th>
                            <th>{{ __('users.table.phone') }}</th>
                            <th>{{ __('users.table.role') }}</th>
                            <th>{{ __('users.table.department') }}</th>
                            <th class="text-center">{{ __('users.table.status') }}</th>
                            <th>{{ __('users.table.last_login_at') }}</th>
                            <th class="text-center">{{ __('users.table.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- 逐一列出每位用戶，每個用戶一列 <tr>。 --}}
                        @foreach ($users as $user)
                            {{-- 先算出「這一列是不是目前登入的自己」，等下用來停用自己的刪除／停用按鈕。 --}}
                            @php($isSelf = $user->id === auth()->id())
                            {{-- 已刪除（軟刪除）的用戶整列用灰色背景標示。 --}}
                            <tr class="{{ $user->trashed() ? 'table-secondary' : '' }}">
                                <td>
                                    <span class="fw-semibold">{{ $user->username ?? '—' }}</span>
                                    {{-- 標出「這是你自己」的徽章。 --}}
                                    @if ($isSelf)
                                        <span class="badge text-bg-info ms-1">{{ __('users.current_user_badge') }}</span>
                                    @endif
                                </td>
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td>{{ $user->phone ?? '—' }}</td>
                                {{-- 身分名稱；沒有身分時顯示破折號。 --}}
                                <td><span class="badge text-bg-primary">{{ $user->role->name ?? '—' }}</span></td>
                                <td>{{ $user->department->name ?? __('users.no_department') }}</td>
                                <td class="text-center">
                                    @if ($user->trashed())
                                        <span class="badge text-bg-danger">{{ __('users.status.deleted') }}</span>
                                    @elseif ($user->is_active)
                                        <span class="badge text-bg-success">{{ __('users.status.active') }}</span>
                                    @else
                                        <span class="badge text-bg-secondary">{{ __('users.status.inactive') }}</span>
                                    @endif
                                </td>
                                {{-- 最後登入時間（已依台北時區儲存）；從未登入過就顯示「從未登入」。 --}}
                                <td>{{ $user->last_login_at?->format('Y-m-d H:i') ?? __('users.never_logged_in') }}</td>
                                <td class="text-center">
                                    <div class="d-inline-flex gap-1">
                                        @if ($user->trashed())
                                            {{-- 已刪除的用戶只提供「還原」按鈕。onsubmit 的 confirm 是送出前的確認視窗。 --}}
                                            <form method="POST" action="{{ route('users.restore', $user) }}"
                                                onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('users.confirm.restore', ['name' => $user->name])) }});">
                                                @csrf
                                                {{-- 瀏覽器的表單只能 GET/POST，@method 用隱藏欄位偽裝成 PATCH / PUT / DELETE。 --}}
                                                @method('PATCH')
                                                <button class="btn btn-outline-success btn-sm icon-btn" type="submit"
                                                    title="{{ __('users.actions.restore') }}" aria-label="{{ __('users.actions.restore') }}"><i class="bi bi-arrow-counterclockwise"></i></button>
                                            </form>
                                        @else
                                            {{-- 編輯按鈕（鉛筆圖示）。 --}}
                                            <a class="btn btn-outline-primary btn-sm icon-btn" href="{{ route('users.edit', $user) }}"
                                                title="{{ __('common.buttons.edit') }}" aria-label="{{ __('common.buttons.edit') }}"><i class="bi bi-pencil"></i></a>

                                            {{-- 啟用／停用切換按鈕：目前啟用中才會跳出確認視窗；自己不能停用自己（@disabled）。 --}}
                                            <form method="POST" action="{{ route('users.toggle', $user) }}"
                                                @if ($user->is_active) onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('users.confirm.deactivate', ['name' => $user->name])) }});" @endif>
                                                @csrf
                                                @method('PATCH')
                                                <button class="btn btn-outline-warning btn-sm icon-btn" type="submit" @disabled($isSelf)
                                                    title="{{ $user->is_active ? __('users.actions.deactivate') : __('users.actions.activate') }}"
                                                    aria-label="{{ $user->is_active ? __('users.actions.deactivate') : __('users.actions.activate') }}">
                                                    <i class="bi {{ $user->is_active ? 'bi-person-slash' : 'bi-person-check' }}"></i>
                                                </button>
                                            </form>

                                            {{-- 刪除按鈕（垃圾桶圖示）：送出前確認；自己不能刪除自己。 --}}
                                            <form method="POST" action="{{ route('users.destroy', $user) }}"
                                                onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('users.confirm.delete', ['name' => $user->name])) }});">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-outline-danger btn-sm icon-btn" type="submit" @disabled($isSelf)
                                                    title="{{ __('common.buttons.delete') }}" aria-label="{{ __('common.buttons.delete') }}"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-3">
            {{-- 分頁按鈕（上一頁／下一頁／頁碼），樣式在 AppServiceProvider 設定成 Bootstrap。 --}}
            {{ $users->links() }}
        </div>
    @endif
@endsection
