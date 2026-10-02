<a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><i class="bi bi-speedometer2 me-2"></i>主控台</a>
@if (in_array(Auth::user()->role?->slug, ['admin', 'it_manager']))
    <a class="nav-link {{ request()->routeIs('classrooms.*') ? 'active' : '' }}" href="{{ route('classrooms.index') }}"><i class="bi bi-building me-2"></i>教室管理</a>
    <a class="nav-link {{ request()->routeIs('device-categories.*') ? 'active' : '' }}" href="{{ route('device-categories.index') }}"><i class="bi bi-tags me-2"></i>設備類別</a>
    <a class="nav-link {{ request()->routeIs('devices.*') ? 'active' : '' }}" href="{{ route('devices.index') }}"><i class="bi bi-pc-display me-2"></i>設備管理</a>
    <a class="nav-link {{ request()->routeIs('audit-logs.*') ? 'active' : '' }}" href="{{ route('audit-logs.index') }}"><i class="bi bi-clock-history me-2"></i>操作紀錄</a>
@endif
{{-- 以下連結等其他組員的模組合併進 develop 後會自動出現，不用回來改這個檔案 --}}
@if (Route::has('knowledge-base.index'))
    <a class="nav-link {{ request()->routeIs('knowledge-base.*') ? 'active' : '' }}" href="{{ route('knowledge-base.index') }}"><i class="bi bi-book me-2"></i>{{ __('common.nav.knowledge_base') }}</a>
@endif
@if (Route::has('repairs.index'))
    <a class="nav-link {{ request()->routeIs('repairs.*') ? 'active' : '' }}" href="{{ route('repairs.index') }}"><i class="bi bi-tools me-2"></i>{{ __('common.nav.repair_requests') }}</a>
@endif
@if (Route::has('maintenance.index'))
    <a class="nav-link {{ request()->routeIs('maintenance.*') ? 'active' : '' }}" href="{{ route('maintenance.index') }}"><i class="bi bi-wrench-adjustable me-2"></i>保養</a>
@endif
@if (Route::has('reservations.index'))
    <a class="nav-link {{ request()->routeIs('reservations.*') ? 'active' : '' }}" href="{{ route('reservations.index') }}"><i class="bi bi-calendar-check me-2"></i>空間預約</a>
@endif
@if (Route::has('inventory.index'))
    <a class="nav-link {{ request()->routeIs('inventory.*') ? 'active' : '' }}" href="{{ route('inventory.index') }}"><i class="bi bi-box-seam me-2"></i>庫存</a>
@endif
