@extends('layouts.app')

@section('title', '零件履歷：' . $part->displayName())

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        {{-- 窄的時候只在「零件履歷：」後面換行，零件編號與名稱整段放到下一行。 --}}
        <h1 class="h3 mb-0">零件履歷：<span class="d-inline-block">{{ $part->displayName() }}</span></h1>
    </div>

    {{-- 基本資料：修改基本資料、刪除零件都在這裡（權限與零件庫存相同）。 --}}
    @php($average = $part->averageUnitPrice())
    {{-- 清單檢視點一筆時的對話框標題：零件名稱（規格）＋ 哪一種紀錄。 --}}
    @php($panelName = $part->name . ($part->specification ? '（' . $part->specification . '）' : ''))
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            {{-- 區塊標題統一用 .tb-section-title（字型、大小、顏色在 theme-b.css 最上面的變數設定）。 --}}
            <h2 class="tb-section-title">基本資料</h2>
            <div class="d-flex gap-2">
                <a href="{{ route('parts.edit', $part) }}" class="btn btn-sm btn-outline-primary" data-modal-frame data-modal-title="修改零件基本資料">修改基本資料</a>
                <form method="POST" action="{{ route('parts.destroy', $part) }}" data-confirm="請問確定要刪除零件「{{ $part->part_code }} {{ $part->name }}」嗎？採購紀錄也會一起刪除。">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger">刪除零件</button>
                </form>
            </div>
        </div>
        <div class="card-body">
            {{-- 名稱與內容固定同一行（.tb-dl，見 theme-b.css）；零件編號、名稱、單位用固定字數，超過以「…」結尾（滑鼠停留看完整）；
                 規格不限字數，同一行放得下就整段顯示，放不下才以「…」結尾；
                 品牌/型號不限字數，太長就換行。 --}}
            <dl class="tb-dl tb-dl-roomy mb-0">
                <dt>零件編號</dt>
                <dd><span class="tb-clip tb-clip-code" title="{{ $part->part_code }}">{{ $part->part_code }}</span></dd>

                <dt>名稱</dt>
                <dd><span class="tb-clip tb-clip-name" title="{{ $part->name }}">{{ $part->name }}</span></dd>

                <dt>規格</dt>
                <dd><span class="tb-clip" title="{{ $part->specification }}">{{ $part->specification ?? '—' }}</span></dd>

                <dt>品牌/型號</dt>
                <dd class="tb-dl-wrap">{{ $part->brandModel() ?? '—' }}</dd>

                <dt>單位</dt>
                <dd><span class="tb-clip tb-clip-unit" title="{{ $part->unit }}">{{ $part->unit }}</span></dd>

                <dt>目前庫存</dt>
                <dd>
                    {{ $part->withUnit($part->current_stock) }}
                    @if ($part->isLowStock())
                        <span class="badge text-bg-danger">⚠ 低庫存</span>
                    @endif
                </dd>

                <dt>安全庫存</dt>
                <dd>{{ $part->withUnit($part->safety_stock) }}</dd>

                <dt>平均單價</dt>
                <dd class="tb-dl-wrap">
                    {{ $average === null ? '—' : 'NT$ ' . rtrim(rtrim(number_format($average, 1), '0'), '.') }}
                </dd>
                {{-- 說明放在「平均單價」的下一行、靠左（寬窄畫面都一樣）。 --}}
                <dd class="tb-dl-full text-muted small">（用料成本依先進先出，先扣最早買進的批次）</dd>
            </dl>
        </div>
    </div>

    {{-- 卡片順序：使用紀錄 → 採購紀錄 → 報廢紀錄。三張表都固定清單（data-onion-list-only，沒有表格檢視），點一筆跳出詳細。 --}}
    {{-- 使用紀錄：維修（報修單）與保養用掉的零件。跨兩個採購批次的用料會分成兩列，各自顯示那一批的單價。 --}}
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h2 class="tb-section-title">使用紀錄</h2>
            <span class="text-muted small">共使用 {{ $part->withUnit($usages->sum('quantity')) }}</span>
        </div>
        @if ($usages->isEmpty())
            <div class="card-body text-muted small">目前沒有使用紀錄。</div>
        @else
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0" data-onion-panel-title="{{ $panelName }}使用紀錄" data-onion-list-only>
                    <thead class="table-light">
                        <tr>
                            {{-- 欄位順序決定清單的標題與副標題：標題＝使用日期，副標題＝狀態 · 單號。 --}}
                            <th class="tb-col-fit">來源</th>
                            <th class="text-nowrap">使用日期</th>
                            <th>狀態</th>
                            <th class="text-nowrap">單號</th>
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
                                <td class="tb-col-fit"><span class="badge {{ $usage['source'] === '報修' ? 'text-bg-primary' : 'text-bg-success' }}">{{ $usage['source'] }}</span></td>
                                <td class="text-nowrap">{{ $usage['used_at']?->format('Y-m-d') ?? '—' }}</td>
                                <td>{{ $usage['status'] ?? '—' }}</td>
                                <td class="text-nowrap">
                                    @if ($usage['link'])
                                        <a href="{{ $usage['link'] }}">{{ $usage['reference_label'] }}</a>
                                    @else
                                        {{ $usage['reference_label'] }}
                                    @endif
                                </td>
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

    {{-- 採購紀錄：每一筆是一個進貨批次。新增會增加庫存；已被用過的批次不能改數量、單價，也不能刪除。 --}}
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h2 class="tb-section-title">採購紀錄</h2>
            <a href="{{ route('parts.purchases.create', $part) }}" class="btn btn-sm btn-outline-primary" data-modal-frame data-modal-title="新增採購紀錄">新增採購</a>
        </div>
        @if ($purchases->isEmpty())
            <div class="card-body text-muted small">目前沒有採購紀錄。</div>
        @else
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0" data-onion-panel-title="{{ $panelName }}採購紀錄" data-onion-list-only>
                    <thead class="table-light">
                        <tr>
                            <th class="text-nowrap">採購日期</th>
                            <th>廠商</th>
                            <th class="text-end text-nowrap">數量</th>
                            <th class="text-end text-nowrap">單價</th>
                            <th class="text-end text-nowrap">總價</th>
                            <th class="text-end text-nowrap">剩餘數量</th>
                            <th>備註</th>
                            {{-- 操作欄只跟按鈕一樣寬（tb-col-fit）：欄位的分隔線剛好在最左邊的按鈕旁邊；標題與按鈕都靠右。 --}}
                            <th class="text-end tb-col-fit">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($purchases as $purchase)
                            <tr>
                                <td class="text-nowrap">{{ $purchase->purchase_date->format('Y-m-d') }}</td>
                                {{-- 沒填廠商也顯示文字（不用「—」），清單的副標題才會固定是「廠商 · 數量」。 --}}
                                <td>{{ filled($purchase->vendor_name) ? $purchase->vendor_name : '未填廠商' }}</td>
                                <td class="text-end text-nowrap">{{ $part->withUnit($purchase->quantity) }}</td>
                                <td class="text-end text-nowrap">NT$ {{ number_format($purchase->unit_price) }}</td>
                                <td class="text-end text-nowrap">NT$ {{ number_format($purchase->totalPrice()) }}</td>
                                <td class="text-end text-nowrap">{{ $part->withUnit($purchase->remaining_quantity) }}</td>
                                <td>{{ $purchase->note ?? '—' }}</td>
                                <td class="text-end tb-col-fit">
                                    {{-- 附件：有附件時只放一個「附件」按鈕，在目前畫面用視窗預覽（圖片、PDF）；有好幾個附件時在視窗裡左右切換。 --}}
                                    @if ($purchase->attachments->isNotEmpty())
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#attachmentModal"
                                            data-attachments="{{ $purchase->attachments->map(fn ($attachment) => ['url' => route('attachments.show', $attachment), 'mime' => $attachment->mime_type, 'filename' => $attachment->file_name])->values()->toJson() }}"
                                            data-title="{{ $purchase->purchase_date->format('Y-m-d') }} {{ $purchase->vendor_name }} 的附件">
                                            <i class="bi bi-paperclip"></i> 附件
                                        </button>
                                    @endif
                                    <a href="{{ route('parts.purchases.edit', [$part, $purchase]) }}" class="btn btn-sm btn-outline-primary" data-modal-frame data-modal-title="修改採購紀錄">修改</a>
                                    @unless ($purchase->isUsed())
                                        <form method="POST" action="{{ route('parts.purchases.destroy', [$part, $purchase]) }}" class="d-inline"
                                            data-confirm="請問確定要刪除 {{ $purchase->purchase_date->format('Y-m-d') }} {{ $purchase->vendor_name }} 的採購紀錄（{{ $part->withUnit($purchase->quantity) }}）嗎？庫存會減少 {{ $part->withUnit($purchase->quantity) }}。">
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

    {{-- 附件預覽視窗（零件履歷與修改採購共用）：見 parts/_attachment-modal.blade.php。 --}}
    @include('parts._attachment-modal')

    {{-- 報廢紀錄：零件還沒用就壞掉、過期或遺失時登記，從庫存扣掉（先進先出，跟用料一樣）。
         報廢損失＝扣到的各批數量×那一批的單價。登記錯了可以「取消報廢」，數量加回原本的批次。 --}}
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            {{-- 標題旁邊寫共報廢多少（損失金額在每一筆的詳細與匯出裡）；右邊是「登記報廢」。 --}}
            <div class="d-flex flex-wrap align-items-baseline gap-2">
                <h2 class="tb-section-title">報廢紀錄</h2>
                @if ($scraps->isNotEmpty())
                    <span class="text-muted small">共報廢 {{ $part->withUnit($scraps->sum('quantity')) }}</span>
                @endif
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <a href="{{ route('parts.scraps.create', $part) }}" class="btn btn-sm btn-outline-danger" data-modal-frame data-modal-title="登記報廢">登記報廢</a>
            </div>
        </div>
        @if ($scraps->isEmpty())
            <div class="card-body text-muted small">目前沒有報廢紀錄。</div>
        @else
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0" data-onion-panel-title="{{ $panelName }}報廢紀錄" data-onion-list-only>
                    <thead class="table-light">
                        <tr>
                            {{-- 欄位順序：報廢日期、登記人、數量、損失金額、扣用批次、原因、備註、操作。 --}}
                            <th class="text-nowrap">報廢日期</th>
                            <th>登記人</th>
                            <th class="text-end text-nowrap">數量</th>
                            <th class="text-end text-nowrap">損失金額</th>
                            <th class="text-nowrap">扣用批次</th>
                            <th>原因</th>
                            <th>備註</th>
                            <th class="text-end tb-col-fit">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($scraps as $scrap)
                            <tr>
                                <td class="text-nowrap">{{ $scrap->scrap_date->format('Y-m-d') }}</td>
                                <td>{{ $scrap->recorder?->name ?? '—' }}</td>
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
                                <td>{{ $scrap->reason }}</td>
                                <td>{{ $scrap->note ?? '—' }}</td>
                                <td class="text-end tb-col-fit">
                                    <form method="POST" action="{{ route('parts.scraps.destroy', [$part, $scrap]) }}" class="d-inline"
                                        data-confirm="請問確定要取消 {{ $scrap->scrap_date?->format('Y-m-d') }} 這筆報廢（{{ $part->withUnit($scrap->quantity) }}）嗎？庫存會加回 {{ $part->withUnit($scrap->quantity) }}。" data-confirm-ok="確認取消報廢">
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
