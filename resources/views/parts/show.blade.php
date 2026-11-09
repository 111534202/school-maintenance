@extends('layouts.app')

@section('title', '零件履歷：' . $part->displayName())

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">零件履歷：{{ $part->displayName() }}</h1>
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

                <dt class="col-sm-3">單位</dt>
                <dd class="col-sm-9">{{ $part->unit }}</dd>

                <dt class="col-sm-3">目前庫存</dt>
                <dd class="col-sm-9">
                    {{ $part->withUnit($part->current_stock) }}
                    @if ($part->isLowStock())
                        <span class="badge text-bg-danger">⚠ 低庫存</span>
                    @endif
                </dd>

                <dt class="col-sm-3">安全庫存</dt>
                <dd class="col-sm-9">{{ $part->withUnit($part->safety_stock) }}</dd>

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
                                <td>{{ $purchase->note ?? '—' }}</td>
                                <td class="text-end text-nowrap">
                                    {{-- 附件：在目前畫面用視窗預覽（圖片、PDF），不會另開分頁。 --}}
                                    @if ($attachment = $purchase->attachments->first())
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#attachmentModal"
                                            data-url="{{ route('attachments.show', $attachment) }}" data-mime="{{ $attachment->mime_type }}"
                                            data-title="{{ $purchase->purchase_date->format('Y-m-d') }} {{ $purchase->vendor_name }} 的附件">
                                            <i class="bi bi-paperclip"></i> 附件
                                        </button>
                                    @endif
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

    {{-- 附件預覽視窗：圖片直接顯示、PDF 用內嵌檢視器；其他格式提供下載連結。 --}}
    <div class="modal fade" id="attachmentModal" tabindex="-1" aria-labelledby="attachmentModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="attachmentModalTitle">附件</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="關閉"></button>
                </div>
                <div class="modal-body text-center bg-light" id="attachmentModalBody"></div>
                <div class="modal-footer">
                    <a href="#" id="attachmentModalOpen" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">在新分頁開啟／下載</a>
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">關閉</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('attachmentModal');
        const body = document.getElementById('attachmentModalBody');

        modal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const url = button.dataset.url;
            const mime = button.dataset.mime || '';
            document.getElementById('attachmentModalTitle').textContent = button.dataset.title;
            document.getElementById('attachmentModalOpen').href = url;
            body.innerHTML = '';

            if (mime.startsWith('image/')) {
                const img = document.createElement('img');
                img.src = url;
                img.alt = button.dataset.title;
                img.className = 'img-fluid rounded';
                img.style.maxHeight = '75vh';
                body.appendChild(img);
            } else if (mime === 'application/pdf') {
                const frame = document.createElement('iframe');
                frame.src = url;
                frame.title = button.dataset.title;
                frame.style.width = '100%';
                frame.style.height = '75vh';
                frame.style.border = '0';
                body.appendChild(frame);
            } else {
                body.innerHTML = '<p class="text-muted my-4">這個檔案格式無法在這裡預覽，請按下方「在新分頁開啟／下載」。</p>';
            }
        });

        // 關閉時清空，避免下次打開先閃過上一個附件。
        modal.addEventListener('hidden.bs.modal', function () { body.innerHTML = ''; });
    });
    </script>

    {{-- 使用紀錄：維修（報修單）與保養用掉的零件。跨兩個採購批次的用料會分成兩列，各自顯示那一批的單價。 --}}
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>使用紀錄</span>
            <span class="text-muted small">共使用 {{ $part->withUnit($usages->sum('quantity')) }}</span>
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
    {{-- 報廢紀錄：零件還沒用就壞掉、過期或遺失時登記，從庫存扣掉（先進先出，跟用料一樣）。
         報廢損失＝扣到的各批數量×那一批的單價。登記錯了可以「取消報廢」，數量加回原本的批次。 --}}
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            {{-- 標題旁邊寫共報廢多少（損失金額在每一筆的詳細與匯出裡）；右邊是「登記報廢」。 --}}
            <div class="d-flex flex-wrap align-items-baseline gap-2">
                <span>報廢紀錄</span>
                @if ($scraps->isNotEmpty())
                    <span class="text-muted small">共報廢 {{ $part->withUnit($scraps->sum('quantity')) }}</span>
                @endif
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <a href="{{ route('parts.scraps.create', $part) }}" class="btn btn-sm btn-outline-danger">登記報廢</a>
            </div>
        </div>
        @if ($scraps->isEmpty())
            <div class="card-body text-muted small">目前沒有報廢紀錄。</div>
        @else
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-nowrap">報廢日期</th>
                            <th>原因</th>
                            <th class="text-end text-nowrap">數量</th>
                            <th class="text-end text-nowrap">損失金額</th>
                            <th class="text-nowrap">扣用批次</th>
                            <th>登記人</th>
                            <th>備註</th>
                            <th class="text-nowrap">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($scraps as $scrap)
                            <tr>
                                <td class="text-nowrap">{{ $scrap->scrap_date->format('Y-m-d') }}</td>
                                <td>{{ $scrap->reason }}</td>
                                <td class="text-end text-nowrap">{{ $part->withUnit($scrap->quantity) }}</td>
                                <td class="text-end text-nowrap">
                                    NT$ {{ number_format($scrap->lossAmount()) }}
                                    @if ($scrap->hasMissingPrice())<small class="text-muted">（部分缺少單價）</small>@endif
                                </td>
                                <td class="text-nowrap">
                                    @foreach ($scrap->batches as $batch)
                                        <div>{{ $batch->purchase ? $batch->purchase->purchase_date->format('Y-m-d') . ' 採購' : '—' }} × {{ $batch->quantity }}</div>
                                    @endforeach
                                </td>
                                <td>{{ $scrap->recorder?->name ?? '—' }}</td>
                                <td>{{ $scrap->note ?? '—' }}</td>
                                <td class="text-nowrap">
                                    <form method="POST" action="{{ route('parts.scraps.destroy', [$part, $scrap]) }}" class="d-inline"
                                        data-msg="請問確定要取消 {{ $scrap->scrap_date?->format('Y-m-d') }} 這筆報廢（{{ $part->withUnit($scrap->quantity) }}）嗎？庫存會加回 {{ $part->withUnit($scrap->quantity) }}。" onsubmit="return confirm(this.dataset.msg);">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">取消報廢</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- 匯出：先打開預覽視窗，勾選要匯出的區塊（基本資料、使用紀錄、採購紀錄、報廢紀錄）與欄位，再選 Excel 或 PDF。 --}}
    <div class="d-flex justify-content-end">
        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#partHistoryExportModal">
            <i class="bi bi-download" aria-hidden="true"></i> 匯出 Excel／PDF
        </button>
    </div>

    @include('exports._sections-modal', [
        'modalId' => 'partHistoryExportModal',
        'modalTitle' => '匯出零件履歷',
        'excelUrl' => route('parts.history.export', [$part, 'excel']),
        'pdfUrl' => route('parts.history.export', [$part, 'pdf']),
        'hidden' => [],
        'note' => $part->displayName() . '：勾選要匯出的區塊與欄位，取消勾選的欄位在預覽裡會隱藏。',
        'sections' => $exportSections,
    ])
@endsection
