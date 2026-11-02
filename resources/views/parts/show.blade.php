@extends('layouts.app')

@section('title', '零件履歷：' . $part->displayName())

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">零件履歷：{{ $part->displayName() }}</h1>
        <a href="{{ route('parts.index') }}" class="btn btn-outline-secondary btn-sm">返回列表</a>
    </div>

    {{-- 基本資料：修改基本資料、刪除零件都在這裡（權限與零件庫存相同）。 --}}
    @php($average = $part->averageUnitPrice())
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>基本資料</span>
            <div class="d-flex gap-2">
                <a href="{{ route('parts.edit', $part) }}" class="btn btn-sm btn-outline-primary">修改基本資料</a>
                <form method="POST" action="{{ route('parts.destroy', $part) }}" onsubmit="return confirm('確定要刪除這個零件嗎？採購紀錄也會一起刪除。');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger">刪除零件</button>
                </form>
            </div>
        </div>
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">零件編號</dt>
                <dd class="col-sm-9">{{ $part->part_code }}</dd>

                <dt class="col-sm-3">名稱</dt>
                <dd class="col-sm-9">{{ $part->name }}</dd>

                <dt class="col-sm-3">規格</dt>
                <dd class="col-sm-9">{{ $part->specification ?? '—' }}</dd>

                <dt class="col-sm-3">品牌／型號</dt>
                <dd class="col-sm-9">{{ $part->brandModel() ?? '—' }}</dd>

                <dt class="col-sm-3">目前庫存</dt>
                <dd class="col-sm-9">
                    {{ $part->current_stock }}
                    @if ($part->isLowStock())
                        <span class="badge text-bg-danger">⚠ 低庫存</span>
                    @endif
                </dd>

                <dt class="col-sm-3">安全庫存</dt>
                <dd class="col-sm-9">{{ $part->safety_stock }}</dd>

                <dt class="col-sm-3">平均單價</dt>
                <dd class="col-sm-9">
                    {{ $average === null ? '—' : 'NT$ ' . rtrim(rtrim(number_format($average, 1), '0'), '.') }}
                    <span class="text-muted small">（目前庫存的加權平均；用料成本依先進先出，先扣最早買進的批次）</span>
                </dd>
            </dl>
        </div>
    </div>

    {{-- 採購紀錄：每一筆是一個進貨批次。新增會增加庫存；已被用過的批次不能改數量、單價，也不能刪除。 --}}
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>採購紀錄</span>
            <a href="{{ route('parts.purchases.create', $part) }}" class="btn btn-sm btn-primary">新增採購</a>
        </div>
        @if ($purchases->isEmpty())
            <div class="card-body text-muted small">目前沒有採購紀錄。</div>
        @else
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-nowrap">採購日期</th>
                            <th>廠商</th>
                            <th class="text-end text-nowrap">數量</th>
                            <th class="text-end text-nowrap">單價</th>
                            <th class="text-end text-nowrap">總價</th>
                            <th class="text-end text-nowrap">剩餘數量</th>
                            <th>發票</th>
                            <th>備註</th>
                            <th class="text-end">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($purchases as $purchase)
                            <tr>
                                <td class="text-nowrap">{{ $purchase->purchase_date->format('Y-m-d') }}</td>
                                <td>{{ $purchase->vendor_name ?? '—' }}</td>
                                <td class="text-end">{{ $purchase->quantity }}</td>
                                <td class="text-end text-nowrap">NT$ {{ number_format($purchase->unit_price) }}</td>
                                <td class="text-end text-nowrap">NT$ {{ number_format($purchase->totalPrice()) }}</td>
                                <td class="text-end">{{ $purchase->remaining_quantity }}</td>
                                <td>
                                    @forelse ($purchase->attachments as $attachment)
                                        <a href="{{ route('attachments.show', $attachment) }}" target="_blank" rel="noopener"><i class="bi bi-paperclip"></i>{{ $attachment->file_name }}</a>
                                    @empty
                                        <span class="text-muted">—</span>
                                    @endforelse
                                </td>
                                <td>{{ $purchase->note ?? '—' }}</td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('parts.purchases.edit', [$part, $purchase]) }}" class="btn btn-sm btn-outline-primary">修改</a>
                                    @unless ($purchase->isUsed())
                                        <form method="POST" action="{{ route('parts.purchases.destroy', [$part, $purchase]) }}" class="d-inline"
                                            onsubmit="return confirm('確定要刪除這筆採購紀錄嗎？庫存會減少 {{ $purchase->quantity }}。');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">刪除</button>
                                        </form>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- 使用紀錄：維修（報修單）與保養用掉的零件。跨兩個採購批次的用料會分成兩列，各自顯示那一批的單價。 --}}
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>使用紀錄</span>
            <span class="text-muted small">共使用 {{ $usages->sum('quantity') }} 個</span>
        </div>
        @if ($usages->isEmpty())
            <div class="card-body text-muted small">目前沒有使用紀錄。</div>
        @else
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>來源</th>
                            <th class="text-nowrap">單號</th>
                            <th>狀態</th>
                            <th class="text-nowrap">使用日期</th>
                            <th>執行人</th>
                            <th class="text-end text-nowrap">數量</th>
                            <th class="text-end text-nowrap">單價</th>
                            <th class="text-end text-nowrap">小計</th>
                            <th class="text-nowrap">扣用批次</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($usages as $usage)
                            <tr>
                                <td><span class="badge {{ $usage['source'] === '報修' ? 'text-bg-primary' : 'text-bg-success' }}">{{ $usage['source'] }}</span></td>
                                <td class="text-nowrap">
                                    @if ($usage['link'])
                                        <a href="{{ $usage['link'] }}">{{ $usage['reference_label'] }}</a>
                                    @else
                                        {{ $usage['reference_label'] }}
                                    @endif
                                </td>
                                <td>{{ $usage['status'] ?? '—' }}</td>
                                <td class="text-nowrap">{{ $usage['used_at']?->format('Y-m-d') ?? '—' }}</td>
                                <td>{{ $usage['executor'] ?? '—' }}</td>
                                <td class="text-end">{{ $usage['quantity'] }}</td>
                                <td class="text-end text-nowrap">{{ $usage['unit_price'] === null ? '缺少單價' : 'NT$ ' . number_format($usage['unit_price']) }}</td>
                                <td class="text-end text-nowrap">{{ $usage['unit_price'] === null ? '—' : 'NT$ ' . number_format($usage['quantity'] * $usage['unit_price']) }}</td>
                                <td class="text-nowrap">{{ $usage['batch'] ? $usage['batch']->purchase_date->format('Y-m-d') . ' 採購' : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
