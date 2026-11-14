{{-- 科系主檔列表頁（對應 MajorController::index）：關鍵字與狀態篩選 + 資料表格 + 分頁。 --}}
{{-- 做法和部門主檔相同；多了「年級數」「每年級班數」兩欄，這兩個數字決定空間申請下拉選單有哪些年級與班級。 --}}
@extends('layouts.app')

@section('title', __('majors.index_title'))

@section('content')
<div class="tb">
    <div class="tb-header">
        <div>
            <h1 class="tb-title">{{ __('majors.index_title') }}</h1>
        </div>
        <a class="tb-btn tb-btn-primary" href="{{ route('majors.create') }}" data-modal-frame data-modal-title="{{ __('majors.add_major') }}" data-modal-size="lg">
            <i class="bi bi-plus-lg"></i>{{ __('majors.add_major') }}
        </a>
    </div>

    <div class="tb-card tb-card-pad">
        <p class="text-muted small mb-3">{{ __('majors.intro') }}</p>
        <div>
            <form method="GET" action="{{ route('majors.index') }}" class="filter-bar tb-form-row tb-filter" data-no-onion>
                <div>
                    <label for="keyword" class="form-label small mb-1">{{ __('majors.filter.keyword') }}</label>
                    <input type="text" id="keyword" name="keyword" class="form-control form-control-sm" style="width: 15rem;"
                        value="{{ request('keyword') }}" placeholder="{{ __('majors.filter.keyword_placeholder') }}">
                </div>
                <div>
                    <label for="status" class="form-label small mb-1">{{ __('majors.filter.status') }}</label>
                    <select id="status" name="status" class="form-select form-select-sm" style="width: 8rem;">
                        <option value="">{{ __('majors.filter.status_all') }}</option>
                        @foreach (['active', 'inactive'] as $statusOption)
                            <option value="{{ $statusOption }}" @selected(request('status') === $statusOption)>{{ __('majors.status.' . $statusOption) }}</option>
                        @endforeach
                    </select>
                </div>
                @include('layouts.partials.filter-actions', ['reset' => route('majors.index'), 'fields' => ['keyword', 'status']])
            </form>
        </div>
    </div>

    @if ($majors->isEmpty())
        <div class="tb-card tb-empty">{{ __('majors.empty_list') }}</div>
    @else
        <section class="tb-table-wrap" data-no-fit>
            <div class="table-responsive">
                <table class="tb-table tb-table-hover" data-onion-primary="1">
                    <thead>
                        <tr>
                            <th>{{ __('majors.table.code') }}</th>
                            <th>{{ __('majors.table.name') }}</th>
                            <th class="text-center">{{ __('majors.table.abbreviation') }}</th>
                            <th class="text-center">{{ __('majors.table.grades') }}</th>
                            <th class="text-center">{{ __('majors.table.classes') }}</th>
                            <th>{{ __('majors.table.example') }}</th>
                            <th class="text-center">{{ __('majors.table.reservations') }}</th>
                            <th class="text-center">{{ __('majors.table.status') }}</th>
                            <th class="text-end tb-col-fit">{{ __('majors.table.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($majors as $major)
                            {{-- 點這一列的任何地方都會在畫面中的對話框打開編輯頁。 --}}
                            <tr data-modal-frame-href="{{ route('majors.edit', $major) }}" data-modal-title="{{ __('majors.form.edit_title') }}" data-modal-size="lg" tabindex="0" role="link">
                                <td>{{ $major->major_code ?? '—' }}</td>
                                <td class="fw-semibold">{{ $major->name }}</td>
                                <td class="text-center">{{ $major->abbreviation }}</td>
                                <td class="text-center">{{ $major->grade_count }}</td>
                                <td class="text-center">{{ $major->class_count }}</td>
                                {{-- 例：資一忠 … 資五愛（第一個年級的第一班 到 最後一個年級的最後一班）。 --}}
                                <td>{{ $major->classLabel(1, 1) }} … {{ $major->classLabel($major->grade_count, $major->class_count) }}</td>
                                <td class="text-center">{{ $major->reservations_count }}</td>
                                <td class="text-center">
                                    @if ($major->is_active)
                                        <span class="badge tb-pill tb-pill-ok">{{ __('majors.status.active') }}</span>
                                    @else
                                        <span class="badge tb-pill tb-pill-muted">{{ __('majors.status.inactive') }}</span>
                                    @endif
                                </td>
                                <td class="text-end tb-col-fit">
                                    <div class="d-inline-flex gap-1">
                                        <a class="btn btn-outline-primary btn-sm icon-btn" href="{{ route('majors.edit', $major) }}" data-modal-frame data-modal-title="{{ __('majors.form.edit_title') }}" data-modal-size="lg"
                                            title="{{ __('common.buttons.edit') }}" aria-label="{{ __('common.buttons.edit') }}"><i class="bi bi-pencil"></i></a>

                                        <form method="POST" action="{{ route('majors.toggle', $major) }}"
                                            @if ($major->is_active) data-confirm="{{ __('majors.confirm.deactivate', ['name' => $major->name]) }}" data-confirm-ok="確認停用" @endif>
                                            @csrf
                                            @method('PATCH')
                                            <button class="btn btn-outline-warning btn-sm icon-btn" type="submit"
                                                title="{{ $major->is_active ? __('majors.actions.deactivate') : __('majors.actions.activate') }}"
                                                aria-label="{{ $major->is_active ? __('majors.actions.deactivate') : __('majors.actions.activate') }}">
                                                <i class="bi {{ $major->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                                            </button>
                                        </form>

                                        {{-- 刪除按鈕：送出前先確認；已有空間申請選過這個科系時，後端會擋下並建議改用停用。 --}}
                                        <form method="POST" action="{{ route('majors.destroy', $major) }}"
                                            data-confirm="{{ __('majors.confirm.delete', ['name' => $major->name]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-outline-danger btn-sm icon-btn" type="submit"
                                                title="{{ __('common.buttons.delete') }}" aria-label="{{ __('common.buttons.delete') }}"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <div>
            @include('layouts.pager', ['paginator' => $majors])
        </div>
    @endif
</div>
@endsection
