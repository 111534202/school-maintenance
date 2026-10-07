{{-- 選單連結內容（同時被手機版抽屜與桌面版側邊欄引入，所以寫成獨立檔案）。 --}}
{{-- 每個連結都用 @can('權限') 包起來：沒有權限的人看不到該連結，而且就算手動輸入網址，路由的 can: 也會擋下來。 --}}
{{-- 想新增選單項目：照下面的格式加一個 <a class="nav-link ...">；主檔類放進「主檔」群組，並把它的權限加進 $masterPermissions。 --}}
@php
    // $navId：同一個選單會在桌面側邊欄與手機抽屜各畫一次，收合區塊的 id 不能重複，所以由外面傳不同的名字進來。
    // navId 由外面傳進來，沒傳就用 nav。
    $navId = $navId ?? 'nav';
    // 屬於「主檔」群組的權限清單：只要有其中任何一個，就會顯示「主檔」群組。
    $masterPermissions = ['users.manage', 'roles.manage', 'departments.manage', 'classrooms.manage', 'device-categories.manage', 'devices.manage'];
    // 目前就在某個主檔頁面時，主檔群組預設展開，不然使用者會找不到自己在哪一頁。
    // 目前所在頁面是不是主檔頁面（routeIs 比對路由名稱）；是的話群組預設展開。
    $masterOpen = request()->routeIs('users.*', 'roles.*', 'departments.*', 'classrooms.*', 'device-categories.*', 'devices.*');
@endphp

<a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><i class="bi bi-speedometer2 me-2"></i>{{ __('common.nav.dashboard') }}</a>

{{-- 以下連結都依「身分主檔」勾選的權限顯示，沒有權限的人看不到，直接輸入網址也進不去（路由有 can: 保護）。 --}}

{{-- 主檔：用戶、身分、部門、教室、設備類別、設備全部收進同一個可展開的群組。 --}}
{{-- @canany：只要有其中任何一個權限就顯示（整個「主檔」群組）。 --}}
@canany($masterPermissions)
    <a class="nav-link nav-group-toggle d-flex justify-content-between align-items-center {{ $masterOpen ? '' : 'collapsed' }}"
        {{-- 點這個連結會展開／收合 href 指向的區塊（Bootstrap 的 collapse 功能）。 --}}
        data-bs-toggle="collapse" href="#{{ $navId }}-master" role="button"
        aria-expanded="{{ $masterOpen ? 'true' : 'false' }}" aria-controls="{{ $navId }}-master">
        <span><i class="bi bi-database me-2"></i>{{ __('common.nav.master_section') }}</span>
        <i class="bi bi-chevron-down small nav-chevron"></i>
    </a>
    {{-- 可收合的區塊本體：show 代表預設展開。 --}}
    <div class="collapse {{ $masterOpen ? 'show' : '' }}" id="{{ $navId }}-master">
        <div class="nav flex-column ms-3 ps-2 border-start">
            {{-- 用戶主檔連結：需要 users.manage 權限。 --}}
            @can('users.manage')
                <a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}"><i class="bi bi-people me-2"></i>{{ __('common.nav.users') }}</a>
            @endcan
            {{-- 身分主檔連結。 --}}
            @can('roles.manage')
                <a class="nav-link {{ request()->routeIs('roles.*') ? 'active' : '' }}" href="{{ route('roles.index') }}"><i class="bi bi-shield-lock me-2"></i>{{ __('common.nav.roles') }}</a>
            @endcan
            {{-- 部門主檔連結。 --}}
            @can('departments.manage')
                <a class="nav-link {{ request()->routeIs('departments.*') ? 'active' : '' }}" href="{{ route('departments.index') }}"><i class="bi bi-diagram-3 me-2"></i>{{ __('common.nav.departments') }}</a>
            @endcan
            {{-- 教室主檔連結。 --}}
            @can('classrooms.manage')
                <a class="nav-link {{ request()->routeIs('classrooms.*') ? 'active' : '' }}" href="{{ route('classrooms.index') }}"><i class="bi bi-building me-2"></i>{{ __('common.nav.classrooms') }}</a>
            @endcan
            {{-- 設備類別連結。 --}}
            @can('device-categories.manage')
                <a class="nav-link {{ request()->routeIs('device-categories.*') ? 'active' : '' }}" href="{{ route('device-categories.index') }}"><i class="bi bi-tags me-2"></i>{{ __('common.nav.device_categories') }}</a>
            @endcan
            {{-- 設備主檔連結。 --}}
            @can('devices.manage')
                <a class="nav-link {{ request()->routeIs('devices.*') ? 'active' : '' }}" href="{{ route('devices.index') }}"><i class="bi bi-pc-display me-2"></i>{{ __('common.nav.devices') }}</a>
            @endcan
        </div>
    </div>
