{{-- 各頁面自己的內容會被放在這裡（兩處是登入後與未登入的版面）。 --}}
{{-- 【Blade 版面（layout）是什麼？】所有頁面共用的外框：最上方的導覽列、左邊的選單、訊息區，中間 @yield('content') 才是各頁面自己的內容。 --}}
{{-- 各頁面用 @extends('layouts.app') 套用這個外框，再用 @section('content') 填入內容。 --}}
{{-- 想改全站共用的外觀（顏色、導覽列、選單位置）改這個檔案；想改選單有哪些連結，改 app/Support/NavMenu.php。 --}}
{{-- Blade 語法速查：雙大括號包住變數 = 輸出文字（會自動防止惡意程式碼）；@if / @foreach 是條件與迴圈；@can('權限') 有權限才顯示；大括號加兩個減號的寫法是不會輸出到網頁的註解。 --}}
<!DOCTYPE html>
{{-- 網頁的語言標記，依目前選的語言自動變成 zh-TW 或 en。 --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    {{-- 讓手機瀏覽器用正確的寬度顯示（響應式網頁 RWD 必備）。 --}}
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- 瀏覽器分頁標題：各頁面用 @section('title', ...) 提供；沒提供就用網站名稱。 --}}
    <title>@yield('title', __('common.site_title'))</title>
    {{-- 載入 Bootstrap 5 樣式（網格、按鈕、表格、表單等，放在 public/vendor/bootstrap，不連外部網站，離線也有樣式）。 --}}
    <link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    {{-- Bootstrap Icons：全站按鈕統一用圖示（筆=編輯、垃圾桶=刪除…），不使用 emoji。 --}}
    <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
    {{-- 以下是本站自訂的樣式，補強 Bootstrap 不足的地方。 --}}
    <style>
        /* 頁面至少和視窗一樣高。 */
        body { min-height: 100vh; }
        /* 圖示按鈕：固定正方形，列表操作欄的按鈕大小一致、不會互相擠壓。 */
        .icon-btn { width: 2rem; height: 2rem; padding: 0; display: inline-flex; align-items: center; justify-content: center; }
        /* 篩選列：一律單行不換行，視窗太窄時整列左右捲動。 */
        .filter-bar { display: flex; flex-wrap: nowrap; align-items: flex-end; gap: 0.75rem; overflow-x: auto; padding-bottom: 0.75rem; margin-bottom: -0.5rem; }
        /* 手機寬度：頁面標題列的標題與按鈕放不下時整組換行，不要互相擠壓。 */
        .page-header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 0.5rem; }
        @media (min-width: 768px) { .fs-md-5 { font-size: 1.25rem !important; } }
        /* 觸控裝置：圖示按鈕放大到好點的尺寸；表單欄位字級不低於 16px，避免 iOS 點進去自動放大整個畫面。 */
        @media (pointer: coarse) {
            .icon-btn { width: 2.5rem; height: 2.5rem; }
            .form-control, .form-select, .form-control-sm, .form-select-sm { font-size: 1rem; }
        }
        /* 篩選列裡的每個欄位保持原本大小，不被壓縮。 */
        .filter-bar > * { flex: 0 0 auto; }
        .filter-bar label { white-space: nowrap; }
        /* 窄版頁面（新增／編輯表單）限制最大寬度，避免在大螢幕上被拉得太寬。 */
        .page-narrow { max-width: 880px; }
        /* 表單分區標題的字型。 */
        .form-section-title { font-size: 0.95rem; font-weight: 600; color: #495057; }
        /* 選單抽屜：寬度固定 280px（Bootstrap 預設 400px 在桌面太寬）；窄螢幕時最多佔 85% 寬度。 */
        #sidebarOffcanvas { --bs-offcanvas-width: min(280px, 85vw); }
        /* 選單連結平常是深灰色，目前所在頁面（.active）是藍底白字。 */
        .offcanvas .nav-link { color: #495057; }
        .offcanvas .nav-link.active { color: #fff; background-color: #0d6efd; }
        /* 讓內容區可以比內容窄，否則寬表格會把整個頁面撐開（水平捲動交給表格自己處理）。 */
        .app-content { min-width: 0; }
        @media (max-width: 575.98px) {
            .app-content { padding: 1rem !important; }
        }
        /* 中文在窄欄位會逐字換行，讓表格看起來直排；改成不換行，
           太寬就交給 .table-responsive 的水平捲動處理 */
        .table-responsive table th, .table-responsive table td {
            white-space: nowrap;
        }
        /* i18n 語言切換下拉選單：跟登出按鈕放在同一排右上角。 */
        .locale-switcher select {
            background: transparent; color: #fff; border: 1px solid rgba(255,255,255,0.5);
            border-radius: 4px; padding: 0.25rem 0.4rem; font-size: 0.85rem;
        }
        .locale-switcher select option { color: #212529; }
        /* 選單裡可展開的群組（主檔）：箭頭展開時翻轉，子項目縮排並用左邊線標示層級。 */
        .nav-group-toggle .nav-chevron { transition: transform .2s; }
        .nav-group-toggle[aria-expanded="true"] .nav-chevron { transform: rotate(180deg); }
        .offcanvas .nav-group-toggle { color: #495057; cursor: pointer; }
    </style>
    {{-- 全站風格 B「校園溫和」：墨綠導覽列、暖白底、思源黑體／宋體、Bootstrap 主色換成墨綠（說明見 public/css/theme-b.css 開頭）。
         洋蔥式元件（清單詳細面板、篩選面板、分步驟表單、洋蔥式選單、主控台入口）的配色也在那個檔案的「3. 洋蔥式元件」。 --}}
    <link href="{{ asset('css/theme-b.css') }}?v={{ filemtime(public_path('css/theme-b.css')) }}" rel="stylesheet">
</head>
{{-- 頁面主體：暖白色背景（tb-body，見 theme-b.css）。 --}}
{{-- 網址帶 ?modal=1：這一頁是放在別頁對話框裡的表單（例如零件履歷的「修改採購」），只顯示內容，不顯示導覽列與選單。 --}}
@php($embedded = request()->boolean('modal'))
<body class="tb-body {{ $embedded ? 'tb-embedded' : '' }}">
@unless ($embedded)
    {{-- 最上方的墨綠導覽列（tb-navbar，見 theme-b.css；捲動時固定在畫面最上方）。 --}}
    <nav class="navbar navbar-dark tb-navbar">
        {{-- RWD：頂部列永遠單排（漢堡 / 標題 / 右側工具），標題太長就省略號，不會換成兩排。 --}}
        <div class="container-fluid flex-nowrap gap-2">
            @auth
                {{-- 「漢堡」按鈕（手機、平板、桌面都一樣）：整份選單收在裡面，點了會打開下面的 #sidebarOffcanvas 側邊選單。
                     不採用寬螢幕上方選單（top-nav），與 integrate/all 相同。 --}}
                <button class="btn btn-outline-light btn-sm tb-nav-btn tb-nav-btn-square flex-shrink-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-label="開啟選單">
                    <i class="bi bi-list fs-5"></i>
                </button>
                {{-- 「返回」按鈕：每一頁（主控台除外）都有，最內層的清單頁（用戶主檔、空間預約…）也一樣能退回上一頁。
                     點了回到瀏覽紀錄的上一頁；沒有上一頁（直接開網址、新分頁）就回父頁（清單頁或所屬的工單，規則見 App\Support\BackLink），再不行回主控台。
                     各頁面不再自己放返回連結，返回只放在這裡（守門測試：BackLinkTest）。 --}}
                @unless (request()->routeIs('dashboard'))
                    <a href="{{ \App\Support\BackLink::url(request()->route()) }}" class="btn btn-outline-light btn-sm tb-nav-btn flex-shrink-0" data-page-back aria-label="{{ __('common.buttons.back') }}">
                        <i class="bi bi-chevron-left"></i><span class="d-none d-sm-inline ms-1">{{ __('common.buttons.back') }}</span>
                    </a>
                @endunless
            @endauth
            {{-- 網站名稱（左側標題）。 --}}
            {{-- 已登入時，點網站名稱回到主控台（沒登入就只是標題）。同步自 integrate/all（彭仕衡 5cc43ed）。 --}}
            @auth
                <a href="{{ route('dashboard') }}" class="navbar-brand text-truncate me-auto mb-0 fs-6 fs-md-5" style="min-width: 0;" data-site-title>{{ __('common.site_title') }}</a>
            @else
                <span class="navbar-brand text-truncate me-auto mb-0 fs-6 fs-md-5" style="min-width: 0;">{{ __('common.site_title') }}</span>
            @endauth
            <div class="d-flex align-items-center gap-2 gap-md-3 flex-shrink-0">
                {{-- i18n 語言切換：目前先隱藏（config('app.locale_switcher') 預設 false，之後要開放把
                     .env 的 APP_LOCALE_SWITCHER 設成 true 就好，翻譯與切換功能都還在）。 --}}
                @if (config('app.locale_switcher'))
                    <div class="locale-switcher">
                        <select id="locale-select" aria-label="{{ __('common.locale.zh_TW') }} / {{ __('common.locale.en') }}">
                            <option value="{{ route('locale.switch', 'zh_TW') }}" @selected(app()->getLocale() === 'zh_TW')>{{ __('common.locale.zh_TW') }}</option>
                            <option value="{{ route('locale.switch', 'en') }}" @selected(app()->getLocale() === 'en')>{{ __('common.locale.en') }}</option>
                        </select>
                    </div>
                @endif
                @auth
                    {{-- 通知鈴鐺：有「新報修待派工」「待驗收」「指派給我的案件」時顯示紅色數字，
                         點開清單，點其中一項直接跳到對應的案件列表。資料由 AppServiceProvider 的 view composer 算好。 --}}
                    <div class="dropdown">
                        <button class="btn btn-outline-light btn-sm icon-btn position-relative" type="button" id="notificationBell"
                            data-bs-toggle="dropdown" aria-expanded="false"
                            title="{{ __('notifications.title') }}" aria-label="{{ __('notifications.title') }}">
                            <i class="bi {{ ($notificationTotal ?? 0) > 0 ? 'bi-bell-fill' : 'bi-bell' }}"></i>
                            @if (($notificationTotal ?? 0) > 0)
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger" id="notificationBadge">
                                    {{ $notificationTotal > 99 ? '99+' : $notificationTotal }}
                                </span>
                            @endif
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="notificationBell" style="min-width: 17rem;">
                            <li><h6 class="dropdown-header">{{ __('notifications.title') }}</h6></li>
                            {{-- @forelse：逐項列出通知；沒有任何通知時改走 @empty 區塊（顯示「目前沒有待處理事項」）。 --}}
                            @forelse (($notificationItems ?? []) as $item)
                                <li>
                                    <a class="dropdown-item d-flex justify-content-between align-items-center gap-3" href="{{ $item['url'] }}">
                                        <span><i class="bi {{ $item['icon'] }} me-2"></i>{{ $item['text'] }}</span>
                                        <span class="badge rounded-pill text-bg-danger">{{ $item['count'] }}</span>
                                    </a>
                                </li>
                            @empty
                                <li><span class="dropdown-item-text text-muted small">{{ __('notifications.empty') }}</span></li>
                            @endforelse
                        </ul>
                    </div>

                    <span class="text-light tb-nav-user d-none d-sm-inline">
                        {{ Auth::user()->name }}｜{{ Auth::user()->role->name ?? '尚未指派角色' }}
                    </span>
                    {{-- 手機寬度放不下姓名文字，改顯示人像圖示，長按/滑過仍可看到姓名與角色。 --}}
                    <i class="bi bi-person-circle text-light fs-5 d-sm-none" title="{{ Auth::user()->name }}｜{{ Auth::user()->role->name ?? '尚未指派角色' }}"></i>
                    {{-- 登出按鈕其實是一個送出 POST 的表單（登出是「動作」，不能用一般連結）。 --}}
                    <form method="POST" action="{{ route('logout') }}">
                        {{-- 表單防偽 token（CSRF）：Laravel 要求所有 POST 表單都要有，沒有會被擋下（錯誤 419）。 --}}
                        @csrf
                        <button type="submit" class="btn btn-outline-light btn-sm icon-btn" title="登出" aria-label="登出"><i class="bi bi-box-arrow-right"></i></button>
                    </form>
                @endauth
                @guest
                    {{-- 免登入查詢課表頁：右上角放「登入」，登入後回到空間預約。 --}}
                    @if (request()->routeIs('schedule'))
                        <a href="{{ route('schedule.login') }}" class="btn btn-outline-light btn-sm tb-nav-btn px-3" data-login-required-ok>
                            <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>登入
                        </a>
                    @endif
                @endguest
            </div>
        </div>
    </nav>
@endunless

    @if (config('app.locale_switcher') && ! $embedded)
        <script>
            document.getElementById('locale-select').addEventListener('change', function () {
                window.location.href = this.value;
            });
        </script>
    @endif

    @auth
        @unless ($embedded)
        {{-- 全站唯一的選單：預設收起來，點左上角的漢堡按鈕才從左邊滑出（所有螢幕大小都一樣，不再有固定的側邊欄）。 --}}
        {{-- data-bs-scroll：打開選單時不拿掉頁面捲軸（Bootstrap 拿掉捲軸時會在 body 右邊補一條空白，頂端列不會跟著補，最右邊就多出一條空白）。 --}}
        <div class="offcanvas offcanvas-start" tabindex="-1" id="sidebarOffcanvas" data-bs-scroll="true">
            <div class="offcanvas-header">
                {{-- 右上角不放關閉 ✕：點選單外面的灰色區域或按 Esc 就會收起來。 --}}
                <h6 class="offcanvas-title">選單</h6>
            </div>
            {{-- 手機版與桌面版都用「洋蔥式」選單（一層一層往內點，見 layouts/partials/nav-drilldown.blade.php）。 --}}
            <div class="offcanvas-body p-0">
                @include('layouts.partials.nav-drilldown', ['drillId' => 'drawer'])
            </div>
        </div>
        @endunless

        <div class="d-flex">
            <div class="flex-grow-1 app-content {{ $embedded ? 'p-3' : 'p-3 p-md-4' }}">
                {{-- 操作成功訊息（Controller 用 ->with('success', ...) 帶過來，只顯示一次）；右邊的 ✕ 可以手動關閉。 --}}
                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="關閉"></button>
                    </div>
                @endif
                {{-- 操作失敗訊息（Controller 用 ->with('error', ...) 帶過來）。 --}}
                @if (session('error') && !request()->routeIs('reservations.index'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="關閉"></button>
                    </div>
                @endif
                {{-- 表單驗證錯誤：把所有錯誤訊息列在一個紅色框裡。 --}}
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </div>
        </div>
    @else
        <div class="app-content p-3 p-md-4">
            @yield('content')
        </div>
    @endauth

    {{-- 可點的表格列：表格檢視裡有 data-href 的 <tr>，點任何一格（或用 Tab 移到該列後按 Enter）就進入該筆的詳細頁；
         點到列裡的按鈕、連結、表單欄位時照原本的功能，正在選取文字時也不跳頁。（清單檢視則是點一筆從右邊滑出詳細面板。） --}}
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('tr[data-href]').forEach(function (row) {
            if (!row.hasAttribute('tabindex')) { row.tabIndex = 0; }
            row.addEventListener('click', function (event) {
                if (event.defaultPrevented || event.target.closest('a, button, input, select, textarea, label, form')) { return; }
                if (window.getSelection().toString()) { return; }
                window.location.href = row.dataset.href;
            });
            row.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' && event.target === row) { window.location.href = row.dataset.href; }
            });
        });
    });
    </script>
    {{-- 上傳檔案的大小檢查：選檔時與送出前先檢查，超過就跳出提示，不會送到伺服器才出錯（The POST data is too large）。
         每個檔案的上限：欄位的 data-max-mb（跟後端驗證一致，例如零件採購附件 10 MB），以及伺服器的 upload_max_filesize，取較小的；
         一次送出的全部檔案加起來不能超過伺服器的 post_max_size。伺服器的兩個值見 App\Support\UploadLimit（在 php.ini 設定）。 --}}
    <script>
    (function () {
        var serverFile = {{ \App\Support\UploadLimit::fileBytes() }};
        var serverPost = {{ \App\Support\UploadLimit::postBytes() }};
        function mb(bytes) { return (Math.round(bytes / 1048576 * 10) / 10) + ' MB'; }
        function fileLimit(input) {
            var own = parseFloat(input.getAttribute('data-max-mb') || '0') * 1048576;
            if (own && serverFile) return Math.min(own, serverFile);
            return own || serverFile;
        }
        // 欄位有 data-max-files（例如零件採購一筆最多 5 個附件）時，選太多個也先擋下來。
        function tooMany(input) {
            if (!input.hasAttribute('data-max-files') || !input.files) return null;
            var max = parseInt(input.getAttribute('data-max-files'), 10);
            return input.files.length > max ? max : null;
        }
        // 欄位有 data-max-total-mb（附件一筆加起來的上限，例如 5 MB）時：已選的檔案＋data-existing-bytes（保留的舊附件）不能超過。
        function totalLimit(input) { return parseFloat(input.getAttribute('data-max-total-mb') || '0') * 1048576; }
        function existingBytes(input) { return parseInt(input.getAttribute('data-existing-bytes') || '0', 10) || 0; }
        function sumBytes(files) { var t = 0; for (var i = 0; i < files.length; i++) t += files[i].size; return t; }
        function tooHeavy(input) {
            var limit = totalLimit(input);
            if (!limit || !input.files) return null;
            var total = existingBytes(input) + sumBytes(input.files);
            return total > limit ? { total: total, limit: limit } : null;
        }
        function tooManyText(max) {
            return max <= 0 ? '附件已經達到上限，請先勾選刪除原本的附件。' : '最多只能再選 ' + max + ' 個檔案。';
        }
        function tooBig(input) {
            var limit = fileLimit(input);
            if (!limit || !input.files) return null;
            for (var i = 0; i < input.files.length; i++) {
                if (input.files[i].size > limit) return { file: input.files[i], limit: limit };
            }
            return null;
        }
        // 可以選多個檔案的欄位（multiple）：每次「選擇檔案」選到的會「加進」已選的清單，而不是取代；
        // 欄位下方列出已選的檔案，每個都可以按 ✕ 拿掉。太大的檔案、超過數量的部分會跳出提示並略過。
        var picked = new WeakMap();   // 每個欄位目前已選的檔案
        function fileKey(f) { return f.name + '|' + f.size + '|' + f.lastModified; }
        function setFiles(input, files) {
            var dt = new DataTransfer();
            files.forEach(function (f) { dt.items.add(f); });
            input.files = dt.files;
            picked.set(input, files);
            renderList(input, files);
        }
        function renderList(input, files) {
            var list = input._pickedList;
            if (!list) {
                list = document.createElement('ul');
                list.className = 'list-unstyled small mt-2 mb-0 picked-files';
                input.insertAdjacentElement('afterend', list);
                input._pickedList = list;
            }
            list.innerHTML = '';
            files.forEach(function (f, i) {
                var li = document.createElement('li');
                li.className = 'd-flex align-items-center gap-2 mb-1';
                li.innerHTML = '<i class="bi bi-paperclip text-muted"></i><span class="text-truncate"></span><span class="text-muted"></span>' +
                    '<button type="button" class="btn btn-link btn-sm p-0 text-danger ms-auto" aria-label="移除這個檔案"><i class="bi bi-x-lg"></i></button>';
                li.children[1].textContent = f.name;
                li.children[2].textContent = f.size >= 1048576 ? mb(f.size) : Math.max(1, Math.round(f.size / 1024)) + ' KB';
                li.querySelector('button').addEventListener('click', function () {
                    var rest = (picked.get(input) || []).slice();
                    rest.splice(i, 1);
                    setFiles(input, rest);
                });
                list.appendChild(li);
            });
            list.hidden = files.length === 0;
        }
        // 檔案欄位的錯誤用紅字顯示在欄位下方（已選清單的下面），不跳出視窗；空字串＝清掉錯誤。
        function showFileError(input, text) {
            var box = input._fileError;
            if (!box) {
                box = document.createElement('div');
                box.className = 'text-danger small mt-1 file-error';
                box.setAttribute('role', 'alert');
                (input._pickedList || input).insertAdjacentElement('afterend', box);
                input._fileError = box;
            } else if (input._pickedList && box.previousElementSibling !== input._pickedList) {
                input._pickedList.insertAdjacentElement('afterend', box);
            }
            box.innerHTML = '';
            (Array.isArray(text) ? text : [text]).filter(Boolean).forEach(function (line) {
                var d = document.createElement('div');
                d.textContent = line;
                box.appendChild(d);
            });
            box.hidden = !box.childNodes.length;
            input.classList.toggle('is-invalid', !box.hidden);
        }
        // 錯誤訊息後面加一顆「不附這些檔案，繼續」：按了才算處理完，才能送出表單（也可以改選較小的檔案，重新選檔會清掉）。
        function addAcknowledge(input) {
            var box = input._fileError;
            if (!box || box.querySelector('.file-ack')) return;
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-outline-secondary btn-sm mt-1 file-ack';
            btn.textContent = '不附這些檔案，繼續';
            btn.addEventListener('click', function () {
                input._rejected = false;
                showFileError(input, '');
            });
            box.appendChild(btn);
        }
        document.addEventListener('change', function (event) {
            var input = event.target;
            if (!input.matches || !input.matches('input[type=file]')) return;

            if (input.multiple && window.DataTransfer) {
                var current = (picked.get(input) || []).slice();
                var known = current.map(fileKey);
                var limit = fileLimit(input);
                var max = input.hasAttribute('data-max-files') ? parseInt(input.getAttribute('data-max-files'), 10) : Infinity;
                var total = totalLimit(input);
                var messages = [];
                Array.prototype.forEach.call(input.files, function (f) {
                    if (known.indexOf(fileKey(f)) !== -1) return;   // 同一個檔案選兩次只算一次
                    if (limit && f.size > limit) { messages.push('「' + f.name + '」有 ' + mb(f.size) + '，超過上限 ' + mb(limit) + '。'); return; }
                    if (current.length >= max) { messages.push(max <= 0 ? '附件已經達到上限，請先勾選刪除原本的附件。' : '這裡最多只能放 ' + max + ' 個檔案。'); return; }
                    if (total && existingBytes(input) + sumBytes(current) + f.size > total) {
                        messages.push('加入「' + f.name + '」（' + mb(f.size) + '）後附件會超過 ' + mb(total) + '，所以沒有加入；附件加起來最多 ' + mb(total) + '。');
                        return;
                    }
                    current.push(f);
                    known.push(fileKey(f));
                });
                setFiles(input, current);
                var unique = Array.from(new Set(messages));
                showFileError(input, unique);
                // 有檔案因超過上限沒有加入：記下來，送出前一定要先處理（見 submit 檢查），避免「選了超過上限的檔案卻照樣送出」。
                input._rejected = unique.length > 0;
                if (input._rejected) addAcknowledge(input);
                return;
            }

            var big = tooBig(input);
            var many = tooMany(input);
            if (big) {
                showFileError(input, '「' + big.file.name + '」有 ' + mb(big.file.size) + '，超過上限 ' + mb(big.limit) + '，請改選較小的檔案（例如把照片縮小）。');
                input.value = '';
            } else if (many !== null) {
                showFileError(input, tooManyText(many));
                input.value = '';
            } else {
                showFileError(input, '');
            }
        });
        document.addEventListener('submit', function (event) {
            var form = event.target;
            var total = 0;
            var inputs = form.querySelectorAll('input[type=file]');
            function stop(input, text) {
                event.preventDefault();
                showFileError(input, text);
                input.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            for (var i = 0; i < inputs.length; i++) {
                if (inputs[i]._rejected) {
                    stop(inputs[i], '有檔案因為超過上限，沒有加入附件。請改選較小的檔案，或按下方「不附這些檔案，繼續」之後再送出。');
                    addAcknowledge(inputs[i]);
                    return;
                }
                var big = tooBig(inputs[i]);
                if (big) {
                    return stop(inputs[i], '「' + big.file.name + '」有 ' + mb(big.file.size) + '，超過上限 ' + mb(big.limit) + '，請改選較小的檔案。');
                }
                var many = tooMany(inputs[i]);
                if (many !== null) {
                    return stop(inputs[i], tooManyText(many));
                }
                var heavy = tooHeavy(inputs[i]);
                if (heavy) {
                    return stop(inputs[i], '附件加起來有 ' + mb(heavy.total) + '，超過上限 ' + mb(heavy.limit) + '，請移除一些檔案或改選較小的檔案。');
                }
                for (var k = 0; k < inputs[i].files.length; k++) total += inputs[i].files[k].size;
            }
            if (serverPost && total > serverPost * 0.95) {
                stop(inputs[inputs.length - 1], '這次選的檔案加起來有 ' + mb(total) + '，超過伺服器一次能收的 ' + mb(serverPost) + '，請減少檔案數量或改選較小的檔案。');
            }
        }, true);
    })();
    </script>
    {{-- 密碼欄位的「顯示密碼」小眼睛：<input type="password" data-reveal> 會在右邊加一顆眼睛按鈕，按一下顯示輸入的內容，再按一下藏回去。 --}}
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('input[type="password"][data-reveal]').forEach(function (input) {
            var group = document.createElement('div');
            group.className = 'input-group';
            input.parentNode.insertBefore(group, input);
            group.appendChild(input);
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-outline-secondary';
            button.setAttribute('aria-label', '顯示密碼');
            button.innerHTML = '<i class="bi bi-eye" aria-hidden="true"></i>';
            button.addEventListener('click', function () {
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                button.setAttribute('aria-label', show ? '隱藏密碼' : '顯示密碼');
                button.innerHTML = '<i class="bi ' + (show ? 'bi-eye-slash' : 'bi-eye') + '" aria-hidden="true"></i>';
            });
            group.appendChild(button);
        });
    });
    </script>
    {{-- 刪除前的二次確認（全站共用）：表單加上 data-confirm="請問確定要刪除…嗎？"，按下送出會先跳出這個視窗，
         按「確認」才真的送出，按「取消」什麼都不做。用 <dialog>，會蓋在所有東西上面（包含清單的詳細視窗）。
         按鈕文字可用 data-confirm-ok 改（預設「確認刪除」）；不是危險動作（例如還原）可加 data-confirm-variant="primary"，按鈕改成主色。 --}}
    <dialog id="confirmActionDialog" class="tb-confirm-dialog" aria-labelledby="confirmActionTitle">
        <form method="dialog">
            <h2 class="tb-confirm-title" id="confirmActionTitle">請再確認一次</h2>
            <p class="tb-confirm-message" id="confirmActionMessage"></p>
            <div class="tb-confirm-actions">
                <button type="submit" value="cancel" class="btn btn-outline-secondary" autofocus>取消</button>
                <button type="submit" value="ok" class="btn btn-danger" id="confirmActionOk">確認刪除</button>
            </div>
        </form>
    </dialog>
    <script>
    (function () {
        const dialog = document.getElementById('confirmActionDialog');
        if (!dialog || typeof dialog.showModal !== 'function') return;   // 很舊的瀏覽器：退回不攔（後端照常處理）
        const message = document.getElementById('confirmActionMessage');
        const okButton = document.getElementById('confirmActionOk');
        let pending = null;
        document.addEventListener('submit', function (event) {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm')) return;
            if (form.dataset.confirmed === '1') { delete form.dataset.confirmed; return; }
            event.preventDefault();
            event.stopImmediatePropagation();
            pending = form;
            message.textContent = form.getAttribute('data-confirm');
            okButton.textContent = form.getAttribute('data-confirm-ok') || '確認刪除';
            okButton.className = 'btn ' + (form.getAttribute('data-confirm-variant') === 'primary' ? 'btn-primary' : 'btn-danger');
            dialog.returnValue = '';
            dialog.showModal();
        }, true);
        dialog.addEventListener('close', function () {
            const form = pending;
            pending = null;
            if (form && dialog.returnValue === 'ok') {
                form.dataset.confirmed = '1';
                if (typeof form.requestSubmit === 'function') form.requestSubmit(); else form.submit();
            }
        });
        // 點視窗外面（灰色背景）等於取消。
        dialog.addEventListener('click', function (event) { if (event.target === dialog) dialog.close('cancel'); });
    })();
    </script>
    {{-- 零件依日期重新計算先進先出後「某一筆當時庫存不足」被擋下時，用彈出視窗提醒（訊息以「錯誤，請重新確認：」開頭；
         不論是放在欄位錯誤還是頁面錯誤訊息裡都會抓到）。 --}}
    @if ($stockTimelineMessage = \App\Exceptions\PartStockTimelineException::pendingMessage($errors ?? null))
        <dialog id="stockTimelineDialog" class="tb-confirm-dialog" aria-labelledby="stockTimelineTitle">
            <form method="dialog">
                <h2 class="tb-confirm-title text-danger" id="stockTimelineTitle">錯誤，請重新確認</h2>
                <p class="tb-confirm-message">{{ $stockTimelineMessage }}</p>
                <div class="tb-confirm-actions">
                    <button type="submit" class="btn btn-primary" autofocus>我知道了</button>
                </div>
            </form>
        </dialog>
        <script>
            (function () { const d = document.getElementById('stockTimelineDialog'); if (d && d.showModal) d.showModal(); })();
        </script>
    @endif
    {{-- 暫時關掉整頁捲軸（洋蔥的詳細面板、篩選面板）：tbLockScroll(true) 關、tbLockScroll(false) 開回來。
         關掉時在 body 右邊補上原本捲軸的寬度，版面寬度不會跳一下（Bootstrap 對話框自己會做同樣的事）。手機、Mac 的捲軸寬度是 0，不受影響。 --}}
    <script>
    window.tbLockScroll = function (lock) {
        var html = document.documentElement;
        if (lock) {
            var w = window.innerWidth - html.clientWidth;
            html.style.overflow = 'hidden';
            document.body.style.paddingRight = w > 0 ? w + 'px' : '';
        } else {
            html.style.overflow = '';
            document.body.style.paddingRight = '';
        }
    };
    </script>
    {{-- 可以輸入也可以下拉選的欄位（跟空間預約的「選擇教室」一樣）：<input data-suggest='["選項1","選項2"]'>。
         點進欄位就列出全部選項，打字會篩出包含這些字的選項；點一個選項（或用上下鍵＋Enter）就填進去。
         加 data-suggest-submit 時，選了就直接送出表單（篩選列用）。樣式在 public/css/theme-b.css 的 .tb-suggest。 --}}
    <script>
    (function () {
        document.querySelectorAll('input[data-suggest]').forEach(function (input) {
            var items = [];
            try { items = JSON.parse(input.getAttribute('data-suggest')) || []; } catch (e) { return; }
            input.setAttribute('autocomplete', 'off');
            input.setAttribute('role', 'combobox');
            input.setAttribute('aria-expanded', 'false');
            var wrap = document.createElement('div');
            wrap.className = 'tb-suggest';
            input.parentNode.insertBefore(wrap, input);
            wrap.appendChild(input);
            var list = document.createElement('div');
            list.className = 'tb-suggest-list';
            list.setAttribute('role', 'listbox');
            list.hidden = true;
            wrap.appendChild(list);
            var active = -1;
            function render() {
                var q = input.value.trim().toLowerCase();
                list.innerHTML = '';
                active = -1;
                var shown = items.filter(function (x) { return q === '' || String(x).toLowerCase().indexOf(q) !== -1; });
                shown.forEach(function (x) {
                    var b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'tb-suggest-item';
                    b.setAttribute('role', 'option');
                    b.textContent = x;
                    b.addEventListener('mousedown', function (e) { e.preventDefault(); choose(x); });
                    list.appendChild(b);
                });
                if (!shown.length) {
                    var none = document.createElement('div');
                    none.className = 'tb-suggest-empty';
                    none.textContent = '找不到符合的選項';
                    list.appendChild(none);
                }
                open(true);
            }
            function open(on) { list.hidden = !on; input.setAttribute('aria-expanded', on ? 'true' : 'false'); }
            function choose(x) {
                input.value = x;
                open(false);
                if (input.hasAttribute('data-suggest-submit') && input.form) {
                    if (typeof input.form.requestSubmit === 'function') input.form.requestSubmit(); else input.form.submit();
                }
            }
            function move(step) {
                var opts = list.querySelectorAll('.tb-suggest-item');
                if (!opts.length) return;
                active = (active + step + opts.length) % opts.length;
                opts.forEach(function (o, i) { o.classList.toggle('is-active', i === active); });
                opts[active].scrollIntoView({ block: 'nearest' });
            }
            input.addEventListener('focus', render);
            input.addEventListener('click', function () { if (list.hidden) render(); });
            input.addEventListener('input', render);
            input.addEventListener('blur', function () { open(false); });
            input.addEventListener('keydown', function (e) {
                if (e.key === 'ArrowDown') { e.preventDefault(); if (list.hidden) render(); move(1); }
                else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
                else if (e.key === 'Escape') { open(false); }
                else if (e.key === 'Enter' && !list.hidden && active >= 0) {
                    e.preventDefault();
                    choose(list.querySelectorAll('.tb-suggest-item')[active].textContent);
                }
            });
        });
    })();
    </script>
    {{-- 載入 Bootstrap 的 JavaScript（下拉選單、側邊滑出選單、可收合群組都靠它）。 --}}
    <script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
    @auth
        <script>
            // 「返回」：上一頁是本站的頁面才用瀏覽器的上一頁；否則維持連結本身（回主控台）。
            (function () {
                var back = document.querySelector('[data-page-back]');
                if (!back) { return; }
                back.addEventListener('click', function (event) {
                    var sameSite = false;
                    try { sameSite = document.referrer !== '' && new URL(document.referrer).origin === location.origin; } catch (e) {}
                    if (sameSite && history.length > 1) {
                        event.preventDefault();
                        history.back();
                    }
                });
            })();
        </script>
    @endauth
    {{-- 分步驟表單（洋蔥式）：表單裡有兩段以上標了 data-step 的欄位時，自動變成一步一步填。 --}}
    @include('layouts.partials.form-stepper')
    {{-- 篩選列的洋蔥式：一長排篩選欄位收成「篩選條件」摘要列，點開從右邊滑入、直向排列。 --}}
    @include('layouts.partials.filter-onion')
    {{-- 清單的洋蔥式檢視：列表頁的寬表格變成一列一列的簡短清單，點進去詳細內容從右邊滑入。 --}}
    @include('layouts.partials.list-onion')
    {{-- 表格的一列加上 data-modal-frame-href（與 data-modal-title）：點這一列（或按 Enter）就在畫面中的對話框打開那個網址，
         跟 data-modal-frame 連結一樣；清單模式點一筆也一樣（list-onion 會呼叫 tbOpenRow）。
         在對話框裡面（例如「我的申請」視窗）點一列：在那個對話框上面再開一個對話框。點到列裡的按鈕、連結不算。 --}}
    <script>
    (function () {
        window.tbOpenRow = function (tr) {
            var href = tr.getAttribute('data-modal-frame-href');
            if (!href) return;
            if (window.parent !== window) {   // 已經在對話框裡（例如對話框裡的清單）：請外面的對話框疊一層打開，關掉這一層會回到原本的清單
                window.parent.postMessage({ type: 'modal-push', url: new URL(href, window.location.href).href, title: tr.getAttribute('data-modal-title') || '', size: tr.getAttribute('data-modal-size') || '' }, window.location.origin);
                return;
            }
            var a = document.createElement('a');
            a.href = href;
            a.setAttribute('data-modal-frame', '');
            a.setAttribute('data-modal-title', tr.getAttribute('data-modal-title') || '');
            if (tr.hasAttribute('data-modal-size')) a.setAttribute('data-modal-size', tr.getAttribute('data-modal-size'));
            a.hidden = true;
            (tr.querySelector('td') || document.body).appendChild(a);   // 放在列裡面：從另一個視窗（例如低庫存品項）打開時，會先關掉那個視窗
            a.click();
            a.remove();
        };
        document.addEventListener('click', function (event) {
            var tr = event.target.closest && event.target.closest('tr[data-modal-frame-href]');
            if (!tr || event.target.closest('a, button, input, label, select, textarea, summary, details, form')) return;
            window.tbOpenRow(tr);
        });
        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter' || !event.target.matches || !event.target.matches('tr[data-modal-frame-href]')) return;
            event.preventDefault();
            window.tbOpenRow(event.target);
        });
        // Bootstrap 視窗打開後（例如低庫存品項），讓表格／清單自動切換依視窗內的寬度重新判斷。
        document.addEventListener('shown.bs.modal', function () { window.dispatchEvent(new Event('resize')); });
    })();
    </script>
    {{-- 所有表格／清單依畫面大小自動切換：螢幕夠大用表格，不夠用清單（不用左右滑動）。（同步 integrate/all 85ddc1a） --}}
    @include('layouts.partials.auto-table')
    {{-- 讓列表頁剛好一個畫面：電腦版清單在固定外框內捲動，手機版一次只顯示放得下的幾筆並用上一頁／下一頁切換。 --}}
    @include('layouts.partials.fit-screen')
    {{-- 表單防呆：防連點、離開提醒、自動暫存（見檔頭說明）。 --}}
    @include('layouts.partials.form-guard')

    {{-- 「在畫面中」開啟的表單（例如零件履歷的「新增採購」「修改」）：連結加上 data-modal-frame，點了不換頁，
         而是在目前畫面中央的對話框裡打開（iframe 載入同一個網址，加上 ?modal=1 用精簡版面）。
         送出成功後整頁跳到結果頁（App\Http\Middleware\EmbeddedModal）；按「取消」或點對話框外面就關掉。 --}}
    @if ($embedded)
        <script>
        (function () {
            if (window.parent === window) return;
            function post(msg) { window.parent.postMessage(msg, window.location.origin); }
            // 表單送出時帶著 modal=1，失敗時才會回到對話框裡的表單。
            document.querySelectorAll('form').forEach(function (form) {
                if (form.querySelector('input[name="modal"]')) return;
                var hidden = document.createElement('input');
                hidden.type = 'hidden'; hidden.name = 'modal'; hidden.value = '1';
                form.appendChild(hidden);
            });
            // 對話框右上角已經有 ✕ 可以關閉：表單旁邊的「取消」按鈕就不顯示（頁面裡的小視窗、確認視窗裡的「取消」不動）。
            document.querySelectorAll('.app-content a.btn, .app-content button.btn[type="button"]').forEach(function (b) {
                if (b.textContent.trim() !== '取消' || b.closest('.modal, dialog')) return;
                b.hidden = true;
                b.classList.add('d-none');
            });
            // 點一般連結（例如「取消」）：交給外面的頁面處理，對話框裡不換頁。
            document.addEventListener('click', function (event) {
                var a = event.target.closest && event.target.closest('a[href]');
                if (!a || a.target === '_blank' || a.hasAttribute('data-bs-toggle') || a.getAttribute('href').charAt(0) === '#') return;
                // data-modal-stay（例如對話框裡清單的分頁）：就在對話框裡換頁（分頁連結本來就帶著 modal=1）。
                if (a.closest('[data-modal-stay]')) return;
                // 對話框裡的 data-modal-frame 連結（例如報修案件裡的「填寫維修紀錄」）：在同一個對話框疊一層打開，關掉回到原本那一層。
                if (a.hasAttribute('data-modal-frame')) {
                    event.preventDefault();
                    post({ type: 'modal-push', url: a.href, title: a.getAttribute('data-modal-title') || a.textContent.trim(), size: a.getAttribute('data-modal-size') || '' });
                    return;
                }
                event.preventDefault();
                post({ type: 'modal-navigate', url: a.href });
            });
            // 告訴外面的對話框「表單內容」有多高（量內容本身，不是整個頁面；頁面高度會被對話框撐開，量它會留下一大段空白），
            // 對話框就剛好到「儲存／取消」那一列為止，也不會出現兩層捲軸。
            var content = document.querySelector('.app-content') || document.body;
            // 量內容區裡最後一個東西的底部（加上內容區下方留白）：內容區本身會被拉高到填滿對話框，
            // 量它的話對話框只會變高不會變矮（例如對話框變寬、表格變矮之後留下一大段空白）。
            function size() {
                var bottom = 0;
                Array.prototype.forEach.call(content.children, function (child) {
                    if (child.tagName === 'SCRIPT' || child.tagName === 'STYLE' || child.offsetParent === null) return;
                    bottom = Math.max(bottom, child.getBoundingClientRect().bottom);
                });
                if (!bottom) bottom = content.getBoundingClientRect().bottom;
                else bottom += parseFloat(getComputedStyle(content).paddingBottom) || 0;
                post({ type: 'modal-size', height: Math.ceil(bottom + window.scrollY) });
            }
            window.addEventListener('load', size);
            if (window.ResizeObserver) {
                var observer = new ResizeObserver(size);
                observer.observe(content);
                Array.prototype.forEach.call(content.children, function (child) { observer.observe(child); });
            }
        })();
        </script>
    @else
        <div class="modal fade" id="frameModal" tabindex="-1" aria-labelledby="frameModalTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title h5" id="frameModalTitle"></h2>
                        {{-- 從列表點一列打開時：這一列「操作」欄的按鈕（修改、停用、刪除…）也放在標題旁邊，
                             清單檢視（沒有操作欄）也能直接操作。內容由下面的程式從那一列複製過來。 --}}
                        <div class="frame-modal-actions" id="frameModalActions" hidden></div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="關閉"></button>
                    </div>
                    <div class="modal-body p-0">
                        <iframe id="frameModalFrame" title="" style="width: 100%; height: 240px; border: 0; display: block;"></iframe>
                    </div>
                </div>
            </div>
        </div>
        {{-- 上層對話框：在對話框標題旁按「修改」時，直接在原本的對話框（例如設備詳細）上面再開一個，
             不用把詳細頁換成修改頁；修改送出後關掉上層，下面的詳細頁重新載入顯示改好的資料。 --}}
        <div class="modal fade" id="frameModalTop" tabindex="-1" aria-labelledby="frameModalTopTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title h5" id="frameModalTopTitle"></h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="關閉"></button>
                    </div>
                    <div class="modal-body p-0">
                        <iframe id="frameModalTopFrame" title="" style="width: 100%; height: 240px; border: 0; display: block;"></iframe>
                    </div>
                </div>
            </div>
        </div>
        <script>
        (function () {
            var modalEl = document.getElementById('frameModal');
            var frame = document.getElementById('frameModalFrame');
            var title = document.getElementById('frameModalTitle');
            var dialog = modalEl.querySelector('.modal-dialog');
            var stack = [];   // 對話框裡疊上去的各層（見 modal-push）
            var actionsBox = document.getElementById('frameModalActions');
            function modal() { return window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(modalEl) : null; }
            // 標題旁的操作按鈕：複製這一列最後一格（操作欄）裡的按鈕、表單；連到對話框本身那一頁的（例如修改頁裡的「修改」）不放。
            // 表單（停用、刪除…）照常在這一頁送出（有 data-confirm 的一樣先跳確認視窗）；「修改」這類 data-modal-frame 連結在對話框裡疊一層打開。
            function setActions(tr, url) {
                actionsBox.innerHTML = '';
                var cell = tr && tr.lastElementChild;
                if (cell && cell.querySelector('a, button, form')) {
                    Array.prototype.forEach.call(cell.querySelectorAll('a[href], form, .btn.disabled'), function (el) {
                        if (el.parentElement.closest('form') || (el.matches('.btn.disabled') && el.closest('a, form'))) return;
                        if (el.matches('a') && (el.hidden || new URL(el.href, window.location.href).pathname === url.pathname)) return;
                        // 不能按的「已回報」這種文字標籤不放；表單的按鈕是停用狀態的（例如不能停用、刪除自己的帳號、系統內建身分不能刪除）
                        // 照樣放上去、顯示成灰色不能按，跟表格檢視的操作欄一樣，看得出有這個功能、只是現在不能用。
                        if (el.matches('.btn.disabled')) return;
                        var copy = el.cloneNode(true);
                        copy.removeAttribute('id');
                        copy.querySelectorAll('[id]').forEach(function (x) { x.removeAttribute('id'); });
                        actionsBox.appendChild(copy);
                    });
                }
                showActions();
            }
            function showActions() { actionsBox.hidden = stack.length > 0 || !actionsBox.children.length; }
            // 對話框裡的頁面本身已經有同一個按鈕（例如保養工單詳細的「回報保養結果」）：標題旁就不重複放。
            // 對話框裡的頁面標了 data-modal-header 的按鈕（例如保養紀錄的「回報保養結果」）：搬到標題列 ✕ 左邊（頁面裡那一個在對話框裡本來就藏起來）。
            var hoistedPaths = [];
            frame.addEventListener('load', function () {
                setTimeout(function () { contentReady(modalEl, '_tbLoaded'); }, 0);   // 下面先整理標題旁的按鈕，再顯示
                if (stack.length) return;
                var doc;
                try { doc = frame.contentDocument; } catch (e) { return; }
                if (!doc) return;
                // 上一次搬上來的、以及從列表那一列複製來的同一個按鈕先拿掉（例如回報完之後，頁面已經沒有這個按鈕了）。
                actionsBox.querySelectorAll('[data-hoisted]').forEach(function (x) { x.remove(); });
                actionsBox.querySelectorAll('a[href]').forEach(function (x) { if (hoistedPaths.indexOf(x.pathname) !== -1) x.remove(); });
                hoistedPaths = [];
                var hoist = Array.prototype.slice.call(doc.querySelectorAll('.app-content [data-modal-header]'));
                var paths = Array.prototype.map.call(doc.querySelectorAll('.app-content a[href]'), function (x) { return x.pathname; });
                actionsBox.querySelectorAll('a[href]').forEach(function (x) { if (paths.indexOf(x.pathname) !== -1) x.remove(); });
                hoist.forEach(function (el) {
                    var copy = document.importNode(el, true);
                    copy.removeAttribute('data-modal-header');
                    copy.removeAttribute('id');
                    copy.classList.remove('btn-sm');
                    copy.setAttribute('data-hoisted', '');
                    if (copy.pathname) hoistedPaths.push(copy.pathname);
                    actionsBox.appendChild(copy);
                });
                showActions();
            });
            // 在對話框裡疊一層打開（對話框裡的 data-modal-frame 連結、標題旁的「修改」都用這個）。
            function pushLayer(href, newTitle) {
                stack.push({ src: frame.src, title: title.textContent, width: dialog.style.maxWidth, height: frame.style.height });
                var next = new URL(href, window.location.href);
                next.searchParams.set('modal', '1');
                title.textContent = newTitle || title.textContent;
                frame.title = title.textContent;
                frame.src = next.href;
                showActions();
            }
            // 標題旁的「修改」這類連結：在上層對話框打開（下面的詳細頁保持不動）。
            var topEl = document.getElementById('frameModalTop');
            var topFrame = document.getElementById('frameModalTopFrame');
            var topTitle = document.getElementById('frameModalTopTitle');
            var topDialog = topEl.querySelector('.modal-dialog');
            var changed = false;   // 上層送出過：關掉整個對話框時重新整理列表
            // 對話框剛打開、裡面的頁面還沒載入好時先不顯示（不會先看到一個空白的對話框再跳出內容）：
            // 等裡面的頁面載入完成（標題旁重複的按鈕也整理好）、而且回報過高度（modal-size）才顯示；萬一太久，2.5 秒後也會顯示。
            function waitForContent(el) {
                el.querySelector('.modal-content').classList.add('tb-frame-loading');
                el._tbLoaded = false;
                el._tbSized = false;
                clearTimeout(el._tbLoadTimer);
                el._tbLoadTimer = setTimeout(function () { showContent(el); }, 2500);
            }
            function contentReady(el, what) {
                el[what] = true;
                if (el._tbLoaded && el._tbSized) showContent(el);
            }
            function showContent(el) {
                clearTimeout(el._tbLoadTimer);
                el.querySelector('.modal-content').classList.remove('tb-frame-loading');
            }
            // 對話框裡頁面的最高高度＝視窗高度－對話框上下留白－標題列高度（裡面的頁面自己捲動，對話框本身不出現捲軸）。
            function maxFrameHeight(el) {
                var dlg = el.querySelector('.modal-dialog');
                var head = el.querySelector('.modal-header');
                var margin = dlg ? parseFloat(getComputedStyle(dlg).marginTop) * 2 : 56;
                return Math.max(200, Math.floor(window.innerHeight - margin - (head ? head.offsetHeight : 80) - 4));
            }
            function topModal() { return window.bootstrap.Modal.getOrCreateInstance(topEl); }
            function openTop(a) {
                openTopUrl(a.href, a.getAttribute('data-modal-title') || a.getAttribute('title') || a.textContent.trim(), a.getAttribute('data-modal-size'));
            }
            // 在目前的對話框上面再開一個對話框（下面那個保持不動，關掉上面就回到下面，不用重新載入）。
            function openTopUrl(href, newTitle, size) {
                var url = new URL(href, window.location.href);
                url.searchParams.set('modal', '1');
                topTitle.textContent = newTitle || '';
                topFrame.title = topTitle.textContent;
                topFrame.style.height = '240px';
                topDialog.classList.toggle('modal-lg', size === 'lg');
                topDialog.classList.toggle('modal-xl', size === 'xl');
                waitForContent(topEl);
                topFrame.src = url.href;
                // 下面那個對話框會把游標留在自己裡面（Bootstrap 的 focus trap）：上層打開時先暫停，不然上層的欄位不能打字。
                var under = modal();
                if (under && under._focustrap) under._focustrap.deactivate();
                topModal().show();
            }
            topFrame.addEventListener('load', function () { contentReady(topEl, '_tbLoaded'); });
            topEl.addEventListener('hidden.bs.modal', function () {
                topFrame.src = 'about:blank';
                var under = modal();
                if (under && under._focustrap && modalEl.classList.contains('show')) under._focustrap.activate();
                // Bootstrap 關掉上層時會把整頁的捲動鎖拿掉：下面的對話框還開著，要鎖回去。
                if (modalEl.classList.contains('show')) { document.body.classList.add('modal-open'); document.body.style.overflow = 'hidden'; }
            });
            window.addEventListener('message', function (event) {
                if (event.origin !== window.location.origin || event.source !== topFrame.contentWindow || !event.data) return;
                var data = event.data;
                if (data.type === 'modal-size') {
                    topFrame.style.height = Math.min(data.height, maxFrameHeight(topEl)) + 'px';
                    contentReady(topEl, '_tbSized');
                } else if (data.type === 'modal-push') {
                    var next = new URL(data.url, window.location.href);
                    next.searchParams.set('modal', '1');
                    topFrame.src = next.href;
                } else if (data.type === 'modal-done') {
                    // 送出成功：關掉上層，下面的頁面重新載入（看到改好的資料）。
                    changed = true;
                    topModal().hide();
                    var url = new URL(frame.src, window.location.href);
                    url.searchParams.set('_r', Date.now());
                    frame.src = url.href;
                } else if (data.type === 'modal-navigate') {
                    topModal().hide();
                }
            });
            actionsBox.addEventListener('click', function (event) {
                var a = event.target.closest('a[data-modal-frame]');
                if (!a) return;
                event.preventDefault();
                event.stopPropagation();
                openTop(a);
            });
            document.addEventListener('click', function (event) {
                var a = event.target.closest && event.target.closest('a[data-modal-frame]');
                if (!a || !modal() || event.ctrlKey || event.metaKey || event.shiftKey) return;
                if (actionsBox.contains(a)) return;
                event.preventDefault();
                var url = new URL(a.href, window.location.href);
                url.searchParams.set('modal', '1');
                title.textContent = a.getAttribute('data-modal-title') || a.textContent.trim();
                frame.title = title.textContent;
                frame.style.height = '240px';   // 先用小的高度，表單載入後依內容高度調整（見 modal-size）
                // data-modal-table：外面這一頁的表格正在用表格顯示時，對話框裡也用表格（table=1），寬度跟著表格調整（見 modal-width）。
                if (a.hasAttribute('data-modal-table')) {
                    var pageTable = document.querySelector('.app-content .tb-table-wrap .table-responsive');
                    if (pageTable && pageTable.offsetParent !== null && !pageTable.hidden) url.searchParams.set('table', '1');
                }
                // data-modal-size="lg"／"xl"：內容比較多的表單或詳細頁用比較寬的對話框（Bootstrap 的 modal-lg／modal-xl）。
                dialog.classList.toggle('modal-lg', a.getAttribute('data-modal-size') === 'lg');
                dialog.classList.toggle('modal-xl', a.getAttribute('data-modal-size') === 'xl');
                stack = [];
                setActions(a.closest('tr[data-modal-frame-href]'), url);
                waitForContent(modalEl);
                frame.src = url.href;
                // 從另一個已打開的視窗裡點（例如低庫存品項視窗）：先關掉那個視窗，再打開這個。
                var host = a.closest('.modal.show');
                if (host && host !== modalEl) {
                    host.addEventListener('hidden.bs.modal', function () { modal().show(); }, { once: true });
                    window.bootstrap.Modal.getOrCreateInstance(host).hide();
                    return;
                }
                modal().show();
            });
            window.addEventListener('message', function (event) {
                if (event.origin !== window.location.origin || event.source !== frame.contentWindow || !event.data) return;
                var data = event.data;
                if (data.type === 'modal-size') {
                    // 最高到瀏覽器視窗扣掉對話框標題與上下留白；比較高的表單在對話框裡捲動。
                    var h = Math.min(data.height, maxFrameHeight(modalEl));
                    if (Math.abs(h - parseFloat(frame.style.height || 0)) > 1) frame.style.height = h + 'px';
                    contentReady(modalEl, '_tbSized');
                } else if (data.type === 'modal-push') {
                    // 在對話框裡點一筆（例如申請清單→預約詳細、清單→詳細）：在目前的對話框上面再開一個對話框，
                    // 不把下面那一層換掉（不用等重新載入），關掉上面那個就回到原本的清單。
                    openTopUrl(data.url, data.title, data.size);
                } else if (data.type === 'modal-width') {
                    // 對話框寬度＝表格需要的寬度（加上對話框本身的框線），最寬到瀏覽器視窗左右各留 16px。
                    // 同一次打開只會變寬、不會變窄：捲軸出現／消失會讓量到的寬度差一點點，來回調整會讓對話框晃動。
                    var w = Math.min(data.width + 2, window.innerWidth - 32);
                    var cur = parseFloat(dialog.style.maxWidth || 0);
                    if (!cur || w > cur + 1) dialog.style.maxWidth = w + 'px';
                } else if (data.type === 'modal-done') {
                    var done = new URL(data.url, window.location.href);
                    // 疊上去的一層送出成功、要回到上一層的頁面（例如報修案件裡「填寫維修紀錄」送出後回到案件）：
                    // 留在對話框裡，回到上一層並重新載入（成功訊息會顯示在那一層），不換整頁。
                    if (backToPreviousLayer(done, true)) return;
                    // 回到同一個列表頁（例如在預約列表審核完）：保留目前的篩選（?status=pending）。
                    if (done.pathname === window.location.pathname && !done.search) done.search = window.location.search;
                    window.location.href = done.href;
                } else if (data.type === 'modal-navigate') {
                    var target = new URL(data.url, window.location.href);
                    // 疊上去的一層按「取消」（連到上一層的頁面）：回到上一層，不換整頁。
                    if (backToPreviousLayer(target, false)) return;
                    stack = [];   // 要離開對話框了：不用再回到上一層
                    modal().hide();
                    if (target.pathname !== window.location.pathname) window.location.href = target.href;
                }
            });
            // 目標網址就是上一層的頁面時，回到上一層（reload：重新載入，看到剛送出的結果）；不是的話回傳 false。
            function backToPreviousLayer(target, reload) {
                if (!stack.length) return false;
                var prev = stack[stack.length - 1];
                if (new URL(prev.src, window.location.href).pathname !== target.pathname) return false;
                stack.pop();
                title.textContent = prev.title;
                frame.title = prev.title;
                dialog.style.maxWidth = prev.width;
                frame.style.height = prev.height;
                var url = new URL(prev.src, window.location.href);
                if (reload) url.searchParams.set('_r', Date.now());   // 同一個網址也要重新載入
                frame.src = url.href;
                showActions();
                return true;
            }
            // 有疊上去的一層時，關閉＝回到上一層（例如從詳細回到原本的清單，保留原本的頁數）。
            modalEl.addEventListener('hide.bs.modal', function (event) {
                if (!stack.length) return;
                event.preventDefault();
                var prev = stack.pop();
                title.textContent = prev.title;
                frame.title = prev.title;
                dialog.style.maxWidth = prev.width;
                frame.style.height = prev.height;
                frame.src = prev.src;
                showActions();
            });
            modalEl.addEventListener('hidden.bs.modal', function () {
                frame.src = 'about:blank';
                dialog.style.maxWidth = '';
                dialog.classList.remove('modal-lg', 'modal-xl');
                actionsBox.innerHTML = '';
                actionsBox.hidden = true;
                // 在上層修改過資料：整個對話框關掉後重新整理列表（顯示改好的資料）。
                if (changed) { changed = false; window.location.reload(); }
            });
        })();
        </script>
    @endif
</body>
</html>
