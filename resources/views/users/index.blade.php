{{-- 用戶主檔列表頁（對應 UserController::index）：篩選列 + 資料表格 + 分頁。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 【想新增一欄】在下面表格的 <thead> 加一個 <th>、在 <tbody> 的 <tr> 裡加對應的 <td>，欄位名稱的中英文放 lang/各語言資料夾/users.php 的 table 區塊。 --}}
{{-- 套用主版面（導覽列、側邊選單都由它提供）。 --}}
@extends('layouts.app')

{{-- 瀏覽器分頁標題。 --}}
@section('title', __('users.index_title'))

{{-- 從這裡開始是「放進主版面中間」的內容，到 @endsection 結束。 --}}
@section('content')
<div class="tb">
    {{-- 頁面標題列：左邊標題、右邊「新增用戶」按鈕。 --}}
    <div class="tb-header">
        <div>
            <h1 class="tb-title">{{ __('users.index_title') }}</h1>
        </div>
        <div class="d-flex gap-2">
            @include('charts._button', ['buttonClass' => 'tb-btn tb-btn-outline'])
            <a class="tb-btn tb-btn-primary" href="{{ route('users.create') }}" data-modal-frame data-modal-title="{{ __('users.add_user') }}" data-modal-size="lg">
                <i class="bi bi-plus-lg"></i>{{ __('users.add_user') }}
            </a>
        </div>
    </div>

    {{-- 篩選列：直接顯示在頁面上（data-no-onion），放得下就排成一列，不夠寬時換行（tb-filter，不用左右捲動），按鈕一律用圖示。 --}}
    <div class="tb-card tb-card-pad">
        <div>
            {{-- 篩選表單：用 GET 送出，條件會出現在網址上（例如 ?role_id=3），所以可以被書籤、圖表連結直接帶入。 --}}
            <form method="GET" action="{{ route('users.index') }}" class="filter-bar tb-form-row tb-filter" data-no-onion>
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
                @include('layouts.partials.filter-actions', ['reset' => route('users.index'), 'fields' => ['keyword', 'role_id', 'department_id', 'status']])
            </form>
        </div>
    </div>

    {{-- 查不到任何用戶時顯示提示；否則顯示表格。 --}}
    @if ($users->isEmpty())
        <div class="tb-card tb-empty">{{ __('users.empty_list') }}</div>
    @else
        <section class="tb-table-wrap" data-no-fit>
            <div class="table-responsive">
                {{-- 資料表格（風格 B）：螢幕夠大用表格、不夠用清單（全站自動切換）；整頁一起捲動，表頭固定在頂端列下方（data-no-fit）。 --}}
                <table class="tb-table tb-table-hover" data-onion-primary="1,4">
                    <thead>
                        <tr>
                            <th>{{ __('users.table.account') }}</th>
                            <th>{{ __('users.table.name') }}</th>
                            <th>{{ __('users.table.email') }}</th>
                            <th>{{ __('users.table.phone') }}</th>
                            <th>{{ __('users.table.role') }}</th>
                            <th>{{ __('users.table.department') }}</th>
                            <th class="text-center">{{ __('users.table.status') }}</th>
                            <th>{{ __('users.table.last_login_at') }}</th>
                            <th class="text-end tb-col-fit">{{ __('users.table.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- 逐一列出每位用戶，每個用戶一列 <tr>。 --}}
                        @foreach ($users as $user)
                            {{-- 先算出「這一列是不是目前登入的自己」，等下用來停用自己的刪除／停用按鈕。 --}}
                            @php($isSelf = $user->id === auth()->id())
                            {{-- 已刪除（軟刪除）的用戶整列用灰色背景標示。 --}}
                            {{-- 點這一列的任何地方都會在畫面中的對話框打開編輯頁（data-modal-frame-href，行為在共用版型）；已刪除的用戶只能還原，沒有編輯頁。 --}}
                            <tr class="{{ $user->trashed() ? 'tb-row-muted' : '' }}" @unless ($user->trashed()) data-modal-frame-href="{{ route('users.edit', $user) }}" data-modal-title="修改用戶" data-modal-size="lg" tabindex="0" role="link" @endunless>
                                <td>
                                    <span class="fw-semibold">{{ $user->account ?? '—' }}</span>
                                    {{-- 標出「這是你自己」的徽章。 --}}
                                    @if ($isSelf)
                                        <span class="badge tb-pill tb-pill-info ms-1">{{ __('users.current_user_badge') }}</span>
                                    @endif
                                </td>
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td>{{ $user->phone ?? '—' }}</td>
                                {{-- 身分名稱；沒有身分時顯示破折號。 --}}
                                <td><span class="badge tb-pill tb-pill-info">{{ $user->role->name ?? '—' }}</span></td>
                                <td>{{ $user->department->name ?? __('users.no_department') }}</td>
                                <td class="text-center">
                                    @if ($user->trashed())
                                        <span class="badge tb-pill tb-pill-danger">{{ __('users.status.deleted') }}</span>
                                    @elseif ($user->is_active)
                                        <span class="badge tb-pill tb-pill-ok">{{ __('users.status.active') }}</span>
                                    @else
                                        <span class="badge tb-pill tb-pill-muted">{{ __('users.status.inactive') }}</span>
                                    @endif
                                </td>
                                {{-- 最後登入時間（已依台北時區儲存）；從未登入過就顯示「從未登入」。 --}}
                                <td>{{ $user->last_login_at?->format('Y-m-d H:i') ?? __('users.never_logged_in') }}</td>
                                <td class="text-end tb-col-fit">
                                    <div class="d-inline-flex gap-1">
                                        @if ($user->trashed())
                                            {{-- 已刪除的用戶提供「還原」與「永久刪除」兩個按鈕。data-confirm：送出前先跳出全站共用的確認視窗（跟刪除一樣）。 --}}
                                            <form method="POST" action="{{ route('users.restore', $user) }}"
                                                data-confirm="{{ __('users.confirm.restore', ['name' => $user->name]) }}" data-confirm-ok="確認還原" data-confirm-variant="primary">
                                                @csrf
                                                {{-- 瀏覽器的表單只能 GET/POST，@method 用隱藏欄位偽裝成 PATCH / PUT / DELETE。 --}}
                                                @method('PATCH')
                                                <button class="btn btn-success btn-sm icon-btn" type="submit"
                                                    title="{{ __('users.actions.restore') }}" aria-label="{{ __('users.actions.restore') }}"><i class="bi bi-arrow-counterclockwise"></i></button>
                                            </form>
                                            {{-- 永久刪除：真的從資料庫移除、無法還原，所以用實心紅色按鈕跟其他操作區隔開，並且用全站共用的二次確認視窗（data-confirm）。 --}}
                                            <form method="POST" action="{{ route('users.force-destroy', $user) }}"
                                                data-confirm="{{ __('users.confirm.force_delete', ['name' => $user->name]) }}"
                                                data-confirm-ok="{{ __('users.actions.force_delete') }}">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-danger btn-sm icon-btn" type="submit"
                                                    title="{{ __('users.actions.force_delete') }}" aria-label="{{ __('users.actions.force_delete') }}"><i class="bi bi-trash3-fill"></i></button>
                                            </form>
                                        @else
                                            {{-- 編輯按鈕（鉛筆圖示）。 --}}
                                            <a class="btn btn-outline-primary btn-sm icon-btn" href="{{ route('users.edit', $user) }}" data-modal-frame data-modal-title="修改用戶" data-modal-size="lg"
                                                title="{{ __('common.buttons.edit') }}" aria-label="{{ __('common.buttons.edit') }}"><i class="bi bi-pencil"></i></a>

                                            {{-- 啟用／停用切換按鈕：目前啟用中才會跳出確認視窗（全站共用的彈出視窗）；自己不能停用自己（@disabled）。 --}}
                                            <form method="POST" action="{{ route('users.toggle', $user) }}"
                                                @if ($user->is_active) data-confirm="{{ __('users.confirm.deactivate', ['name' => $user->name]) }}" data-confirm-ok="確認停用" @endif>
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
                                                data-confirm="{{ __('users.confirm.delete', ['name' => $user->name]) }}">
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
        </section>

        <div>
            {{-- 分頁按鈕（上一頁／下一頁／頁碼），樣式在 AppServiceProvider 設定成 Bootstrap。 --}}
            @include('layouts.pager', ['paginator' => $users])
        </div>
    @endif
    {{-- 統計圖表視窗（資料來自 App\Services\MasterCharts）。 --}}
    @include('charts._modal')
</div>
@endsection