@endcanany

{{-- 「業務功能」小標題。 --}}
<div class="text-uppercase text-muted small fw-semibold px-3 pt-3 pb-1">{{ __('common.nav.business_section') }}</div>
{{-- 以下連結等其他組員的模組合併進 develop 後會自動出現，不用回來改這個檔案 --}}
{{-- Route::has：該路由存在才顯示連結，其他組員的模組尚未合併時不會出現壞連結。 --}}
@if (Route::has('knowledge-base.index'))
    <a class="nav-link {{ request()->routeIs('knowledge-base.*') ? 'active' : '' }}" href="{{ route('knowledge-base.index') }}"><i class="bi bi-book me-2"></i>{{ __('common.nav.knowledge_base') }}</a>
@endif
@if (Route::has('repairs.index'))
    <a class="nav-link {{ request()->routeIs('repairs.*') ? 'active' : '' }}" href="{{ route('repairs.index') }}"><i class="bi bi-tools me-2"></i>{{ __('common.nav.repair_requests') }}</a>
@endif
@if (Route::has('maintenance-items.index'))
    {{-- 保養模組（王佑恩）：保養項目、計畫、工單、設備履歷；AI 預防保養僅管理端可見。 --}}
    <a class="nav-link {{ request()->routeIs('maintenance-items.*') ? 'active' : '' }}" href="{{ route('maintenance-items.index') }}"><i class="bi bi-list-check me-2"></i>保養項目</a>
    <a class="nav-link {{ request()->routeIs('maintenance-plans.*') ? 'active' : '' }}" href="{{ route('maintenance-plans.index') }}"><i class="bi bi-calendar-event me-2"></i>保養計畫</a>
    <a class="nav-link {{ request()->routeIs('maintenance-orders.*') ? 'active' : '' }}" href="{{ route('maintenance-orders.index') }}"><i class="bi bi-wrench-adjustable me-2"></i>保養工單</a>
    <a class="nav-link {{ request()->routeIs('device-profile.*') ? 'active' : '' }}" href="{{ route('device-profile.index') }}"><i class="bi bi-journal-text me-2"></i>設備履歷</a>
    @if (in_array(Auth::user()->role?->slug, ['admin', 'it_manager']))
        <a class="nav-link {{ request()->routeIs('preventive-candidates.*', 'ai-settings.*') ? 'active' : '' }}" href="{{ route('preventive-candidates.index') }}"><i class="bi bi-robot me-2"></i>AI 預防保養</a>
    @endif
@endif
@if (Route::has('reservations.index'))
    <a class="nav-link {{ request()->routeIs('reservations.*') ? 'active' : '' }}" href="{{ route('reservations.index') }}"><i class="bi bi-calendar-check me-2"></i>空間預約</a>
@endif
@if (Route::has('inventory.index'))
    <a class="nav-link {{ request()->routeIs('inventory.*') ? 'active' : '' }}" href="{{ route('inventory.index') }}"><i class="bi bi-box-seam me-2"></i>庫存</a>
@endif

@can('audit-logs.view')
    <div class="text-uppercase text-muted small fw-semibold px-3 pt-3 pb-1">{{ __('common.nav.system_section') }}</div>
    <a class="nav-link {{ request()->routeIs('audit-logs.*') ? 'active' : '' }}" href="{{ route('audit-logs.index') }}"><i class="bi bi-clock-history me-2"></i>{{ __('common.nav.audit_logs') }}</a>
@endcan
