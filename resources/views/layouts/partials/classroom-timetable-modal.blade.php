{{-- 教室課表挑時間視窗（共用）：顯示某間教室一週的課表，點空堂把日期（或日期＋時間）填進指定的欄位。
     用法：@include('layouts.partials.classroom-timetable-modal', ['classroom' => 教室, 'url' => 課表 JSON 網址, 'mode' => 'datetime' 或 'date', 'busyLabel' => '已排維修', 'busyHint' => '說明'])
     觸發按鈕：data-bs-toggle="modal" data-bs-target="#timetableModal" data-target-input="要填的欄位 id"；同一頁有很多筆時，按鈕可另外帶 data-url 與 data-classroom-name。 --}}
<div class="modal fade" id="timetableModal" tabindex="-1" aria-labelledby="timetableModalTitle" aria-hidden="true"
    data-url="{{ $url }}" data-mode="{{ $mode }}" data-busy-label="{{ $busyLabel }}">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="timetableModalTitle">{{ $classroom->name }}（{{ $classroom->classroom_code }}）課表</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="關閉"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="ttPrev"><i class="bi bi-chevron-left"></i> 上一週</button>
                    <input type="date" class="form-control form-control-sm" id="ttDate" style="width: 11rem;" aria-label="選擇日期">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="ttNext">下一週 <i class="bi bi-chevron-right"></i></button>
                    <span class="small text-muted ms-auto" id="ttRange"></span>
                </div>
                <div class="small mb-2 d-flex flex-wrap gap-3">
                    <span><span class="badge text-bg-success">空堂</span> 可以選</span>
                    <span><span class="badge text-bg-warning">有待審核預約</span> 可以選，但之後可能被核准</span>
                    <span><span class="badge text-bg-secondary">課程／已核准預約</span> 不能選</span>
                    <span><span class="badge text-bg-info">{{ $busyLabel }}</span> {{ $busyHint }}</span>
                </div>
                <div id="ttBody" class="table-responsive"><p class="text-muted my-4 text-center">載入中…</p></div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('timetableModal');
    const body = document.getElementById('ttBody');
    const dateInput = document.getElementById('ttDate');
    let targetInput = null;
    let current = null;   // 目前顯示的這一週任一天（YYYY-MM-DD）

    function shift(dateString, days) {
        const d = new Date(dateString + 'T00:00:00');
        d.setDate(d.getDate() + days);
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    function cell(text, cls) {
        const td = document.createElement('td');
        td.className = 'small ' + (cls || '');
        td.textContent = text;
        td.style.whiteSpace = 'pre-line';
        return td;
    }

    async function load(dateString) {
        body.innerHTML = '<p class="text-muted my-4 text-center">載入中…</p>';
        try {
            const response = await fetch(modal.dataset.url + '?date=' + encodeURIComponent(dateString), { headers: { 'Accept': 'application/json' } });
            const data = await response.json();
            if (!response.ok) { throw new Error(data.message || '課表載入失敗'); }
            current = data.selected_date;
            dateInput.value = data.selected_date;
            document.getElementById('ttRange').textContent = data.week_start + ' ～ ' + data.week_end;
            render(data);
        } catch (error) {
            body.innerHTML = '';
            const p = document.createElement('p');
            p.className = 'text-danger my-4 text-center';
            p.textContent = error.message;
            body.appendChild(p);
        }
    }

    function render(data) {
        const now = new Date();
        const table = document.createElement('table');
        table.className = 'table table-bordered align-middle text-center mb-0';
        const head = table.createTHead().insertRow();
        head.appendChild(Object.assign(document.createElement('th'), { textContent: '節次', className: 'table-light text-nowrap' }));
        data.days.forEach(function (day) {
            head.appendChild(Object.assign(document.createElement('th'), { textContent: day.weekday + ' ' + day.date.slice(5) + ((day.notes && day.notes.length) ? '\n' + day.notes.join('、') : ''), className: 'table-light text-nowrap', style: 'white-space: pre-line;' }));
        });

        const tbody = table.createTBody();
        data.days[0].periods.forEach(function (period, index) {
            const row = tbody.insertRow();
            row.appendChild(cell((period.label || ('第 ' + period.period_number + ' 節')) + '\n' + period.start_time + '–' + period.end_time, 'text-nowrap table-light'));

            data.days.forEach(function (day) {
                const p = day.periods[index];
                const approved = p.reservations.filter(function (r) { return r.status === 'approved'; });
                const pending = p.reservations.filter(function (r) { return r.status === 'pending'; });
                const start = new Date(day.date + 'T' + p.start_time + ':00');
                let td;

                if (p.is_class) {
                    td = cell((p.course_name || '課程') + '\n' + (p.class_name || ''), 'bg-secondary-subtle text-secondary');
                } else if (approved.length) {
                    td = cell('已核准預約\n' + approved[0].class_name, 'bg-secondary-subtle text-secondary');
                } else if (start < now) {
                    td = cell('已過', 'text-muted bg-light');
                } else {
                    let label = '空堂';
                    let cls = 'bg-success-subtle';
                    if (pending.length) { label = '有待審核預約\n' + pending[0].class_name; cls = 'bg-warning-subtle'; }
                    if (p.repairs.length) { label += '\n' + (modal.dataset.busyLabel || '已排') + ' #' + p.repairs[0].id; cls = 'bg-info-subtle'; }
                    td = cell(label, cls + ' tt-free');
                    td.tabIndex = 0;
                    td.setAttribute('role', 'button');
                    td.setAttribute('aria-label', day.date + ' ' + (p.label || ('第 ' + p.period_number + ' 節')) + ' ' + p.start_time + '，選這個時間');
                    const choose = function () {
                        if (targetInput) { targetInput.value = modal.dataset.mode === 'date' ? day.date : day.date + 'T' + p.start_time; }
                        bootstrap.Modal.getInstance(modal).hide();
                    };
                    td.addEventListener('click', choose);
                    td.addEventListener('keydown', function (e) { if (e.key === 'Enter') { choose(); } });
                }
                td.style.whiteSpace = 'pre-line';
                row.appendChild(td);
            });
        });

        body.innerHTML = '';
        body.appendChild(table);
    }

    modal.addEventListener('show.bs.modal', function (event) {
        targetInput = document.getElementById(event.relatedTarget.dataset.targetInput);
        if (event.relatedTarget.dataset.url) { modal.dataset.url = event.relatedTarget.dataset.url; }   // 同一頁有很多筆（例如 AI 候選）時，每顆按鈕各帶自己那間教室的課表網址
        if (event.relatedTarget.dataset.classroomName) { document.getElementById('timetableModalTitle').textContent = event.relatedTarget.dataset.classroomName + ' 課表'; }
        // 已經填過建議時間就從那一週開始看，否則從今天這一週。
        const start = targetInput && targetInput.value ? targetInput.value.slice(0, 10) : (current || shift(new Date().toLocaleDateString('en-CA'), 0));
        load(start);
    });
    document.getElementById('ttPrev').addEventListener('click', function () { load(shift(current, -7)); });
    document.getElementById('ttNext').addEventListener('click', function () { load(shift(current, 7)); });
    dateInput.addEventListener('change', function () { if (dateInput.value) { load(dateInput.value); } });
});
</script>
<style>
    .tt-free { cursor: pointer; }
    .tt-free:hover, .tt-free:focus-visible { outline: 2px solid var(--bs-primary); outline-offset: -2px; }
</style>
