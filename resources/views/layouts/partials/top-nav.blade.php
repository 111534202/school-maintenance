{{-- 上方導覽列裡的選單（風格 B）：寬螢幕（1200px 以上）直接顯示在墨綠導覽列上，每個分類是一個下拉選單（滑鼠停上去就打開）。
     比較窄的螢幕看不到這一排，改用左上角的漢堡選單；漢堡選單在任何寬度都保留（守門測試 DrilldownNavigationTest）。
     項目、權限與漢堡選單、主控台功能入口同一份資料：app/Support/NavMenu.php，想新增選單項目改那裡就好。 --}}
@php
    ['menu' => $topMenu] = \App\Support\NavMenu::build(auth()->user());
@endphp
<ul class="navbar-nav tb-topnav d-none d-xl-flex flex-row align-items-center gap-1 me-auto" aria-label="主選單">
    @foreach ($topMenu as $entry)
        @continue(! ($entry['show'] ?? false))
        @if ($entry['type'] === 'link')
            <li class="nav-item">
                <a class="nav-link {{ $entry['active'] ? 'active' : '' }}" href="{{ $entry['href'] }}" @if ($entry['active']) aria-current="page" @endif>
                    {{ $entry['label'] }}
                </a>
            </li>
        @else
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle {{ $entry['active'] ? 'active' : '' }}" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    {{ $entry['label'] }}
                </a>
                <ul class="dropdown-menu shadow">
                    @foreach ($entry['children'] as $child)
                        <li>
                            <a class="dropdown-item {{ $child['active'] ? 'active' : '' }}" href="{{ $child['href'] }}" @if ($child['active']) aria-current="page" @endif>
                                <i class="bi {{ $child['icon'] }} me-2" aria-hidden="true"></i>{{ $child['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </li>
        @endif
    @endforeach
</ul>
<script>
// 滑鼠停到另一個分類時，先把「用點擊打開」的下拉選單收起來，避免兩個選單同時出現、疊在一起。
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.tb-topnav .dropdown').forEach(function (dropdown) {
        dropdown.addEventListener('mouseenter', function () {
            document.querySelectorAll('.tb-topnav .dropdown-toggle.show').forEach(function (toggle) {
                if (!dropdown.contains(toggle) && window.bootstrap) {
                    window.bootstrap.Dropdown.getOrCreateInstance(toggle).hide();
                }
            });
        });
    });
});
</script>
