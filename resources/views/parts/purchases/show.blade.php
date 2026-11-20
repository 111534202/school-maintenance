{{-- 採購紀錄詳細（唯讀）：送出後的採購紀錄就是定案，不能修改或刪除，只能查看（在零件履歷的對話框開啟）。 --}}
@extends('layouts.app')

@section('title', '採購紀錄：' . $part->part_code)

@section('content')
    <h1 data-modal-hide class="tb-title mb-1">採購紀錄</h1>
    <p class="text-muted mb-3">{{ $part->part_code }} {{ $part->name }}{{ $part->specification ? '（' . $part->specification . '）' : '' }}</p>

    <div class="card shadow-sm">
        <div class="card-header bg-white form-section-title"><i class="bi bi-cart me-2"></i>採購資料</div>
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">採購日期</dt>
                <dd class="col-sm-9">{{ $purchase->purchase_date->format('Y-m-d') }}</dd>

                <dt class="col-sm-3">廠商</dt>
                <dd class="col-sm-9">{{ filled($purchase->vendor_name) ? $purchase->vendor_name : '未填廠商' }}</dd>

                <dt class="col-sm-3">數量</dt>
                <dd class="col-sm-9">{{ $part->withUnit($purchase->quantity) }}</dd>

                <dt class="col-sm-3">單價</dt>
                <dd class="col-sm-9">NT$ {{ number_format($purchase->unit_price) }}</dd>

                <dt class="col-sm-3">總價</dt>
                <dd class="col-sm-9">NT$ {{ number_format($purchase->totalPrice()) }}</dd>

                <dt class="col-sm-3">剩餘數量</dt>
                <dd class="col-sm-9">{{ $part->withUnit($purchase->remaining_quantity) }}</dd>

                <dt class="col-sm-3">備註</dt>
                <dd class="col-sm-9">{{ $purchase->note ?? '—' }}</dd>

                <dt class="col-sm-3">附件</dt>
                <dd class="col-sm-9">
                    @forelse ($purchase->attachments as $attachment)
                        <div><a href="{{ route('attachments.show', $attachment) }}" target="_blank" rel="noopener"><i class="bi bi-paperclip me-1"></i>{{ $attachment->file_name }}</a></div>
                    @empty
                        沒有附件
                    @endforelse
                </dd>
            </dl>
        </div>
    </div>
    <p class="text-muted small mt-3 mb-0"><i class="bi bi-lock me-1"></i>採購紀錄送出後就定案，不能修改或刪除。</p>
@endsection
