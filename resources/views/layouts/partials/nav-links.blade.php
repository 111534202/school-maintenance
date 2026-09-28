<a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">主控台</a>
@if (in_array(Auth::user()->role?->slug, ['admin', 'it_manager']))
    <a class="nav-link {{ request()->routeIs('classrooms.*') ? 'active' : '' }}" href="{{ route('classrooms.index') }}">教室管理</a>
    <a class="nav-link {{ request()->routeIs('device-categories.*') ? 'active' : '' }}" href="{{ route('device-categories.index') }}">設備類別</a>
    <a class="nav-link {{ request()->routeIs('devices.*') ? 'active' : '' }}" href="{{ route('devices.index') }}">設備管理</a>
    <a class="nav-link {{ request()->routeIs('audit-logs.*') ? 'active' : '' }}" href="{{ route('audit-logs.index') }}">操作紀錄</a>
@endif
{{-- 以下連結等其他組員的模組合併進 develop 後會自動出現，不用回來改這個檔案 --}}
@if (Route::has('repairs.index'))
    <a class="nav-link {{ request()->routeIs('repairs.*') ? 'active' : '' }}" href="{{ route('repairs.index') }}">報修</a>
@endif
@if (Route::has('maintenance.index'))
    <a class="nav-link {{ request()->routeIs('maintenance.*') ? 'active' : '' }}" href="{{ route('maintenance.index') }}">保養</a>
@endif
@if (Route::has('reservations.index'))
    <a class="nav-link {{ request()->routeIs('reservations.*') ? 'active' : '' }}" href="{{ route('reservations.index') }}">空間預約</a>
@endif
@if (Route::has('inventory.index'))
    <a class="nav-link {{ request()->routeIs('inventory.*') ? 'active' : '' }}" href="{{ route('inventory.index') }}">庫存</a>
@endif
