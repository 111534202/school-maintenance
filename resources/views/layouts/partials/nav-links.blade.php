<a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">主控台</a>
@if (in_array(Auth::user()->role?->slug, ['admin', 'it_manager']))
    <a class="nav-link {{ request()->routeIs('classrooms.*') ? 'active' : '' }}" href="{{ route('classrooms.index') }}">教室管理</a>
    <a class="nav-link {{ request()->routeIs('device-categories.*') ? 'active' : '' }}" href="{{ route('device-categories.index') }}">設備類別</a>
    <a class="nav-link {{ request()->routeIs('devices.*') ? 'active' : '' }}" href="{{ route('devices.index') }}">設備管理</a>
    <a class="nav-link {{ request()->routeIs('audit-logs.*') ? 'active' : '' }}" href="{{ route('audit-logs.index') }}">操作紀錄</a>
@endif
{{-- 保養模組（王佑恩主責）：原本這裡留的是單一 Route::has('maintenance.index') 預留位，
     但實際路由拆成三組（items/plans/orders），改成直接列出三個連結。 --}}
<a class="nav-link {{ request()->routeIs('maintenance-items.*') ? 'active' : '' }}" href="{{ route('maintenance-items.index') }}">保養項目</a>
<a class="nav-link {{ request()->routeIs('maintenance-plans.*') ? 'active' : '' }}" href="{{ route('maintenance-plans.index') }}">保養計畫</a>
<a class="nav-link {{ request()->routeIs('maintenance-orders.*') ? 'active' : '' }}" href="{{ route('maintenance-orders.index') }}">保養工單</a>
<a class="nav-link {{ request()->routeIs('device-profile.*') ? 'active' : '' }}" href="{{ route('device-profile.index') }}">設備履歷</a>
@if (in_array(Auth::user()->role?->slug, ['admin', 'it_manager']))
    <a class="nav-link {{ request()->routeIs('preventive-candidates.*', 'ai-settings.*') ? 'active' : '' }}" href="{{ route('preventive-candidates.index') }}">AI 預防保養</a>
@endif
{{-- 以下連結等其他組員的模組合併進 develop 後會自動出現，不用回來改這個檔案 --}}
@if (Route::has('repairs.index'))
    <a class="nav-link {{ request()->routeIs('repairs.*') ? 'active' : '' }}" href="{{ route('repairs.index') }}">報修</a>
@endif
@if (Route::has('reservations.index'))
    <a class="nav-link {{ request()->routeIs('reservations.*') ? 'active' : '' }}" href="{{ route('reservations.index') }}">空間預約</a>
@endif
@if (Route::has('inventory.index'))
    <a class="nav-link {{ request()->routeIs('inventory.*') ? 'active' : '' }}" href="{{ route('inventory.index') }}">庫存</a>
@endif
