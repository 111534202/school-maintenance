{{-- 分步驟表單（洋蔥式）：長表單一次只顯示一小段，填完按「下一步」，最後一步才出現送出按鈕。 --}}
{{-- 用法：在表單裡，把每一段欄位包在一個有 data-step="步驟名稱" 的元素裡（至少兩段）就會自動變成分步驟；
     欄位的 name、驗證規則、送出網址都不用改。沒有 JavaScript 時所有欄位會一次全部顯示，仍然可以正常送出。 --}}
<style>
    .stepper-list { display: flex; gap: .5rem; list-style: none; padding: 0; margin: 0; overflow-x: auto; }
    .stepper-pill { display: inline-flex; align-items: center; gap: .4rem; border: 1px solid #dee2e6; background: #fff; border-radius: 2rem; padding: .3rem .8rem .3rem .35rem; color: #6c757d; font-size: .9rem; white-space: nowrap; }
    .stepper-num { width: 1.5rem; height: 1.5rem; border-radius: 50%; background: #e9ecef; display: inline-flex; align-items: center; justify-content: center; font-size: .8rem; }
    .stepper-pill.is-active { border-color: #0d6efd; color: #0d6efd; font-weight: 600; }
    .stepper-pill.is-active .stepper-num { background: #0d6efd; color: #fff; }
    .stepper-pill.is-done .stepper-num { background: #198754; color: #fff; }
    .stepper-bar { height: 4px; background: #e9ecef; border-radius: 2px; margin-top: .6rem; }
    .stepper-bar > span { display: block; height: 100%; width: 0; background: #0d6efd; border-radius: 2px; transition: width .25s ease; }
    /* 手機寬度：只顯示目前這一步的名稱，其他步驟只留圓圈數字。 */
    @media (max-width: 575.98px) { .stepper-pill:not(.is-active) .stepper-label { display: none; } }
    @media (prefers-reduced-motion: reduce) { .stepper-bar > span { transition: none; } }
</style>
<script>
    (function () {
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        document.querySelectorAll('form').forEach(function (form) {
            var steps = Array.prototype.slice.call(form.querySelectorAll('[data-step]'));
            if (steps.length < 2) return;
            init(form, steps);
        });

        function init(form, steps) {
            var cur = 0;
            var last = steps.length - 1;
            var submits = Array.prototype.slice.call(form.querySelectorAll('button[type="submit"], input[type="submit"]'));

            // 上方的步驟列（圓圈數字＋名稱）與進度條。
            var head = document.createElement('div');
            head.className = 'stepper-head mb-3';
            var list = document.createElement('ol');
            list.className = 'stepper-list';
            var pills = steps.map(function (step, i) {
                var li = document.createElement('li');
                var pill = document.createElement('button');
                pill.type = 'button';
                pill.className = 'stepper-pill';
                var num = document.createElement('span');
                num.className = 'stepper-num';
                var label = document.createElement('span');
                label.className = 'stepper-label';
                label.textContent = step.getAttribute('data-step');
                pill.appendChild(num);
                pill.appendChild(label);
                pill.addEventListener('click', function () { tryGo(i); });
                li.appendChild(pill);
                list.appendChild(li);
                return pill;
            });
            var bar = document.createElement('div');
            bar.className = 'stepper-bar';
            bar.appendChild(document.createElement('span'));
            head.appendChild(list);
            head.appendChild(bar);
            steps[0].parentNode.insertBefore(head, steps[0]);

            // 下方的「上一步／下一步」。
            var nav = document.createElement('div');
            nav.className = 'stepper-nav d-flex justify-content-between mt-3';
            nav.innerHTML = '<button type="button" class="btn btn-outline-secondary" data-step-prev><i class="bi bi-arrow-left me-1"></i>上一步</button>' +
                '<button type="button" class="btn btn-primary" data-step-next>下一步<i class="bi bi-arrow-right ms-1"></i></button>';
            var prev = nav.querySelector('[data-step-prev]');
            var next = nav.querySelector('[data-step-next]');
            steps[last].parentNode.insertBefore(nav, steps[last].nextSibling);

            // 這一步的欄位都合格才放行（用瀏覽器內建的驗證，跟原本送出時的檢查一樣）。
            function valid(i) {
                var controls = steps[i].querySelectorAll('input, select, textarea');
                for (var k = 0; k < controls.length; k++) {
                    if (!controls[k].checkValidity()) { controls[k].reportValidity(); return false; }
                }
                return true;
            }

            function go(i, quiet) {
                cur = i;
                steps.forEach(function (s, idx) { s.hidden = idx !== i; });
                pills.forEach(function (p, idx) {
                    p.classList.toggle('is-active', idx === i);
                    p.classList.toggle('is-done', idx < i);
                    p.setAttribute('aria-current', idx === i ? 'step' : 'false');
                    p.querySelector('.stepper-num').innerHTML = idx < i ? '<i class="bi bi-check-lg"></i>' : String(idx + 1);
                });
                bar.firstChild.style.width = ((i + 1) / steps.length * 100) + '%';
                prev.style.visibility = i === 0 ? 'hidden' : 'visible';
                next.hidden = i === last;
                submits.forEach(function (b) { b.hidden = i !== last; });
                if (!quiet) head.scrollIntoView({ block: 'nearest', behavior: reduce ? 'auto' : 'smooth' });
            }

            // 往後跳：中間每一步都要先填對。
            function tryGo(target) {
                if (target <= cur) { go(target); return; }
                for (var k = cur; k < target; k++) {
                    go(k);
                    if (!valid(k)) return;
                }
                go(target);
            }

            prev.addEventListener('click', function () { go(cur - 1); });
            next.addEventListener('click', function () { if (valid(cur)) go(cur + 1); });

            // 在輸入框按 Enter：不是最後一步就當作「下一步」，避免還沒填完就送出。
            form.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter' || cur === last) return;
                var t = e.target;
                if (t.tagName === 'INPUT' && ['submit', 'button', 'checkbox', 'radio', 'file'].indexOf(t.type) === -1) {
                    e.preventDefault();
                    next.click();
                }
            });

            // 送出時若有欄位不合格而且在別的步驟，自動跳到那一步（不然瀏覽器會因為欄位看不到而默默擋住）。
            form.addEventListener('invalid', function (e) {
                for (var idx = 0; idx < steps.length; idx++) {
                    if (steps[idx].contains(e.target)) { if (idx !== cur) go(idx); return; }
                }
            }, true);

            // 伺服器驗證失敗導回來時，停在第一個有錯誤的步驟。
            var start = 0;
            for (var s = 0; s < steps.length; s++) {
                if (steps[s].querySelector('.is-invalid, .invalid-feedback')) { start = s; break; }
            }
            go(start, true);
        }
    })();
</script>
