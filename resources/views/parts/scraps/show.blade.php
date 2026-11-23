{{-- 報廢紀錄詳細（唯讀）：登記後就定案，不能修改或取消，只能查看（在零件履歷的對話框開啟，標題「零件編號 報廢紀錄」）。 --}}
@extends('layouts.app')

@section('title', $part->part_code . ' 報廢紀錄')

@section('content')
    <h1 data-modal-hide class="tb-title mb-3">{{ $part->part_code }} 報廢紀錄</h1>

    <div class="card shadow-sm">
        <div class="card-header bg-white form-section-title"><i class="bi bi-trash3 me-2"></i>報廢資料</div>
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">報廢日期</dt>
                <dd class="col-sm-9">{{ $scrap->scrap_date->format('Y-m-d') }}{{ $scrap->scrap_time ? ' ' . substr($scrap->scrap_time, 0, 5) : '' }}</dd>

                <dt class="col-sm-3">登記人</dt>
                <dd class="col-sm-9">{{ $scrap->recorder?->name ?? '—' }}</dd>

                <dt class="col-sm-3">數量</dt>
                <dd class="col-sm-9">{{ $part->withUnit($scrap->quantity) }}</dd>

                <dt class="col-sm-3">損失金額</dt>
                <dd class="col-sm-9">
                    NT$ {{ number_format($scrap->lossAmount()) }}
                    @if ($scrap->hasMissingPrice())<small class="text-muted">（部分缺少單價）</small>@endif
                </dd>

                <dt class="col-sm-3">扣用批次</dt>
                <dd class="col-sm-9">
                    @forelse ($scrap->batches as $batch)
                        <div>{{ $batch->purchase ? $batch->purchase->purchase_date->format('Y-m-d') . ' 採購' : '—' }} × {{ $batch->quantity }}</div>
                    @empty
                        —
                    @endforelse
                </dd>

                <dt class="col-sm-3">原因</dt>
                <dd class="col-sm-9">{{ $scrap->reason }}</dd>

                <dt class="col-sm-3">備註</dt>
                <dd class="col-sm-9">{{ $scrap->note ?? '—' }}</dd>
            </dl>
        </div>
    </div>
    <p class="text-muted small mt-3 mb-0"><i class="bi bi-lock me-1"></i>報廢紀錄登記後就定案，不能修改或取消。</p>
@endsection
