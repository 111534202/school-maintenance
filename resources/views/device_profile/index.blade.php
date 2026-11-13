@extends('layouts.app')

@section('title', '設備履歷')

@section('content')
{{-- 風格 B：跟零件庫存一樣的單一搜尋框；表格／清單自動切換，整頁捲動、表頭固定；
     點一列在對話框打開設備履歷，「查看履歷」直接換頁。 --}}
<div class="tb">
    <div class="tb-header">
        <div>
            <h1 class="tb-title">設備履歷</h1>
        </div>
    </div>

    <form method="GET" action="{{ route('device-profile.index') }}" class="tb-form-row tb-form-row-nowrap" role="search">
        <label for="keyword" class="visually-hidden">搜尋（設備編號／資產編號／品牌／型號）</label>
        <input type="text" name="keyword" id="keyword" class="tb-input tb-input-pill" style="flex: 1 1 0; min-width: 0"
            value="{{ request('keyword') }}" placeholder="搜尋設備編號、資產編號、品牌或型號，例如 DEV-0001">
        <button type="submit" class="tb-btn tb-btn-dark">搜尋</button>
        @if (request()->filled('keyword'))
            <a href="{{ route('device-profile.index') }}" class="tb-btn tb-btn-outline">清除</a>
        @endif
    </form>

    @if ($devices->isEmpty())
        <div class="tb-card tb-empty">目前沒有設備資料</div>
    @else
    <section class="tb-table-wrap" data-no-fit>
    <div class="table-responsive">
        <table class="tb-table tb-table-hover" data-onion-primary="3">
            <thead>
                <tr>
                    <th>設備編號</th>
                    <th>類別</th>
                    <th>品牌/型號</th>
                    <th>所在教室</th>
                    <th>狀態</th>
                    <th class="text-end tb-col-fit">操作</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($devices as $device)
                    <tr data-modal-frame-href="{{ route('device-profile.show', $device) }}" data-modal-title="設備履歷" data-modal-size="xl" tabindex="0" role="link">
                        <td class="fw-semibold">{{ $device->device_code }}</td>
                        <td>{{ $device->category?->name ?? '—' }}</td>
                        <td>{{ $device->brand }} {{ $device->model }}</td>
                        <td>{{ $device->classroom?->name ?? '—' }}</td>
                        <td><span class="badge tb-pill tb-pill-muted">{{ $device->status }}</span></td>
                        <td class="text-end tb-col-fit">
                            <a href="{{ route('device-profile.show', $device) }}" class="btn btn-sm btn-outline-primary">查看履歷</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    </section>

    <div>@include('layouts.pager', ['paginator' => $devices])</div>
    @endif
</div>
@endsection
