{{-- 「洋蔥式」選單：一層一層往內點（像手機的設定頁）。手機的滑出選單與桌面版側邊欄共用這一份。 --}}
{{-- 第一層是大分類與單一功能；點有「›」的分類會從右邊滑進第二層，點「返回」退回上一層。 --}}
{{-- 呼叫時傳 drillId（'mobile' 或 'desktop'）：兩份選單同時存在於同一個網頁，id 不能重複。 --}}
{{-- 新增選單項目：加在下面的 $menu 陣列就好（手機與桌面版同時生效）；權限規則寫在項目最後的 $can(...)。 --}}
@php
    $drillId = $drillId ?? 'mobile';
    $user = auth()->user();
    // 有沒有某個權限（沒登入或沒有身分一律 false）。
    $can = fn (string $permission) => $user && $user->can($permission);
    // 路由存在才產生網址，其他組員的模組還沒合併時不會出現壞連結。
    $link = fn (string $label, string $icon, string $route, array $active, bool $show = true) => [
        'label' => $label, 'icon' => $icon, 'href' => \Illuminate\Support\Facades\Route::has($route) ? route($route) : '#',
        'active' => request()->routeIs(...$active), 'show' => $show && \Illuminate\Support\Facades\Route::has($route),
    ];

    // 選單內容：type = link（單一連結）／group（往內一層）。
    $menu = [
        ['type' => 'link'] + $link(__('common.nav.dashboard'), 'bi-speedometer2', 'dashboard', ['dashboard']),
        ['type' => 'group', 'id' => 'work', 'label' => '報修與知識庫', 'icon' => 'bi-tools', 'children' => [
            $link(__('common.nav.repair_requests'), 'bi-tools', 'repairs.index', ['repairs.*']),
            $link(__('common.nav.knowledge_base'), 'bi-book', 'knowledge-base.index', ['knowledge-base.*']),
        ]],
        ['type' => 'group', 'id' => 'maintenance', 'label' => '保養與 AI 預防', 'icon' => 'bi-wrench-adjustable', 'children' => [
            $link('保養項目', 'bi-list-check', 'maintenance-items.index', ['maintenance-items.*'], $can('maintenance.view')),
            $link('保養計畫', 'bi-calendar-event', 'maintenance-plans.index', ['maintenance-plans.*'], $can('maintenance.view')),
            $link('保養工單', 'bi-wrench-adjustable', 'maintenance-orders.index', ['maintenance-orders.*'], $can('maintenance.view')),
            $link('設備履歷', 'bi-journal-text', 'device-profile.index', ['device-profile.*'], $can('maintenance.view')),
            $link('AI 預防保養', 'bi-robot', 'preventive-candidates.index', ['preventive-candidates.*', 'ai-settings.*'], $can('ai-maintenance.manage')),
        ]],
        ['type' => 'group', 'id' => 'space', 'label' => '空間與庫存', 'icon' => 'bi-building', 'children' => [
            $link('空間預約', 'bi-calendar-check', 'reservations.index', ['reservations.*']),
            $link('庫存', 'bi-box-seam', 'inventory.index', ['inventory.*']),
        ]],
        ['type' => 'group', 'id' => 'master', 'label' => __('common.nav.master_section'), 'icon' => 'bi-database', 'children' => [
            $link(__('common.nav.users'), 'bi-people', 'users.index', ['users.*'], $can('users.manage')),
            $link(__('common.nav.roles'), 'bi-shield-lock', 'roles.index', ['roles.*'], $can('roles.manage')),
            $link(__('common.nav.departments'), 'bi-diagram-3', 'departments.index', ['departments.*'], $can('departments.manage')),
            $link(__('common.nav.classrooms'), 'bi-building', 'classrooms.index', ['classrooms.*'], $can('classrooms.manage')),
            $link(__('common.nav.device_categories'), 'bi-tags', 'device-categories.index', ['device-categories.*'], $can('device-categories.manage')),
            $link(__('common.nav.devices'), 'bi-pc-display', 'devices.index', ['devices.*'], $can('devices.manage')),
        ]],
        ['type' => 'group', 'id' => 'system', 'label' => __('common.nav.system_section'), 'icon' => 'bi-gear', 'children' => [
            $link(__('common.nav.audit_logs'), 'bi-clock-history', 'audit-logs.index', ['audit-logs.*'], $can('audit-logs.view')),
        ]],
    ];

    // 整理：
    //  1. 把沒權限的子項目拿掉；
    //  2. 一個子項目都沒有的分類整個不顯示；
    //  3. 只剩一個子項目的分類，直接變成第一層的單一連結（少點一層）；
    //  4. 分類裡有「目前所在頁面」就標記 active，並且預設展開那一層（讓使用者知道自己在哪）。
    $openGroup = null;
    foreach ($menu as $i => $entry) {
        if ($entry['type'] !== 'group') {
            continue;
        }
        $children = array_values(array_filter($entry['children'], fn ($c) => $c['show']));
        if (count($children) === 1) {
            $menu[$i] = ['type' => 'link'] + $children[0];
            continue;
        }
        $entry['children'] = $children;
        $entry['show'] = count($children) > 0;
        $entry['active'] = count(array_filter($children, fn ($c) => $c['active'])) > 0;
        if ($entry['show'] && $entry['active']) {
            $openGroup = $entry['id'];
        }
        $menu[$i] = $entry;
    }
    $groups = array_values(array_filter($menu, fn ($e) => $e['type'] === 'group' && $e['show']));
