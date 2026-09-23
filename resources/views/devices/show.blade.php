@extends('layouts.app')

@section('title', '設備詳細資料')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">設備詳細資料</h3>
        <div>
            <a href="{{ route('devices.edit', $device) }}" class="btn btn-outline-primary">編輯</a>
            <a href="{{ route('devices.index') }}" class="btn btn-outline-secondary">返回列表</a>
        </div>
    </div>

    <div class="card p-4" style="max-width: 720px;">
        <dl class="row mb-0">
            <dt class="col-sm-4">設備編號</dt>
            <dd class="col-sm-8">{{ $device->device_code }}</dd>

            <dt class="col-sm-4">資產編號</dt>
            <dd class="col-sm-8">{{ $device->asset_code ?? '－' }}</dd>

            <dt class="col-sm-4">類別</dt>
            <dd class="col-sm-8">{{ $device->category->name ?? '－' }}</dd>

            <dt class="col-sm-4">品牌 / 型號</dt>
            <dd class="col-sm-8">{{ $device->brand }} {{ $device->model }}</dd>

            <dt class="col-sm-4">序號</dt>
            <dd class="col-sm-8">{{ $device->serial_number ?? '－' }}</dd>

            <dt class="col-sm-4">保固期限</dt>
            <dd class="col-sm-8">{{ optional($device->warranty_until)->format('Y-m-d') ?? '－' }}</dd>

            <dt class="col-sm-4">所在教室</dt>
            <dd class="col-sm-8">
                {{ $device->classroom->room_code ?? '－' }} - {{ $device->classroom->room_name ?? '' }}
                （{{ $device->classroom->department->name ?? '未指定部門' }}）
            </dd>

            <dt class="col-sm-4">狀態</dt>
            <dd class="col-sm-8"><span class="badge bg-info text-dark">{{ $device->status }}</span></dd>

            <dt class="col-sm-4">核心設備</dt>
            <dd class="col-sm-8">
                @if ($device->is_core)
                    <span class="badge bg-warning text-dark">是</span>
                @else
                    <span class="text-muted">否</span>
                @endif
            </dd>
        </dl>
    </div>
@endsection
