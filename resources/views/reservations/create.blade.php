@extends('layouts.app')

{{-- $guest：免登入查詢課表（/schedule）。同一個頁面，不顯示「我的申請」，點空堂／送出會跳出「若需預約請先登入」。 --}}
@php $guest = $guest ?? false; @endphp

{{-- $showMine：沒有審核權限的人（空間申請＝這一頁）下方顯示「我的申請」；有審核權限的人在空間申請右上角另外有「我的申請」按鈕。 --}}
@section('title', $guest ? '查詢課表' : ($showMine ?? true ? '空間申請' : '空間預約'))

@section('content')
<div class="container-fluid py-4">
    <div class="mb-4">
        @if ($guest)
            <h1 class="h3 mb-1">查詢課表</h1>
            <p class="text-muted mb-0">不用登入即可查詢教室課表；若需預約教室，請先登入。</p>
        @else
            <h1 class="h3 mb-1">{{ ($showMine ?? true) ? '空間申請' : '空間預約' }}</h1>
            <p class="text-muted mb-0">請填寫教室及使用時段，送出後將進入待審核狀態。</p>
        @endif
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-4">
            @if ($classrooms->isEmpty())
                <div class="alert alert-warning mb-0">
                    目前沒有可供預約的教室。請確認教室是否已啟用，以及是否正常開放外借。
                </div>
            @else
                <form method="POST" action="{{ route('reservations.store') }}">
                    @csrf
                    <input type="hidden" id="confirm_pending_conflict"
                           name="confirm_pending_conflict" value="0">

                    {{-- 錯誤訊息（例如時段已經過去）只顯示在頁面最上方那一個（layouts/app），這裡不再重複。 --}}

                    @if (session('pending_conflict'))
                        <div class="alert alert-warning" role="alert">
                            <div class="fw-semibold mb-2">發現時段重疊</div>
                            <p class="mb-3">{{ session('pending_conflict') }}</p>
                            <div class="form-check">
                                <input type="checkbox"
                                       id="confirm_pending_overlap"
                                       class="form-check-input"
                                       required>
                                <label for="confirm_pending_overlap" class="form-check-label">
                                    我已了解此時段已有待審核申請，仍要提出重疊時段的預約。
                                </label>
                            </div>
                        </div>
                    @endif
                    <div class="mb-3">
                        <div>

                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                <button type="button" id="timetable_query" class="btn btn-outline-primary">
                                    查詢課表
                                </button>
                                {{-- 只在出錯時顯示紅字（例如還沒選教室、課表查詢失敗），放在「教室／日期／查詢課表」那一列下面。 --}}
                                <div id="timetable_status" class="small text-danger" role="alert"></div>
                            </div>
                            {{-- 選好教室後，若該教室有設備在等待維修，這裡會顯示提醒（內容由下方程式依 repairNotices 填入）：
                                 一般情況黃色；教室因核心設備維修而暫停預約時紅色（可查課表、不能送出）。 --}}
                            <div id="classroom_repair_notice" class="alert small mt-3 mb-0" role="status" hidden></div>
                            <div id="timetable_result" class="mt-3" hidden></div>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label for="classroom_search" class="form-label">
                                選擇教室 <span class="text-danger">*</span>
                            </label>

                            @php
                                $selectedClassroom = $classrooms->firstWhere('id', old('classroom_id'));
                            @endphp

                            <input
                                type="text"
                                id="classroom_search"
                                class="form-control @error('classroom_id') is-invalid @enderror"
                                placeholder="選擇或輸入教室名稱"
                                autocomplete="off"
                                role="combobox"
                                aria-autocomplete="list"
                                aria-controls="classroom_options"
                                aria-expanded="false"
                                value="{{ $selectedClassroom ? $selectedClassroom->name . '（' . $selectedClassroom->classroom_code . '）' : '' }}"
                                required
                            >

                            <input
                                type="hidden"
                                id="classroom_id"
                                name="classroom_id"
                                value="{{ old('classroom_id') }}"
                            >

                            <div
                                id="classroom_options"
                                class="list-group mt-1"
                                role="listbox"
                                style="display: none; max-height: 240px; overflow-y: auto;"
                            >
                                @foreach ($classrooms as $classroom)
                                    @php
                                        // 選項文字顏色：紅＝暫停預約（核心設備維修中）；黃＝有設備在報修；黑＝正常。
                                        $roomTone = in_array($classroom->id, $suspendedIds, true)
                                            ? 'text-danger fw-semibold'
                                            : (! empty($repairNotices[$classroom->id]) ? 'room-has-repair fw-semibold' : '');
                                    @endphp
                                    <button
                                        type="button"
                                        class="list-group-item list-group-item-action text-start {{ $roomTone }}"
                                        data-tone="{{ $roomTone }}"
                                        role="option"
                                        data-id="{{ $classroom->id }}"
                                        data-search="{{ $classroom->name }} {{ $classroom->classroom_code }}"
                                        data-label="{{ $classroom->name }}（{{ $classroom->classroom_code }}）"
                                    >
                                        {{ $classroom->name }}（{{ $classroom->classroom_code }}）
                                    </button>
                                @endforeach

                                <div id="classroom_no_results" class="list-group-item text-muted" style="display: none;">
                                    找不到符合的教室
                                </div>
                            </div>

                            @error('classroom_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="class_name" class="form-label">
                                班級名稱 <span class="text-danger">*</span>
                            </label>
                            <input
                                type="text"
                                id="class_name"
                                name="class_name"
                                class="form-control @error('class_name') is-invalid @enderror"
                                value="{{ old('class_name') }}"
                                maxlength="100"
                                placeholder="例如:資一忠/護理英文1B班"
                                required
                            >
                            @error('class_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="reservation_date" class="form-label">
                                預約日期 <span class="text-danger">*</span>
                            </label>
                            <input
                                type="date"
                                id="reservation_date"
                                name="reservation_date"
                                class="form-control @error('reservation_date') is-invalid @enderror"
                                value="{{ old('reservation_date', now()->toDateString()) }}"
                                min="{{ now()->toDateString() }}"
                                required
                            >
                            @error('reservation_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-3">
                            <label for="start_time" class="form-label">
                                開始時間 <span class="text-danger">*</span>
                            </label>
                            <input
                                type="time"
                                id="start_time"
                                name="start_time"
                                class="form-control @error('start_time') is-invalid @enderror"
                                value="{{ old('start_time') }}"
                                required
                            >
                            @error('start_time')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-3">
                            <label for="end_time" class="form-label">
                                結束時間 <span class="text-danger">*</span>
                            </label>
                            <input
                                type="time"
                                id="end_time"
                                name="end_time"
                                class="form-control @error('end_time') is-invalid @enderror"
                                value="{{ old('end_time') }}"
                                required
                            >
                            @error('end_time')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label for="purpose" class="form-label">使用用途</label>
                            <textarea
                                id="purpose"
                                name="purpose"
                                class="form-control @error('purpose') is-invalid @enderror"
                                rows="3"
                                maxlength="255"
                                placeholder="請簡述借用教室的用途（選填）"
                            >{{ old('purpose') }}</textarea>
                            @error('purpose')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror

                            {{-- 一次申請好幾節時：其中一節被別人借走（或被拒絕）就整筆都不要。
                                 不勾＝可以只借到其中幾節（審核時逐節同意）；勾了＝只能整筆同意或整筆拒絕。
                                 只選一節時沒有意義，所以選了兩節以上才顯示（見下方 updateNextStepButtonState）。 --}}
                            <div class="form-check mt-3" id="allOrNothingBox" hidden>
                                <input class="form-check-input" type="checkbox" value="1" id="is_all_or_nothing" name="is_all_or_nothing" @checked(old('is_all_or_nothing'))>
                                <label class="form-check-label" for="is_all_or_nothing">
                                    其中一節無法借用時，整筆申請都不要（自動整筆拒絕）
                                </label>
                                <div class="form-text">不勾選的話，可能只借到其中幾節（例如第 5 節已被借走，第 6 節仍可借）。</div>
                            </div>
                        </div>
                    </div>


                    <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
                        <a href="{{ route('reservations.create') }}" class="btn btn-outline-secondary">
                            取消
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send me-1"></i>
                            送出預約申請
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    {{-- 我的申請：自己提出的空間預約申請與目前狀態（表格，螢幕不夠大自動變清單）；點一筆在畫面中的對話框打開「預約詳細」。
         只給沒有審核權限的人；有審核權限的人在空間申請右上角按「我的申請」打開視窗。 --}}
    @if (! $guest && ($showMine ?? true))
    <section id="my-requests" class="tb mt-4" aria-labelledby="my-requests-title">
        <h2 class="h5 mb-0" id="my-requests-title">我的申請</h2>
        @if ($myReservations->isEmpty())
            <div class="tb-card tb-empty">目前還沒有申請紀錄。</div>
        @else
            <section class="tb-table-wrap" data-no-fit aria-label="我的申請">
                @include('reservations._table', ['reservations' => $myReservations, 'mine' => true])
            </section>
            <div>{{ $myReservations->links() }}</div>
        @endif
    </section>
    @endif
</div>

@if ($guest)
    {{-- 未登入時：點空堂、送出，或點到需要登入的頁面，跳出這個視窗（取消／登入）。 --}}
    <div class="modal fade" id="loginRequiredModal" tabindex="-1" aria-labelledby="loginRequiredTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="loginRequiredTitle">需要登入</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="關閉"></button>
                </div>
                <div class="modal-body">若需預約請先登入。</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">取消</button>
                    <a href="{{ route('schedule.login') }}" class="btn btn-primary" data-login-required-ok>登入</a>
                </div>
            </div>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const modalEl = document.getElementById('loginRequiredModal');
        const ask = function (event) {
            event.preventDefault();
            event.stopImmediatePropagation();
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        };
        // 免登入也能開的網址（查詢課表本身、登入）；其他站內連結都要登入。
        const openPaths = ['/schedule', '/schedule/login', '/login'];
        const needsLogin = function (a) {
            if (!a || !a.href || a.target === '_blank' || a.hasAttribute('data-login-required-ok')) return false;
            const url = new URL(a.href, window.location.href);
            return url.origin === window.location.origin && !openPaths.includes(url.pathname);
        };
        // 捕獲階段先攔下：課表的空堂格子（role=button）、送出表單、站內需要登入的連結。
        document.addEventListener('click', function (event) {
            if (event.target.closest('#timetable_result [role="button"]')) return ask(event);
            const a = event.target.closest('a[href]');
            if (needsLogin(a)) return ask(event);
        }, true);
        document.addEventListener('keydown', function (event) {
            if ((event.key === 'Enter' || event.key === ' ') && event.target.closest && event.target.closest('#timetable_result [role="button"]')) ask(event);
        }, true);
        document.addEventListener('submit', function (event) {
            if (event.target.closest('.container-fluid')) ask(event);
        }, true);
    });
    </script>
@endif

<style>
    /* 第一步「教室／日期／查詢課表」：寬度不夠時換行，不用左右滑動。 */
    /* 教室一段、「日期＋查詢課表」一段；寬度不夠時教室自己一行，日期和「查詢課表」永遠在同一行，按鈕靠最右邊。 */
    #reservation_query_row { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 0.5rem; container-type: inline-size; }
    /* 放得下同一列時，多出來的寬度都給「選擇教室」，日期和「查詢課表」緊接在後面，中間不留大片空白。 */
    #reservation_query_row > .rq-classroom { flex: 1 1 240px; min-width: 0; }
    #reservation_query_row > .rq-date-group { flex: 0 1 auto; min-width: 0; display: flex; align-items: flex-end; gap: 0.5rem; }
    .rq-date-group > .rq-date { flex: 0 1 190px; min-width: 0; }
    /* 窄到教室自己一行時，「日期＋查詢課表」佔滿下一行，按鈕靠最右邊。 */
    @container (max-width: 560px) {
        #reservation_query_row > .rq-date-group { flex-grow: 1; }
    }
    .rq-date-group > .rq-controls { flex: 0 0 auto; margin-left: auto; display: flex; align-items: center; }
    /* 很窄的時候（手機）「查詢課表」縮小一點，跟日期擠得下同一行。 */
    @container (max-width: 360px) {
        #timetable_query { padding: 0.3rem 0.75rem; font-size: 0.875rem; }
    }
    /* 教室下拉清單：外框畫在清單本身（圓角），選項只留分隔線。原本外框在每個選項上，
       最後一個看得到的選項不是 :last-child（後面還有隱藏的「找不到符合的教室」），下面兩個角就沒有圓角。 */
    #classroom_options { border: var(--bs-border-width) solid var(--bs-border-color); border-radius: var(--bs-border-radius-lg); background: var(--bs-body-bg); }
    #classroom_options > .list-group-item { border-width: 0 0 var(--bs-border-width); border-radius: 0; }
    #classroom_options > .list-group-item.is-last-visible { border-bottom-width: 0; }
    #timetable_status { margin: -0.5rem 0 0.75rem; }
    #timetable_status:empty { display: none; }

    /* 上一週／教室與週次／下一週：寬的時候排成一列；窄的時候教室與週次在上面置中，下一行上一週在左、下一週在右。 */
    #timetable_result { container-type: inline-size; }
    .tt-weeknav { display: grid; grid-template-columns: auto auto auto; grid-template-areas: "prev head next"; justify-content: start; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; }
    .tt-weeknav .tt-prev { grid-area: prev; justify-self: start; }
    .tt-weeknav .tt-next { grid-area: next; justify-self: end; }
    .tt-weeknav .tt-head { grid-area: head; margin: 0 0.25rem; }
    /* 教室與週次各自不拆開：要換行時只會在「教室」和「日期～日期」之間換行。 */
    .tt-weeknav .tt-head > span { display: inline-block; white-space: nowrap; }
    .tt-weeknav .tt-head > span + span { margin-left: 1em; }
    @container (max-width: 640px) {
        .tt-weeknav { grid-template-columns: auto 1fr auto; grid-template-areas: "head head head" "prev . next"; justify-content: stretch; }
        .tt-weeknav .tt-head { margin: 0; text-align: center; }
    }

    /* 課表：跟著畫面寬度等比例縮小字型與留白，欄位平均分配，不用左右滑動。 */
    .tt-fit { container-type: inline-size; }
    .tt-fit table { width: 100%; table-layout: fixed; margin-bottom: 0; font-size: clamp(0.6rem, 1.45cqi, 1rem); }
    .tt-fit th, .tt-fit td { vertical-align: middle; padding: clamp(2px, 0.7cqi, 0.5rem) !important; white-space: normal !important; overflow-wrap: anywhere; }
    .tt-fit th:first-child { width: 11%; }
    .tt-fit th { text-align: center; }   /* 左邊的節次與時間也置中，跟其他格子一致 */
    .tt-fit .badge { font-size: 0.8em; white-space: normal; }
    .tt-fit .small { font-size: 0.85em; }
    @container (max-width: 640px) { .tt-fit .tt-year { display: none; } }

    /* 有設備在報修的教室：深黃色字（Bootstrap 的亮黃色在白底上看不清楚）。 */
    .room-has-repair { color: #b07800 !important; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('classroom_search');
    const classroomInput = document.getElementById('classroom_id');
    const optionsList = document.getElementById('classroom_options');

    if (!searchInput || !classroomInput || !optionsList) {
        return;
    }

    const options = Array.from(
        optionsList.querySelectorAll('button[data-id]')
    );

    // 教室目前在等待維修的設備：{ 教室編號: [{ text, core }] }；暫停預約的教室編號清單。
    const repairNotices = @json((object) $repairNotices);
    const suspendedIds = @json($suspendedIds).map(String);
    const repairNotice = document.getElementById('classroom_repair_notice');

    function isSuspended() {
        return suspendedIds.includes(String(classroomInput.value));
    }

    // 暫停預約時，第二步的送出按鈕停用並說明原因（後端也會再擋一次）。
    function updateSubmitState() {
        const submit = searchInput.closest('form').querySelector('button[type="submit"]');
        if (!submit) {
            return;
        }
        let note = document.getElementById('suspended_submit_note');
        if (!note) {
            note = document.createElement('div');
            note.id = 'suspended_submit_note';
            note.className = 'alert alert-danger small mb-2 w-100';
            note.textContent = '這間教室的核心設備正在維修，目前暫停預約，無法送出申請。請改選其他教室。';
            submit.parentElement.insertBefore(note, submit.parentElement.firstChild);
        }
        const suspended = isSuspended();
        submit.disabled = suspended;
        note.hidden = !suspended;
    }

    function updateRepairNotice() {
        updateSubmitState();
        applyRoomTone();
        if (!repairNotice) {
            return;
        }
        const items = repairNotices[classroomInput.value] || [];
        const suspended = isSuspended();
        repairNotice.hidden = items.length === 0 && !suspended;
        repairNotice.classList.toggle('alert-warning', !suspended);
        repairNotice.classList.toggle('alert-danger', suspended);
        repairNotice.replaceChildren();
        if (repairNotice.hidden) {
            return;
        }
        const title = document.createElement('div');
        title.className = 'fw-semibold mb-1';
        title.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>';
        title.append(suspended
            ? '這間教室的核心設備正在維修，目前暫停預約（可以查詢課表，但無法送出申請）：'
            : '這間教室目前有設備在報修，使用時可能受影響：');
        repairNotice.appendChild(title);
        if (items.length === 0) {
            const p = document.createElement('div');
            p.textContent = '教室設備異常。';
            repairNotice.appendChild(p);
            return;
        }
        const list = document.createElement('ul');
        list.className = 'mb-0 ps-3';
        items.forEach(function (item) {
            const li = document.createElement('li');
            li.textContent = item.text + (item.core ? '（核心設備）' : '');
            list.appendChild(li);
        });
        repairNotice.appendChild(list);
    }
    const noResults = document.getElementById('classroom_no_results');
    const form = searchInput.closest('form');

    function closeOptions() {
        optionsList.style.display = 'none';
        searchInput.setAttribute('aria-expanded', 'false');
    }

    function filterOptions() {
        const keyword = searchInput.value.trim().toLocaleLowerCase();
        let visibleCount = 0;

        options.forEach(function (option) {
            const text = (
                option.dataset.search + ' ' + option.dataset.label
            ).toLocaleLowerCase();

            const matched = text.includes(keyword);
            option.style.display = matched ? '' : 'none';

            if (matched) {
                visibleCount++;
            }
        });

        noResults.style.display = visibleCount === 0 ? '' : 'none';
        // 最後一個看得到的選項不畫分隔線（下面就是清單外框），避免底部變成兩條線。
        let lastVisible = null;
        options.forEach(function (option) {
            option.classList.remove('is-last-visible');
            if (option.style.display !== 'none') lastVisible = option;
        });
        if (lastVisible) lastVisible.classList.add('is-last-visible');
        optionsList.style.display = '';
        searchInput.setAttribute('aria-expanded', 'true');
    }

    let previousClassroom = null;
    let changingClassroom = false;

    function restorePreviousClassroom() {
        if (!changingClassroom) {
            return;
        }

        if (previousClassroom) {
            classroomInput.value = previousClassroom.id;
            searchInput.value = previousClassroom.label;
        }
        updateRepairNotice();

        changingClassroom = false;
        previousClassroom = null;
    }

    // 輸入框顯示的教室名稱也跟選項同色（紅／黃／黑）。
    function applyRoomTone() {
        const selected = options.find(function (option) {
            return option.dataset.id === classroomInput.value;
        });
        searchInput.classList.remove('text-danger', 'room-has-repair', 'fw-semibold');
        if (selected && selected.dataset.tone) {
            searchInput.classList.add.apply(searchInput.classList, selected.dataset.tone.split(' '));
        }
    }

    function selectClassroom(option) {
        classroomInput.value = option.dataset.id;
        searchInput.value = option.dataset.label;
        searchInput.classList.remove('is-invalid');
        searchInput.setCustomValidity('');
        changingClassroom = false;
        previousClassroom = null;
        closeOptions();
        updateRepairNotice();
    }

    searchInput.addEventListener('focus', function () {
        if (!changingClassroom) {
            previousClassroom = classroomInput.value
                ? {
                    id: classroomInput.value,
                    label: searchInput.value
                }
                : null;

            changingClassroom = true;

            if (previousClassroom) {
                searchInput.value = '';
                classroomInput.value = '';
            }
        }

        filterOptions();
    });

    searchInput.addEventListener('input', function () {
        classroomInput.value = '';
        searchInput.setCustomValidity('');
        filterOptions();
        updateRepairNotice();
    });

    options.forEach(function (option) {
        option.addEventListener('click', function () {
            selectClassroom(option);
        });
    });

    searchInput.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            restorePreviousClassroom();
            closeOptions();
            return;
        }

        if (event.key === 'Enter' && optionsList.style.display !== 'none') {
            event.preventDefault();

            const firstVisible = options.find(function (option) {
                return option.style.display !== 'none';
            });

            if (firstVisible) {
                selectClassroom(firstVisible);
            }
        }
    });

    document.addEventListener('click', function (event) {
        if (
            !searchInput.contains(event.target) &&
            !optionsList.contains(event.target)
        ) {
            restorePreviousClassroom();
            closeOptions();
        }
    });
    const pendingConfirmCheckbox = document.getElementById('confirm_pending_overlap');
    const pendingConfirmInput = document.getElementById('confirm_pending_conflict');

    if (pendingConfirmCheckbox && pendingConfirmInput) {
        pendingConfirmCheckbox.addEventListener('change', function () {
            pendingConfirmInput.value = this.checked ? '1' : '0';
        });
    }
    if (form) {
        form.addEventListener('submit', function (event) {
            const selectedOption = options.find(function (option) {
                return option.dataset.id === classroomInput.value;
            });

            if (!selectedOption) {
                event.preventDefault();
                searchInput.setCustomValidity('請從搜尋結果中選擇一間教室。');
                searchInput.reportValidity();
                searchInput.focus();
                return;
            }

            searchInput.setCustomValidity('');
        });
    }

    searchInput.addEventListener('input', function () {
        searchInput.setCustomValidity('');
    });
    // Timetable query and period selection.
    const timetableButton = document.getElementById('timetable_query');
    const timetableStatus = document.getElementById('timetable_status');
    const timetableResult = document.getElementById('timetable_result');
    const dateInput = document.getElementById('reservation_date');
    const startInput = document.getElementById('start_time');
    const endInput = document.getElementById('end_time');

    // 將既有欄位分成「查詢課表」與「填寫申請」兩步。
    const formRow = searchInput.closest('form').querySelector('.row.g-3');
    const timetableCard = timetableButton.closest('.mb-3');
    const cardBody = timetableCard.firstElementChild;
    const classroomColumn = classroomInput.closest('.col-12.col-md-6');
    const dateColumn = dateInput.closest('.col-12.col-md-6');

    // 將教室清單移至 body，避免被課表卡片或其他父層裁切。
    const optionsPlaceholder = document.createComment('classroom-options');
    optionsList.parentNode.insertBefore(optionsPlaceholder, optionsList);
    document.body.appendChild(optionsList);

    optionsList.style.position = 'fixed';
    optionsList.style.zIndex = '2000';
    optionsList.style.maxHeight = 'min(360px, 60vh)';
    optionsList.style.overflowY = 'auto';
    optionsList.style.boxSizing = 'border-box';

    function positionClassroomOptions() {
        const rect = searchInput.getBoundingClientRect();

        optionsList.style.left = rect.left + 'px';
        optionsList.style.width = rect.width + 'px';
        optionsList.style.top = rect.bottom + 'px';
    }

    searchInput.addEventListener('focus', positionClassroomOptions);
    searchInput.addEventListener('input', positionClassroomOptions);
    window.addEventListener('resize', function () {
        if (optionsList.style.display !== 'none') {
            positionClassroomOptions();
        }
    });
    window.addEventListener('scroll', function () {
        if (optionsList.style.display !== 'none') {
            positionClassroomOptions();
        }
    }, true);
    const queryControls = timetableButton.parentElement;
    const classNameInput = document.getElementById('class_name');
    const purposeInput = document.getElementById('purpose');
    const submitButton = form.querySelector('button[type="submit"]');

    const classNameColumn = classNameInput.closest('.col-12.col-md-6');
    const startColumn = startInput.closest('.col-12.col-md-3');
    const endColumn = endInput.closest('.col-12.col-md-3');
    const purposeColumn = purposeInput.closest('.col-12');
    const infoAlert = form.querySelector('.alert.alert-info.mt-4');
    const actionArea = submitButton.closest('.d-flex.flex-wrap.justify-content-end');

    const step1 = document.createElement('section');
    step1.id = 'reservation_step1';
    step1.className = 'mb-4';

    const step1Heading = document.createElement('h5');
    step1Heading.className = 'mb-3';
    step1Heading.textContent = '第一步：選擇教室、日期與空堂';

    const step2 = document.createElement('section');
    step2.id = 'reservation_step2';
    step2.className = 'mb-4';
    step2.hidden = true;

    const step2Heading = document.createElement('h5');
    step2Heading.className = 'mb-3';
    step2Heading.textContent = '第二步：填寫預約申請資料';

    const backButton = document.createElement('button');
    backButton.type = 'button';
    backButton.className = 'btn btn-outline-secondary mb-3';
    backButton.textContent = '返回課表重新選擇';
    backButton.addEventListener('click', function () {
        step2.hidden = true;
        step1.hidden = false;
        step1.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    const step2Fields = document.createElement('div');
    step2Fields.className = 'row g-3';

    // 教室／日期／查詢課表排成一列，寬度不夠時自動換行（樣式見上方 #reservation_query_row）。
    const queryRow = document.createElement('div');
    queryRow.id = 'reservation_query_row';
    queryRow.className = 'mb-3';

    classroomColumn.className = 'rq-classroom';
    dateColumn.className = 'rq-date';
    queryControls.className = 'rq-controls';
    timetableButton.style.whiteSpace = 'nowrap';

    const dateGroup = document.createElement('div');
    dateGroup.className = 'rq-date-group';
    dateGroup.append(dateColumn, queryControls);
    queryRow.append(classroomColumn, dateGroup);
    cardBody.insertBefore(queryRow, timetableResult);
    cardBody.insertBefore(timetableStatus, timetableResult);   // 錯誤紅字在那一列下面
    // 報修提醒放在「教室／日期／查詢課表」那一列的下面。
    if (repairNotice) {
        cardBody.insertBefore(repairNotice, timetableResult);
    }
    updateRepairNotice();

    form.insertBefore(step1, timetableCard);
    form.insertBefore(step2, timetableCard);

    step1.append(step1Heading, timetableCard);

    step2.append(step2Heading, backButton, step2Fields);

    const reservationTimeFields = document.createElement('div');
    reservationTimeFields.hidden = true;
    reservationTimeFields.append(startColumn, endColumn);
    form.appendChild(reservationTimeFields);

    [startInput, endInput].forEach(function (input) {
        input.required = false;
    });

    step2Fields.append(classNameColumn, purposeColumn);

    if (infoAlert) step2.appendChild(infoAlert);
    if (actionArea) step2.appendChild(actionArea);
    formRow.remove();
    const hasApplicationErrors = [
        classNameInput, startInput, endInput, purposeInput
    ].some(function (input) {
        return input.classList.contains('is-invalid');
    });

    const hasPreviousApplication = Boolean(
        classNameInput.value || startInput.value || endInput.value
    );

    if (hasApplicationErrors || hasPreviousApplication) {
        step1.hidden = true;
        step2.hidden = false;
    }
    function clearTimetable() {
        timetableResult.replaceChildren();
        timetableResult.hidden = true;
        timetableStatus.textContent = '';
    }

    function addScheduleText(parent, tag, value, className) {
        const element = document.createElement(tag);
        element.textContent = value;
        if (className) element.className = className;
        parent.appendChild(element);
        return element;
    }

    // 保存使用者選取的連續節次，不因單次點選而自動跳頁。
    const selectedPeriods = [];

    // 將每個選取節次獨立送往後端，避免非連續節次被合併。
    const selectedSlotsContainer = document.createElement('div');
    selectedSlotsContainer.id = 'selected_reservation_slots';
    selectedSlotsContainer.hidden = true;
    form.appendChild(selectedSlotsContainer);

    const selectedPeriodSummary = document.createElement('p');
    selectedPeriodSummary.className = 'mt-3 mb-2';
    selectedPeriodSummary.setAttribute('aria-live', 'polite');
    selectedPeriodSummary.textContent = '尚未選取時段。';

    const nextStepButton = document.createElement('button');
    nextStepButton.type = 'button';
    nextStepButton.className = 'btn btn-primary mt-2';
    nextStepButton.textContent = '下一步';
    nextStepButton.disabled = true;

    const pendingConfirmationContainer = document.createElement('div');
    pendingConfirmationContainer.className = 'form-check align-items-start gap-2 mt-2';
    pendingConfirmationContainer.hidden = true;

    const pendingConfirmationCheckbox = document.createElement('input');
    pendingConfirmationCheckbox.type = 'checkbox';
    pendingConfirmationCheckbox.id = 'timetable_pending_confirmation';
    pendingConfirmationCheckbox.className = 'form-check-input mt-1 flex-shrink-0';

    const pendingConfirmationLabel = document.createElement('label');
    pendingConfirmationLabel.htmlFor = 'timetable_pending_confirmation';
    pendingConfirmationLabel.className = 'form-check-label';
    pendingConfirmationLabel.textContent = '我已了解此時段已有待審核申請，仍要提出重疊時段的預約。';
    const pendingConfirmationError = document.createElement('div');
    pendingConfirmationError.className = 'small text-danger mt-1';
    pendingConfirmationError.hidden = true;

    pendingConfirmationContainer.append(
        pendingConfirmationCheckbox,
        pendingConfirmationLabel
    );

    function updateNextStepButtonState() {
        const hasPendingSelection = selectedPeriods.some(function (item) {
            return item.hasPending;
        });

        pendingConfirmationContainer.hidden = !hasPendingSelection;

        pendingConfirmationError.textContent = '';
        pendingConfirmationError.hidden = true;
        pendingConfirmationCheckbox.removeAttribute('aria-invalid');
        pendingConfirmationLabel.classList.remove('text-danger');
        pendingConfirmationContainer.style.display = hasPendingSelection ? 'flex' : 'none';

        if (!hasPendingSelection) {
            pendingConfirmationCheckbox.checked = false;
        }

        nextStepButton.disabled = selectedPeriods.length === 0;

        // 「其中一節無法借用時，整筆申請都不要」：選了兩節以上才顯示；只剩一節時收起來並取消勾選。
        const allOrNothingBox = document.getElementById('allOrNothingBox');
        if (allOrNothingBox) {
            const multiple = selectedPeriods.length >= 2;
            allOrNothingBox.hidden = !multiple;
            if (!multiple) {
                document.getElementById('is_all_or_nothing').checked = false;
            }
        }
    }

    pendingConfirmationCheckbox.addEventListener('change', function () {
        updateNextStepButtonState();
    });

    nextStepButton.addEventListener('click', function () {
        if (!selectedPeriods.length) {
            timetableStatus.textContent = '請先選取空堂。';
            return;
        }

        if (
            selectedPeriods.some(function (item) { return item.hasPending; }) &&
            !pendingConfirmationCheckbox.checked
        ) {
            pendingConfirmationCheckbox.setAttribute('aria-invalid', 'true');
            pendingConfirmationLabel.classList.add('text-danger');
            pendingConfirmationError.textContent = '此欄位為必填：請勾選「我已了解此時段已有待審核申請，仍要提出重疊時段的預約」。';
            pendingConfirmationError.hidden = false;
            pendingConfirmationCheckbox.focus();
            return;
        }

        pendingConfirmationCheckbox.removeAttribute('aria-invalid');
        pendingConfirmationError.textContent = '';
        pendingConfirmationError.hidden = true;
        pendingConfirmationLabel.classList.remove('text-danger');

        const ordered = selectedPeriods.slice().sort(function (a, b) {
            return a.date.localeCompare(b.date) ||
                a.periodIndex - b.periodIndex;
        });
        const first = ordered[0];
        const last = ordered[ordered.length - 1];

        dateInput.value = first.date;
        startInput.value = first.period.start_time;
        // 代表時段＝最早的那一節（同一天內的開始與結束）；實際每個時段另外放在 slots[] 送出。
        endInput.value = first.period.end_time;

        selectedSlotsContainer.replaceChildren();
        ordered.forEach(function (item, index) {
            [
                ['reservation_date', item.date],
                ['start_time', item.period.start_time],
                ['end_time', item.period.end_time],
                ['period_number', item.period.period_number]
            ].forEach(function (field) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'slots[' + index + '][' + field[0] + ']';
                input.value = field[1];
                selectedSlotsContainer.appendChild(input);
            });
        });

        let warning = document.getElementById('selected_period_warning');
        if (!warning) {
            warning = document.createElement('div');
            warning.id = 'selected_period_warning';
            warning.className = 'alert alert-warning mb-3';
            step2.insertBefore(warning, step2Fields);
        }



        const groupedByDate = {};

        ordered.forEach(function (item) {
            if (!groupedByDate[item.date]) {
                groupedByDate[item.date] = [];
            }

            groupedByDate[item.date].push(
                periodLabel(item.period)
            );
        });

        let message = '已選取：' +
            Object.keys(groupedByDate).map(function (date) {
                return date + '（' +
                    groupedByDate[date].join('、') + '）';
            }).join('；') +
            '，共 ' + ordered.length + ' 節。';



        warning.textContent = message;
        step1.hidden = true;
        step2.hidden = false;
        step2.scrollIntoView({ behavior: 'smooth', block: 'start' });
        classNameInput.focus({ preventScroll: true });
    });
    // 節次名稱：早自習、第1節…、午休（後端課表設定提供 label；舊資料沒有就用「第 N 節」）。
    function periodLabel(period) {
        return period.label || ('第' + period.period_number + '節');
    }

    // 使用者電腦目前的日期（YYYY-MM-DD）與時間（HH:MM），用來判斷哪些節次已經過去；送出時後端會再檢查一次。
    function pad2(n) { return String(n).padStart(2, '0'); }
    function localToday() {
        const d = new Date();
        return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
    }
    function localNowTime() {
        const d = new Date();
        return pad2(d.getHours()) + ':' + pad2(d.getMinutes());
    }

    // 'YYYY-MM-DD' 加減天數（用 UTC 計算，避免時區造成日期偏移一天）。
    function shiftDate(dateString, days) {
        const parts = dateString.split('-').map(Number);
        const date = new Date(Date.UTC(parts[0], parts[1] - 1, parts[2] + days));
        return date.toISOString().slice(0, 10);
    }

    // 換到指定日期並重新查詢課表（已選取的時段會清空）。
    function goToDate(dateString) {
        dateInput.value = dateString;
        timetableButton.click();
    }

    function renderTimetable(data) {
        timetableResult.replaceChildren();
        selectedPeriods.length = 0;
        pendingConfirmationCheckbox.checked = false;
        updateNextStepButtonState();
        selectedPeriodSummary.textContent = '尚未選取時段。';
        timetableResult.hidden = false;

        const weekdayNames = ['星期一', '星期二', '星期三', '星期四', '星期五', '星期六', '星期日'];
        // 週切換：上一週／下一週（和報修派工的課表視窗相同），切換後直接重新查詢。
        const weekNav = document.createElement('div');
        weekNav.className = 'tt-weeknav';   // 版面見上方 .tt-weeknav（窄的時候上一週、下一週同一行）
        const prevWeek = document.createElement('button');
        prevWeek.type = 'button';
        prevWeek.className = 'btn btn-sm btn-outline-secondary tt-prev';
        prevWeek.innerHTML = '<i class="bi bi-chevron-left"></i> 上一週';
        const nextWeek = document.createElement('button');
        nextWeek.type = 'button';
        nextWeek.className = 'btn btn-sm btn-outline-secondary tt-next';
        nextWeek.innerHTML = '下一週 <i class="bi bi-chevron-right"></i>';
        const heading = document.createElement('h6');
        heading.className = 'mb-0 tt-head';
        // 教室、週次分成兩段（.tt-head > span），窄的時候整段換到下一行，不會把日期拆開。
        const headRoom = document.createElement('span');
        headRoom.textContent = data.classroom.name + '（' + data.classroom.classroom_code + '）';
        const headWeek = document.createElement('span');
        headWeek.textContent = data.week_start + ' ～ ' + data.week_end;
        heading.append(headRoom, headWeek);
        weekNav.append(prevWeek, heading, nextWeek);
        timetableResult.appendChild(weekNav);

        const earliest = dateInput.min || '';
        // 本週（含今天）以前的週不能查詢：上一週跳到今天以前時改跳到今天，整週都在過去就停用。
        prevWeek.disabled = Boolean(earliest) && data.week_start <= earliest;
        prevWeek.addEventListener('click', function () {
            let target = shiftDate(data.selected_date, -7);
            if (earliest && target < earliest) {
                target = earliest;
            }
            goToDate(target);
        });
        nextWeek.addEventListener('click', function () {
            goToDate(shiftDate(data.selected_date, 7));
        });

        addScheduleText(
            timetableResult,
            'p',
            '綠色代表可選空堂；灰色代表課程或已核准預約；黃色代表有待審核預約。點選空堂後進入申請資料。',
            'small text-muted'
        );

        // 課表跟著寬度等比例縮小（.tt-fit），不再固定 1000px 寬、也不用左右滑動。
        const responsive = document.createElement('div');
        responsive.className = 'tt-fit';

        const table = document.createElement('table');
        table.className = 'table table-bordered align-middle text-center';

        const thead = document.createElement('thead');
        thead.className = 'table-light';
        const headerRow = document.createElement('tr');

        // 節次與時間合併成一欄（例如「第1節」下面一行「08:30－09:20」）。
        addScheduleText(headerRow, 'th', '時間', 'text-nowrap');

        data.days.forEach(function (day, index) {
            // 日期：窄的時候省略年份（2026-11-10 → 11-10），見 .tt-year。
            const th = document.createElement('th');
            const year = document.createElement('span');
            year.className = 'tt-year';
            year.textContent = day.date.slice(0, 5);
            th.append(year, day.date.slice(5), document.createElement('br'), weekdayNames[index]);
            headerRow.appendChild(th);
            if (day.date === data.selected_date) {
                th.className += ' table-primary';
            }
        });

        thead.appendChild(headerRow);
        const tbody = document.createElement('tbody');
        const periods = data.days.length ? data.days[0].periods : [];

        periods.forEach(function (basePeriod, periodIndex) {
            const row = document.createElement('tr');
            const timeHeader = addScheduleText(row, 'th', periodLabel(basePeriod), 'text-nowrap');
            addScheduleText(timeHeader, 'div', basePeriod.start_time + '－' + basePeriod.end_time, 'small fw-normal text-muted');

            data.days.forEach(function (day) {
                const period = day.periods[periodIndex];
                const cell = document.createElement('td');

                if (!period) {
                    cell.textContent = '—';
                    row.appendChild(cell);
                    return;
                }

                const reservations = period.reservations || [];
                const hasApproved = reservations.some(function (item) {
                    return item.status === 'approved';
                });
                const pending = reservations.filter(function (item) {
                    return item.status === 'pending';
                });
                const occupied = Boolean(period.is_class) || hasApproved;
                // 時間已過：今天以前的日期，或今天但這一節已經開始（例如現在 13:00，13:00 以前開始的節次都算過去），不能預約。
                const isPast = day.date < localToday() ||
                    (day.date === localToday() && String(period.start_time).slice(0, 5) <= localNowTime());

                if (occupied) {
                    cell.className = 'table-secondary';
                } else if (isPast) {
                    cell.className = 'table-secondary text-muted';   // 時間已過的空堂維持灰底
                } else if (pending.length) {
                    cell.className = 'table-warning';
                } else {
                    cell.className = 'table-success';
                }

                if (period.is_class) {
                    addScheduleText(cell, 'div', period.class_name || '已有課程', 'fw-semibold');
                    addScheduleText(cell, 'div', period.course_name || '課程中', 'small');
                } else if (!hasApproved && !pending.length) {
                    addScheduleText(cell, 'div', '空堂', 'fw-semibold mb-2');
                    if (!isPast) {
                        addScheduleText(cell, 'span', '可預約', 'badge text-bg-success');
                    }
                }

                if (hasApproved) {
                    // 有審核權限的人看得到借用的班級（第一行粗體班級、第二行「已借用」）；其他人只看到「已借用」。
                    // 時間已過但後來補同意的節次，一樣顯示已借用。
                    const approvedItem = reservations.find(function (item) { return item.status === 'approved'; });
                    if (approvedItem && approvedItem.class_name) {
                        addScheduleText(cell, 'div', approvedItem.class_name, 'fw-bold');
                        addScheduleText(cell, 'div', '已借用', 'small mb-2');
                    } else {
                        addScheduleText(
                            cell,
                            'div',
                            '已借用',
                            'small mb-2 fw-bold'
                        );
                    }
                } else if (pending.length) {
                    addScheduleText(
                        cell,
                        'div',
                        '有 ' + pending.length + ' 班申請待審核',
                        'small mb-2'
                    );
                }

                if (!occupied && pending.length && !isPast) {
                    addScheduleText(
                        cell,
                        'span',
                        '可預約',
                        'badge text-bg-success'
                    );
                }

                if (occupied) {
                    // 已有課堂時保留班級與課程資訊，不額外顯示不可選標籤。
                } else if (isPast) {
                    addScheduleText(cell, 'span', '時間已過', 'badge text-bg-light border text-muted');
                } else {
                    cell.style.cursor = 'pointer';
                    cell.title = pending.length
                        ? '目前有待審核預約，仍可選取並繼續申請'
                        : '點選此空堂以選取時段';
                    cell.setAttribute('role', 'button');
                    cell.tabIndex = 0;
                    cell.setAttribute('aria-pressed', 'false');

                    function togglePeriodSelection() {
                        const existingIndex = selectedPeriods.findIndex(function (item) {
                            return item.date === day.date &&
                                item.periodIndex === periodIndex;
                        });

                        if (existingIndex >= 0) {
                            const removed = selectedPeriods[existingIndex];
                            removed.cell.className = removed.originalCellClass;
                            removed.cell.replaceChildren(
                                ...removed.originalCellChildren.map(function (node) {
                                    return node.cloneNode(true);
                                })
                            );
                            removed.cell.setAttribute('aria-pressed', 'false');
                            selectedPeriods.splice(existingIndex, 1);
                        } else {
                            // 同一筆預約允許選取不同日期的空堂。

                            selectedPeriods.push({
                                date: day.date,
                                periodIndex: periodIndex,
                                period: period,
                                hasPending: pending.length > 0,
                                cell: cell,
                                originalCellClass: cell.className,
                                originalCellChildren: Array.from(cell.childNodes).map(
                                    function (node) {
                                        return node.cloneNode(true);
                                    }
                                )
                            });

                            cell.className = 'table-primary';
                            const selectionBadge = cell.querySelector('.badge.text-bg-success');
                            if (selectionBadge) {
                                selectionBadge.textContent = '已選擇';
                                selectionBadge.className = 'badge text-bg-primary';
                            }
                            cell.setAttribute('aria-pressed', 'true');
                        }

                        selectedPeriods.sort(function (a, b) {
                            return a.date.localeCompare(b.date) ||
                                a.periodIndex - b.periodIndex;
                        });

                        updateNextStepButtonState();

                        if (!selectedPeriods.length) {
                            selectedPeriodSummary.textContent = '尚未選取時段。';
                        } else {
                            const groupedByDate = {};
                            selectedPeriods.forEach(function (item) {
                                if (!groupedByDate[item.date]) {
                                    groupedByDate[item.date] = [];
                                }

                                groupedByDate[item.date].push(
                                    periodLabel(item.period)
                                );
                            });

                            selectedPeriodSummary.textContent =
                                '目前選取：' +
                                Object.keys(groupedByDate).map(function (date) {
                                    return date + '（' +
                                        groupedByDate[date].join('、') + '）';
                                }).join('；') +
                                '，共 ' + selectedPeriods.length + ' 節。';
                        }

                        timetableStatus.textContent = '';
                    }

                    cell.addEventListener('click', togglePeriodSelection);
                    cell.addEventListener('keydown', function (event) {
                        if (event.key === 'Enter' || event.key === ' ') {
                            event.preventDefault();
                            togglePeriodSelection();
                        }
                    });
                }

                row.appendChild(cell);
            });

            tbody.appendChild(row);
        });

        table.append(thead, tbody);
        responsive.appendChild(table);
        timetableResult.appendChild(responsive);
        timetableResult.appendChild(selectedPeriodSummary);
        timetableResult.appendChild(nextStepButton);
        timetableResult.appendChild(pendingConfirmationContainer);
        timetableResult.appendChild(pendingConfirmationError);
    }
    timetableButton.addEventListener('click', async function () {
        if (!classroomInput.value) {
            timetableStatus.textContent = '請先從搜尋結果選擇教室。';
            searchInput.focus();
            return;
        }

        if (!dateInput.value) {
            timetableStatus.textContent = '請先選擇預約日期。';
            dateInput.focus();
            return;
        }

        clearTimetable();
        timetableButton.disabled = true;
        timetableStatus.textContent = '';

        try {
            const url = new URL(
                @json($guest ? route('schedule.timetable.week') : route('reservations.timetable.week')),
                window.location.origin
            );
            url.searchParams.set('classroom_id', classroomInput.value);
            url.searchParams.set('date', dateInput.value);

            const response = await fetch(url.toString(), {
                headers: { 'Accept': 'application/json' }
            });
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || '課表查詢失敗，請確認教室及日期。');
            }

            renderTimetable(data);
            timetableStatus.textContent = '';
        } catch (error) {
            timetableStatus.textContent = error.message || '課表查詢失敗，請稍後再試。';
        } finally {
            timetableButton.disabled = false;
        }
    });

    // 搜尋或更換教室時保留目前課表；只有執行新查詢才更新課表。
    dateInput.addEventListener('change', clearTimetable);
    });
</script>

@endsection
