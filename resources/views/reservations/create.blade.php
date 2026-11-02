@extends('layouts.app')

@section('title', '新增空間預約')

@section('content')
<div class="container-fluid py-4">
    <div class="mb-4">
        <a href="{{ route('reservations.index') }}" class="text-decoration-none">
            <i class="bi bi-arrow-left me-1"></i>
            返回預約列表
        </a>
        <h1 class="h3 mt-3 mb-1">新增空間預約</h1>
        <p class="text-muted mb-0">請填寫教室及使用時段，送出後將進入待審核狀態。</p>
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

                    @if (session('error'))
                        <div class="alert alert-danger" role="alert">
                            {{ session('error') }}
                        </div>
                    @endif

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
                                <span id="timetable_status" class="small text-muted" role="status" aria-live="polite"></span>
                            </div>
                            <div id="timetable_result" class="mt-3" hidden></div>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label for="classroom_search" class="form-label">
                                預約教室 <span class="text-danger">*</span>
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
                                    <button
                                        type="button"
                                        class="list-group-item list-group-item-action text-start"
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
                                value="{{ old('reservation_date') }}"
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
                        </div>
                    </div>


                    <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
                        <a href="{{ route('reservations.index') }}" class="btn btn-outline-secondary">
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
</div>

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

        changingClassroom = false;
        previousClassroom = null;
    }

    function selectClassroom(option) {
        classroomInput.value = option.dataset.id;
        searchInput.value = option.dataset.label;
        searchInput.classList.remove('is-invalid');
        searchInput.setCustomValidity('');
        changingClassroom = false;
        previousClassroom = null;
        closeOptions();
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

    const queryRow = document.createElement('div');
    queryRow.className = 'd-flex flex-nowrap align-items-end gap-2 mb-3';
    queryRow.style.width = '100%';
    queryRow.style.overflowX = 'auto';

    classroomColumn.className = 'flex-grow-1';
    classroomColumn.style.flex = '0 0 280px';
    classroomColumn.style.minWidth = '280px';
    classroomColumn.style.width = '280px';

    dateColumn.className = 'flex-shrink-0';
    dateColumn.style.flex = '0 0 190px';
    dateColumn.style.width = '190px';

    queryControls.className = 'd-flex flex-nowrap align-items-center gap-2 flex-shrink-0';
    timetableButton.style.whiteSpace = 'nowrap';

    queryRow.append(classroomColumn, dateColumn, queryControls);
    cardBody.insertBefore(queryRow, timetableResult);

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
        endInput.value = last.period.end_time;

        selectedSlotsContainer.replaceChildren();
        ordered.forEach(function (item, index) {
            [
                ['reservation_date', item.date],
                ['start_time', item.period.start_time],
                ['end_time', item.period.end_time],
                ['period_no', item.period.period_no]
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
                '第 ' + item.period.period_no + ' 節'
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
    function renderTimetable(data) {
        timetableResult.replaceChildren();
        selectedPeriods.length = 0;
        pendingConfirmationCheckbox.checked = false;
        updateNextStepButtonState();
        selectedPeriodSummary.textContent = '尚未選取時段。';
        timetableResult.hidden = false;

        const weekdayNames = ['星期一', '星期二', '星期三', '星期四', '星期五'];
        const heading = addScheduleText(
            timetableResult,
            'h6',
            data.classroom.name + '（' + data.classroom.classroom_code + '）　' +
                data.week_start + ' ～ ' + data.week_end,
            'mb-2'
        );

        addScheduleText(
            timetableResult,
            'p',
            '綠色代表可選空堂；灰色代表課程或已核准預約；黃色代表有待審核預約。點選空堂後進入申請資料。',
            'small text-muted'
        );

        const responsive = document.createElement('div');
        responsive.className = 'table-responsive';

        const table = document.createElement('table');
        table.className = 'table table-bordered align-middle text-center';
        table.style.minWidth = '900px';

        const thead = document.createElement('thead');
        thead.className = 'table-light';
        const headerRow = document.createElement('tr');

        addScheduleText(headerRow, 'th', '節次', 'text-nowrap');
        addScheduleText(headerRow, 'th', '時間', 'text-nowrap');

        data.days.forEach(function (day, index) {
            const th = addScheduleText(
                headerRow,
                'th',
                day.date + '\n' + weekdayNames[index],
                'text-nowrap'
            );
            th.style.whiteSpace = 'pre-line';
            if (day.date === data.selected_date) {
                th.className += ' table-primary';
            }
        });

        thead.appendChild(headerRow);
        const tbody = document.createElement('tbody');
        const periods = data.days.length ? data.days[0].periods : [];

        periods.forEach(function (basePeriod, periodIndex) {
            const row = document.createElement('tr');
            addScheduleText(row, 'th', '第 ' + basePeriod.period_no + ' 節', 'text-nowrap');
            addScheduleText(
                row,
                'td',
                basePeriod.start_time + '－' + basePeriod.end_time,
                'text-nowrap'
            );

            data.days.forEach(function (day) {
                const period = day.periods[periodIndex];
                const cell = document.createElement('td');
                cell.style.minWidth = '145px';

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
                const today = dateInput.min || dateInput.value;
                const isPast = day.date < today;

                if (occupied) {
                    cell.className = 'table-secondary';
                } else if (pending.length) {
                    cell.className = 'table-warning';
                } else if (isPast) {
                    cell.className = 'text-muted';
                } else {
                    cell.className = 'table-success';
                }

                if (period.is_class) {
                    addScheduleText(cell, 'div', period.class_name || '已有課程', 'fw-semibold');
                    addScheduleText(cell, 'div', period.course_name || '課程中', 'small');
                } else if (!hasApproved && !pending.length) {
                    addScheduleText(cell, 'div', '空堂', 'fw-semibold mb-2');
                    addScheduleText(
                        cell,
                        'span',
                        '可預約',
                        'badge text-bg-success'
                    );
                }

                if (hasApproved) {
                    addScheduleText(
                        cell,
                        'div',
                        '已借用',
                        'small mb-2 fw-bold'
                    );
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
                    addScheduleText(cell, 'span', '日期已過', 'badge text-bg-light mt-1');
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
                                    '第 ' + item.period.period_no + ' 節'
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
        timetableStatus.textContent = '正在查詢課表……';

        try {
            const url = new URL(
                @json(route('reservations.timetable.week')),
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
            timetableStatus.textContent = '課表載入完成。';
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
