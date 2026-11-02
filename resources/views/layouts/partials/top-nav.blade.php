{{-- 上方導覽列裡的選單（風格 B）：寬螢幕（1200px 以上）直接顯示在墨綠導覽列上，每個區塊是一個下拉選單；
     比較窄的螢幕改用左上角漢堡按鈕打開的抽屜（layouts.partials.nav-links），兩份選單的項目與權限相同。
     想新增選單項目時，nav-links.blade.php 與這個檔案都要加。 --}}
@php
    $masterPermissions = ['users.manage', 'roles.manage', 'departments.manage', 'classrooms.manage', 'device-categories.manage', 'devices.manage'];
    $masterActive = request()->routeIs('users.*', 'roles.*', 'departments.*', 'classrooms.*', 'device-categories.*', 'devices.*');
    $repairActive = request()->routeIs('knowledge-base.*', 'repairs.*', 'repair-logs.*');
    $maintenanceActive = request()->routeIs('maintenance-items.*', 'maintenance-plans.*', 'maintenance-orders.*', 'device-profile.*', 'preventive-candidates.*', 'ai-settings.*');
    $resourceActive = request()->routeIs('reservations.*', 'parts.*', 'repair-cost-analysis.*');
    $systemActive = request()->routeIs('audit-logs.*');
@endphp
<ul class="navbar-nav tb-topnav d-none d-xl-flex flex-row align-items-center gap-1 me-auto" aria-label="主選單">
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}" @if (request()->routeIs('dashboard')) aria-current="page" @endif>
            {{ __('common.nav.dashboard') }}
        </a>
    </li>

    @canany($masterPermissions)
        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle {{ $masterActive ? 'active' : '' }}" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-database me-1" aria-hidden="true"></i>{{ __('common.nav.master_section') }}
            </a>
            <ul class="dropdown-menu shadow">
                @can('users.manage')
                    <li><a class="dropdown-item {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}"><i class="bi bi-people me-2"></i>{{ __('common.nav.users') }}</a></li>
                @endcan
                @can('roles.manage')
                    <li><a class="dropdown-item {{ request()->routeIs('roles.*') ? 'active' : '' }}" href="{{ route('roles.index') }}"><i class="bi bi-shield-lock me-2"></i>{{ __('common.nav.roles') }}</a></li>
                @endcan
                @can('departments.manage')
                    <li><a class="dropdown-item {{ request()->routeIs('departments.*') ? 'active' : '' }}" href="{{ route('departments.index') }}"><i class="bi bi-diagram-3 me-2"></i>{{ __('common.nav.departments') }}</a></li>
                @endcan
                @can('classrooms.manage')
                    <li><a class="dropdown-item {{ request()->routeIs('classrooms.*') ? 'active' : '' }}" href="{{ route('classrooms.index') }}"><i class="bi bi-building me-2"></i>{{ __('common.nav.classrooms') }}</a></li>
                @endcan
                @can('device-categories.manage')
                    <li><a class="dropdown-item {{ request()->routeIs('device-categories.*') ? 'active' : '' }}" href="{{ route('device-categories.index') }}"><i class="bi bi-tags me-2"></i>{{ __('common.nav.device_categories') }}</a></li>
                @endcan
                @can('devices.manage')
                    <li><a class="dropdown-item {{ request()->routeIs('devices.*') ? 'active' : '' }}" href="{{ route('devices.index') }}"><i class="bi bi-pc-display me-2"></i>{{ __('common.nav.devices') }}</a></li>
                @endcan
            </ul>
        </li>
    @endcanany

    <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle {{ $repairActive ? 'active' : '' }}" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            {{ __('common.nav.repair_section') }}
        </a>
        <ul class="dropdown-menu shadow">
            <li><a class="dropdown-item {{ request()->routeIs('knowledge-base.*') ? 'active' : '' }}" href="{{ route('knowledge-base.index') }}"><i class="bi bi-book me-2"></i>{{ __('common.nav.knowledge_base') }}</a></li>
            <li><a class="dropdown-item {{ request()->routeIs('repairs.*', 'repair-logs.*') ? 'active' : '' }}" href="{{ route('repairs.index') }}"><i class="bi bi-tools me-2"></i>{{ __('common.nav.repair_requests') }}</a></li>
        </ul>
    </li>

    @canany(['maintenance.view', 'ai-maintenance.manage'])
        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle {{ $maintenanceActive ? 'active' : '' }}" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                {{ __('common.nav.maintenance_section') }}
            </a>
            <ul class="dropdown-menu shadow">
                @can('maintenance.view')
                    <li><a class="dropdown-item {{ request()->routeIs('maintenance-items.*') ? 'active' : '' }}" href="{{ route('maintenance-items.index') }}"><i class="bi bi-list-check me-2"></i>{{ __('common.nav.maintenance_items') }}</a></li>
                    <li><a class="dropdown-item {{ request()->routeIs('maintenance-plans.*') ? 'active' : '' }}" href="{{ route('maintenance-plans.index') }}"><i class="bi bi-calendar-event me-2"></i>{{ __('common.nav.maintenance_plans') }}</a></li>
                    <li><a class="dropdown-item {{ request()->routeIs('maintenance-orders.*') ? 'active' : '' }}" href="{{ route('maintenance-orders.index') }}"><i class="bi bi-wrench-adjustable me-2"></i>{{ __('common.nav.maintenance_orders') }}</a></li>
                    <li><a class="dropdown-item {{ request()->routeIs('device-profile.*') ? 'active' : '' }}" href="{{ route('device-profile.index') }}"><i class="bi bi-journal-text me-2"></i>{{ __('common.nav.device_profile') }}</a></li>
                @endcan
                @can('ai-maintenance.manage')
                    <li><a class="dropdown-item {{ request()->routeIs('preventive-candidates.*', 'ai-settings.*') ? 'active' : '' }}" href="{{ route('preventive-candidates.index') }}"><i class="bi bi-robot me-2"></i>{{ __('common.nav.preventive_ai') }}</a></li>
                @endcan
            </ul>
        </li>
    @endcanany

    <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle {{ $resourceActive ? 'active' : '' }}" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            {{ __('common.nav.resource_section') }}
        </a>
        <ul class="dropdown-menu shadow">
            <li><a class="dropdown-item {{ request()->routeIs('reservations.*') ? 'active' : '' }}" href="{{ route('reservations.index') }}"><i class="bi bi-calendar-check me-2"></i>{{ __('common.nav.reservations') }}</a></li>
            @can('parts.manage')
                <li><a class="dropdown-item {{ request()->routeIs('parts.*') ? 'active' : '' }}" href="{{ route('parts.index') }}"><i class="bi bi-box-seam me-2"></i>{{ __('common.nav.parts') }}</a></li>
            @endcan
            @can('maintenance.view')
                <li><a class="dropdown-item {{ request()->routeIs('repair-cost-analysis.*') ? 'active' : '' }}" href="{{ route('repair-cost-analysis.index') }}"><i class="bi bi-cash-stack me-2"></i>{{ __('common.nav.cost_analysis') }}</a></li>
            @endcan
        </ul>
    </li>

    @can('audit-logs.view')
        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle {{ $systemActive ? 'active' : '' }}" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                {{ __('common.nav.system_section') }}
            </a>
            <ul class="dropdown-menu shadow">
                <li><a class="dropdown-item {{ request()->routeIs('audit-logs.*') ? 'active' : '' }}" href="{{ route('audit-logs.index') }}"><i class="bi bi-clock-history me-2"></i>{{ __('common.nav.audit_logs') }}</a></li>
            </ul>
        </li>
    @endcan
</ul>
