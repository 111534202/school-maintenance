{{-- 教室詳細頁（對應 ClassroomController::show）：教室列表點一列先打開這頁，看得到位置、管理人、設備數、異常設備、進行中工單；
     右上角的鉛筆是修改、開關是啟用／停用（和列表的操作按鈕一樣）。數字的連結開在整個頁面（不在對話框裡）。 --}}
@extends('layouts.app')

@section('title', '教室詳細')

@section('content')
    <div class="page-header mb-4">
        <h1 data-modal-hide class="tb-title mb-0">教室詳細</h1>
        {{-- 在對話框裡打開時：鉛筆搬到標題列（data-modal-header）；啟用／停用開關標題列已經有（從列表那一列帶過來），頁面裡就不再放一個。 --}}
        <div class="d-inline-flex gap-1">
            <a class="btn btn-outline-primary icon-btn" href="{{ route('classrooms.edit', $classroom) }}" data-modal-header data-modal-frame data-modal-title="修改教室" data-modal-size="lg"
                title="{{ __('common.buttons.edit') }}" aria-label="{{ __('common.buttons.edit') }}"><i class="bi bi-pencil"></i></a>
            @unless (request()->boolean('modal'))
                <form method="POST" action="{{ route('classrooms.toggle', $classroom) }}">
                    @csrf
                    @method('PATCH')
                    <button class="btn btn-outline-warning icon-btn" type="submit"
                        title="{{ $classroom->is_active ? __('classrooms.actions.deactivate') : __('classrooms.actions.activate') }}"
                        aria-label="{{ $classroom->is_active ? __('classrooms.actions.deactivate') : __('classrooms.actions.activate') }}">
                        <i class="bi {{ $classroom->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                    </button>
                </form>
            @endunless
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <dl class="row mb-0 gy-2">
                <dt class="col-sm-4 text-muted fw-normal">{{ __('classrooms.table.code') }}</dt>
                <dd class="col-sm-8 mb-0">{{ $classroom->classroom_code }}</dd>

                <dt class="col-sm-4 text-muted fw-normal">{{ __('classrooms.table.name') }}</dt>
                <dd class="col-sm-8 mb-0">{{ $classroom->name }}</dd>

                <dt class="col-sm-4 text-muted fw-normal">{{ __('classrooms.table.department') }}</dt>
                <dd class="col-sm-8 mb-0">{{ $classroom->department->name ?? __('classrooms.no_value') }}</dd>

                <dt class="col-sm-4 text-muted fw-normal">{{ __('classrooms.table.location') }}</dt>
                <dd class="col-sm-8 mb-0">{{ $classroom->campus }} / {{ $classroom->building }} / {{ $classroom->floor }}</dd>

                <dt class="col-sm-4 text-muted fw-normal">{{ __('classrooms.form.type') }}</dt>
                <dd class="col-sm-8 mb-0">{{ $classroom->type ?: __('classrooms.no_value') }}</dd>

                <dt class="col-sm-4 text-muted fw-normal">{{ __('classrooms.table.manager') }}</dt>
                <dd class="col-sm-8 mb-0">{{ $classroom->manager->name ?? __('classrooms.no_value') }}</dd>

                {{-- 三個數字和列表一樣可以點：設備主檔（這間教室的設備／異常設備）、報修看板（教室篩選這間）。 --}}
                <dt class="col-sm-4 text-muted fw-normal">{{ __('classrooms.table.devices') }}</dt>
                <dd class="col-sm-8 mb-0">
                    <a class="text-decoration-none" target="_top" href="{{ route('devices.index', ['classroom_id' => $classroom->id]) }}"
                        title="{{ __('classrooms.links.view_devices') }}">{{ $classroom->devices_count }}</a>
                </dd>

                <dt class="col-sm-4 text-muted fw-normal">{{ __('classrooms.table.abnormal_devices') }}</dt>
                <dd class="col-sm-8 mb-0">
                    @if ($classroom->abnormal_devices_count > 0)
                        <a class="badge tb-pill tb-pill-danger text-decoration-none" target="_top" href="{{ route('devices.index', ['classroom_id' => $classroom->id, 'abnormal' => 1]) }}"
                            title="{{ __('classrooms.links.view_abnormal_devices') }}"><i class="bi bi-exclamation-triangle me-1"></i>{{ $classroom->abnormal_devices_count }}</a>
                    @else
                        <span class="text-muted">0</span>
                    @endif
                </dd>

                <dt class="col-sm-4 text-muted fw-normal">{{ __('classrooms.table.open_repairs') }}</dt>
                <dd class="col-sm-8 mb-0">
                    @if ($classroom->open_repairs_count > 0)
                        <a class="badge tb-pill tb-pill-info text-decoration-none" target="_top" href="{{ route('repairs.index', ['classroom_id' => $classroom->id, 'open' => 1]) }}"
                            title="{{ __('classrooms.links.view_repairs') }}"><i class="bi bi-tools me-1"></i>{{ $classroom->open_repairs_count }}</a>
                    @else
                        <span class="text-muted">0</span>
                    @endif
                </dd>

                <dt class="col-sm-4 text-muted fw-normal">{{ __('classrooms.table.status') }}</dt>
                <dd class="col-sm-8 mb-0">
                    @if ($classroom->is_active)
                        <span class="badge tb-pill tb-pill-ok">{{ __('classrooms.status.active') }}</span>
                    @else
                        <span class="badge tb-pill tb-pill-muted">{{ __('classrooms.status.inactive') }}</span>
                    @endif
                </dd>
            </dl>
        </div>
    </div>
@endsection
