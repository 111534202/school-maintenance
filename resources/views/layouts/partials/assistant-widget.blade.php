{{-- 右下角「AI 客服」：圓形按鈕 → 對話視窗。登入後每一頁都有（對話框裡的頁面 ?modal=1 不放，見 layouts/app.blade.php）。 --}}
{{-- 回答由 AssistantController@ask 產生；這裡只負責畫面。所有文字都用 textContent 放進畫面，不會執行使用者打的 HTML。 --}}
{{-- 對話只暫存在這個瀏覽器分頁（sessionStorage），換頁還在、關掉分頁就沒有；換人登入會自動清掉。 --}}
<style>
    /* z-index 低於對話框與選單（1040 以上），開啟它們時會被蓋住，不會擋到。 */
    .asst-fab { position: fixed; right: 1.25rem; bottom: 1.25rem; z-index: 1030; width: 3.5rem; height: 3.5rem; border: 0; border-radius: 50%; background: var(--tb-accent); color: #fff; font-size: 1.5rem; display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 6px 18px rgba(0, 0, 0, .25); }
    .asst-fab:hover { background: var(--tb-accent-dark); }
    .asst-fab:focus-visible, .asst-panel button:focus-visible, .asst-panel a:focus-visible { outline: 3px solid var(--tb-accent-dark); outline-offset: 2px; }
    .asst-panel { position: fixed; right: 1.25rem; bottom: 5.5rem; z-index: 1031; width: min(23rem, calc(100vw - 2rem)); height: min(32rem, calc(100vh - 8rem)); height: min(32rem, calc(100dvh - 8rem)); display: flex; flex-direction: column; background: var(--tb-surface); border: 1px solid var(--tb-border); border-radius: 18px; box-shadow: 0 18px 48px rgba(0, 0, 0, .25); overflow: hidden; }
    .asst-panel[hidden] { display: none; }
    .asst-head { display: flex; align-items: center; gap: .5rem; padding: .75rem 1rem; background: var(--tb-accent); color: #fff; }
    .asst-head strong { display: block; line-height: 1.2; }
    .asst-head small { opacity: .9; }
    .asst-head button { border: 0; background: transparent; color: #fff; width: 2.5rem; height: 2.5rem; border-radius: 50%; }
    .asst-head button:hover { background: rgba(255, 255, 255, .15); }
    .asst-log { flex: 1 1 auto; overflow-y: auto; overscroll-behavior: contain; padding: .75rem; background: var(--tb-bg); }
    .asst-msg { max-width: 92%; margin-bottom: .6rem; padding: .55rem .75rem; border-radius: 14px; background: var(--tb-surface); border: 1px solid var(--tb-border); color: var(--tb-text); white-space: pre-line; overflow-wrap: anywhere; font-size: .95rem; }
    .asst-msg.is-me { margin-left: auto; background: var(--tb-accent); border-color: var(--tb-accent); color: #fff; width: fit-content; }
    .asst-item { display: block; margin-top: .5rem; padding: .5rem .65rem; border: 1px solid var(--tb-border); border-radius: 10px; background: var(--tb-accent-faint); color: var(--tb-text); text-decoration: none; }
    .asst-item:hover { background: var(--tb-accent-soft); color: var(--tb-text); }
    .asst-item b { display: block; color: var(--tb-accent-dark); }
    .asst-item span { display: block; font-size: .85rem; color: var(--tb-muted); white-space: pre-line; }
    .asst-btns { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .5rem; white-space: normal; }
    .asst-btns .btn { border-radius: 999px; }
    .asst-form { display: flex; gap: .5rem; padding: .65rem; border-top: 1px solid var(--tb-border); background: var(--tb-surface); }
    .asst-form input { flex: 1 1 auto; min-width: 0; }
    /* 整頁會捲動的頁面（長表單）底部留白，捲到最底時按鈕不會壓到「送出」。 */
    body.asst-pad .app-content { padding-bottom: 5.5rem !important; }
    @media (max-width: 575.98px) {
        .asst-fab { right: 1rem; bottom: 1rem; width: 3.25rem; height: 3.25rem; }
        .asst-panel { right: 1rem; bottom: 5rem; }
    }
    @media print { .asst-fab, .asst-panel { display: none !important; } }
</style>
<button type="button" class="asst-fab" id="asstFab" aria-label="{{ __('assistant.ui.open') }}" aria-expanded="false" aria-controls="asstPanel" title="{{ __('assistant.ui.title') }}"><i class="bi bi-chat-dots-fill" aria-hidden="true"></i></button>
<section class="asst-panel" id="asstPanel" role="dialog" aria-label="{{ __('assistant.ui.title') }}" hidden>
    <div class="asst-head">
        <i class="bi bi-robot fs-4" aria-hidden="true"></i>
        <div class="flex-grow-1"><strong>{{ __('assistant.ui.title') }}</strong><small>{{ __('assistant.ui.subtitle') }}</small></div>
        <button type="button" id="asstClear" aria-label="{{ __('assistant.ui.clear') }}" title="{{ __('assistant.ui.clear') }}"><i class="bi bi-trash3"></i></button>
        <button type="button" id="asstClose" aria-label="{{ __('assistant.ui.close') }}" title="{{ __('assistant.ui.close') }}"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="asst-log" id="asstLog" role="log" aria-live="polite"></div>
    {{-- data-no-guard：這不是資料表單，離開頁面不用提醒「尚未儲存」。 --}}
    <form class="asst-form" id="asstForm" data-no-guard autocomplete="off" novalidate>
        <input type="text" class="form-control" id="asstInput" maxlength="200" placeholder="{{ __('assistant.ui.placeholder') }}" aria-label="{{ __('assistant.ui.question') }}">
        <button type="submit" class="btn btn-primary" id="asstSend" aria-label="{{ __('assistant.ui.send') }}"><i class="bi bi-send-fill"></i></button>
    </form>
</section>
<script>
(function () {
    var T = @json(__('assistant.ui')), STARTERS = @json(__('assistant.suggestions'));
    var URL_ASK = @json(route('assistant.ask', [], false)), TOKEN = @json(csrf_token()), USER = @json(auth()->id());
    var KEY = 'asst-log', MAX = 30;
    var fab = document.getElementById('asstFab'), panel = document.getElementById('asstPanel'), log = document.getElementById('asstLog');
    var form = document.getElementById('asstForm'), input = document.getElementById('asstInput'), send = document.getElementById('asstSend');
    var history = [], busy = false;

    // ---- 暫存：瀏覽器不給存（無痕、停用）也要能用 ----
    function save() { try { sessionStorage.setItem(KEY, JSON.stringify({ user: USER, items: history.slice(-MAX) })); } catch (e) { /* 存不了就不存 */ } }
    function load() {
        try {
            var data = JSON.parse(sessionStorage.getItem(KEY) || 'null');
            if (data && data.user === USER && Array.isArray(data.items)) return data.items;
            sessionStorage.removeItem(KEY);                       // 換人登入：前一個人的對話清掉
        } catch (e) { /* 讀不到就當作沒有 */ }
        return [];
    }

    // ---- 畫面 ----
    function el(tag, cls, text) { var n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; }
    // 只接受本站網址（/ 開頭、不是 //），避免放進奇怪的連結。
    function safe(url) { return typeof url === 'string' && /^\/(?!\/)/.test(url) ? url : null; }
    function draw(m) {
        var box = el('div', 'asst-msg' + (m.me ? ' is-me' : ''), m.text);
        (m.items || []).forEach(function (it) {
            var url = safe(it.url), node = el(url ? 'a' : 'div', 'asst-item');
            if (url) node.href = url;
            node.appendChild(el('b', '', it.title)); node.appendChild(el('span', '', it.text)); box.appendChild(node);
        });
        var btns = el('div', 'asst-btns');
        (m.actions || []).forEach(function (a) { var url = safe(a.url); if (!url) return; var n = el('a', 'btn btn-sm btn-primary', a.label); n.href = url; btns.appendChild(n); });
        (m.suggestions || []).forEach(function (s) { var n = el('button', 'btn btn-sm btn-outline-primary', s); n.type = 'button'; n.addEventListener('click', function () { ask(s); }); btns.appendChild(n); });
        if (btns.children.length) box.appendChild(btns);
        log.appendChild(box); log.scrollTop = log.scrollHeight;
        return box;
    }
    function add(m) { history.push(m); save(); draw(m); }
    function welcome() { draw({ text: T.welcome, suggestions: STARTERS }); }
    function setOpen(open) {
        panel.hidden = !open; fab.setAttribute('aria-expanded', open ? 'true' : 'false');
        fab.setAttribute('aria-label', open ? T.close : T.open);
        fab.firstElementChild.className = open ? 'bi bi-chevron-down' : 'bi bi-chat-dots-fill';
        if (open) { log.scrollTop = log.scrollHeight; input.focus(); } else { fab.focus(); }
    }

    // ---- 送出問題 ----
    function ask(question) {
        question = (question || '').trim();
        if (busy || !question) return;
        busy = true; send.disabled = true; input.value = '';
        add({ me: true, text: question });
        var wait = draw({ text: T.thinking });
        fetch(URL_ASK, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': TOKEN, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ question: question })
        }).then(function (r) {
            if (r.ok) return r.json();
            return { text: r.status === 401 || r.status === 419 ? T.expired : r.status === 429 ? T.too_many : r.status === 422 ? T.invalid : T.error };
        }).catch(function () { return { text: T.error }; }).then(function (m) {
            wait.remove();
            add({ text: m.text || T.error, items: m.items, actions: m.actions, suggestions: m.suggestions });
            busy = false; send.disabled = false;
        });
    }

    form.addEventListener('submit', function (e) { e.preventDefault(); e.stopPropagation(); ask(input.value); });
    fab.addEventListener('click', function () { setOpen(panel.hidden); });
    document.getElementById('asstClose').addEventListener('click', function () { setOpen(false); });
    document.getElementById('asstClear').addEventListener('click', function () { history = []; save(); log.textContent = ''; welcome(); input.focus(); });
    panel.addEventListener('keydown', function (e) { if (e.key === 'Escape') { e.stopPropagation(); setOpen(false); } });

    history = load(); welcome(); history.forEach(draw);
    // 客服按鈕有沒有壓到頁面上的連結或按鈕（框內可以自己捲動的清單不算，捲一下就看得到）。
    function covers() {
        var f = fab.getBoundingClientRect();
        return Array.prototype.some.call(document.querySelectorAll('.app-content a, .app-content button'), function (n) {
            if (!n.offsetParent || n.closest('.fit-scroll')) return false;
            var r = n.getBoundingClientRect();
            return r.left < f.right && r.right > f.left && r.top < f.bottom && r.bottom > f.top;
        });
    }
    // 清單頁會自動縮成剛好一個畫面，等它調整完再看：整頁會捲動、或按鈕被壓住（例如分頁的下一頁），底部就留白。
    window.addEventListener('load', function () { setTimeout(function () {
        var scrolls = document.documentElement.scrollHeight > window.innerHeight + 4 && !document.querySelector('.fit-pager');
        if (scrolls || covers()) document.body.classList.add('asst-pad');
    }, 600); });
})();
</script>