@endphp

{{-- 樣式只需要輸出一次（手機與桌面版共用）。 --}}
@once
    <style>
        /* 每一層是一個鋪滿的面板，往內點時新的一層從右邊滑進來蓋住上一層。 */
        .drill { position: relative; overflow: hidden; height: calc(100vh - 3.5rem); height: calc(100dvh - 3.5rem); }
        /* 桌面版側邊欄：高度扣掉頂部列（56px），捲動頁面時選單固定在左邊。 */
        .app-sidebar .drill { position: sticky; top: 0; height: calc(100vh - 56px); }
        .drill-panel {
            position: absolute; inset: 0; overflow-y: auto; background: #fff;
            transition: transform .25s ease, visibility 0s linear .25s;
        }
        /* 第一層平常在原位；有往內點時稍微往左退，讓人看出是「疊在上面」。 */
        .drill-root.is-pushed { transform: translateX(-28%); }
        /* 第二層平常藏在右邊畫面外（visibility 一併隱藏，鍵盤與讀屏就不會跑進去）。 */
        .drill-sub { transform: translateX(100%); visibility: hidden; box-shadow: -6px 0 12px rgba(0,0,0,.08); }
        .drill-sub.is-open { transform: translateX(0); visibility: visible; transition: transform .25s ease, visibility 0s; }
        /* 每一列都是容易點的大按鈕（高度約 48px 以上）。 */
        .drill-item {
            display: flex; align-items: center; gap: .75rem; width: 100%; min-height: 3rem; padding: .7rem 1rem;
            border: 0; border-bottom: 1px solid #eef0f2; background: transparent; color: #212529; text-align: left; text-decoration: none; font-size: 1rem;
        }
        .drill-item:hover, .drill-item:focus-visible { background: #f5f7fa; color: #212529; }
        .drill-item .bi:first-child { width: 1.25rem; text-align: center; color: #6c757d; }
        .drill-item .drill-chevron { margin-left: auto; color: #adb5bd; }
        /* 目前所在頁面：藍色；包含目前頁面的分類：左邊一條藍線。 */
        .drill-item.active { background: #e7f1ff; color: #0d6efd; font-weight: 600; }
        .drill-item.active .bi:first-child { color: #0d6efd; }
        .drill-item.has-active { box-shadow: inset 3px 0 0 #0d6efd; }
        /* 第二層頂端的標題列：返回按鈕＋分類名稱，捲動時固定在上面。 */
        .drill-top { position: sticky; top: 0; z-index: 1; display: flex; align-items: center; gap: .25rem; padding: .35rem .5rem; background: #fff; border-bottom: 1px solid #dee2e6; }
        .drill-top .drill-back { border: 0; background: transparent; color: #0d6efd; display: inline-flex; align-items: center; gap: .15rem; min-height: 2.5rem; padding: 0 .5rem; font-size: 1rem; }
        .drill-top .drill-title { font-weight: 600; }
        @media (prefers-reduced-motion: reduce) { .drill-panel, .drill-sub.is-open { transition: none; } }
    </style>
@endonce

<div class="drill" id="drill-{{ $drillId }}" data-drill>
    {{-- 第一層 --}}
    <div class="drill-panel drill-root {{ $openGroup ? 'is-pushed' : '' }}" data-drill-root @if ($openGroup) inert @endif>
        @foreach ($menu as $entry)
            @if ($entry['type'] === 'link')
                @if ($entry['show'])
                    <a class="drill-item {{ $entry['active'] ? 'active' : '' }}" href="{{ $entry['href'] }}">
                        <i class="bi {{ $entry['icon'] }}"></i><span>{{ $entry['label'] }}</span>
                    </a>
                @endif
            @elseif ($entry['show'])
                {{-- 往內一層的分類（按鈕，不是網址）。 --}}
                <button type="button" class="drill-item {{ $entry['active'] ? 'has-active' : '' }}" data-drill-open="{{ $entry['id'] }}" aria-haspopup="true">
                    <i class="bi {{ $entry['icon'] }}"></i><span>{{ $entry['label'] }}</span>
                    <i class="bi bi-chevron-right drill-chevron"></i>
                </button>
            @endif
        @endforeach
    </div>

    {{-- 第二層：每個分類一個面板。 --}}
    @foreach ($groups as $group)
        <div class="drill-panel drill-sub {{ $openGroup === $group['id'] ? 'is-open' : '' }}" data-drill-panel="{{ $group['id'] }}" @if ($openGroup !== $group['id']) inert @endif>
            <div class="drill-top">
                <button type="button" class="drill-back" data-drill-back aria-label="返回上一層"><i class="bi bi-chevron-left"></i>返回</button>
                <span class="drill-title">{{ $group['label'] }}</span>
            </div>
            @foreach ($group['children'] as $child)
                <a class="drill-item {{ $child['active'] ? 'active' : '' }}" href="{{ $child['href'] }}">
                    <i class="bi {{ $child['icon'] }}"></i><span>{{ $child['label'] }}</span>
                </a>
            @endforeach
        </div>
    @endforeach
</div>

<script>
    // 切換邏輯：往內點 → 第二層滑進來；返回 → 滑出去。
    // 手機版選單關起來之後，下次打開時直接停在「目前頁面所在的那一層」（沒有就停在第一層）。
    (function () {
        var wrap = document.getElementById('drill-{{ $drillId }}');
        if (!wrap) return;
        var root = wrap.querySelector('[data-drill-root]');

        function openPanel(id, keepFocus) {
            var panel = wrap.querySelector('[data-drill-panel="' + id + '"]');
            if (!panel) return;
            panel.classList.add('is-open');
            panel.removeAttribute('inert');
            panel.scrollTop = 0;
            root.classList.add('is-pushed');
            root.setAttribute('inert', '');   // 第一層被蓋住時不可聚焦
            var back = panel.querySelector('[data-drill-back]');
            if (back && !keepFocus) back.focus({ preventScroll: true });
        }

        function closeAll() {
            wrap.querySelectorAll('.drill-sub.is-open').forEach(function (p) {
                p.classList.remove('is-open');
                p.setAttribute('inert', '');
            });
            root.classList.remove('is-pushed');
            root.removeAttribute('inert');
        }

        wrap.addEventListener('click', function (e) {
            var opener = e.target.closest('[data-drill-open]');
            if (opener) { openPanel(opener.getAttribute('data-drill-open')); return; }
            if (e.target.closest('[data-drill-back]')) closeAll();
        });

        @if ($drillId === 'mobile')
        var offcanvas = document.getElementById('sidebarOffcanvas');
        if (offcanvas) {
            offcanvas.addEventListener('hidden.bs.offcanvas', function () {
                closeAll();
                var active = wrap.querySelector('.drill-sub .drill-item.active');
                if (active) openPanel(active.closest('[data-drill-panel]').getAttribute('data-drill-panel'), true);
            });
        }
        @endif
    })();
</script>
