@extends('layouts.app')

@section('title', '零件庫存列表')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">零件庫存列表</h1>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#lowStockModal">
                <i class="bi bi-exclamation-triangle me-1"></i>查看目前低庫存品項
                <span class="badge text-bg-light ms-1">{{ $lowStockParts->count() }}</span>
            </button>
            <a href="{{ route('parts.create') }}" class="btn btn-primary">新增零件</a>
        </div>
    </div>

    <form method="GET" action="{{ route('parts.index') }}" class="row row-cols-1 row-cols-sm-2 row-cols-md-auto g-2 align-items-end bg-white p-3 rounded shadow-sm mb-3">
        <div class="col">
            <label for="keyword" class="form-label small mb-1">搜尋（零件編號／名稱／規格／品牌／型號）</label>
            <input type="text" name="keyword" id="keyword" class="form-control form-control-sm" style="min-width: 280px;"
                value="{{ request('keyword') }}" placeholder="例如 PART-1 或 HDMI">
        </div>
        <div class="col">
            <button type="submit" class="btn btn-sm btn-primary">搜尋</button>
            <a href="{{ route('parts.index') }}" class="btn btn-sm btn-outline-secondary">清除</a>
        </div>
    </form>

    @if ($parts->isEmpty())
        <div class="alert alert-info">
            {{ request()->filled('keyword') ? '找不到符合的零件。' : '目前沒有零件資料。' }}
        </div>
    @else
        <div class="table-responsive">
            {{-- 平均單價：目前庫存各採購批次的加權平均（用料成本採先進先出，詳見零件履歷）。 --}}
            {{-- 點這一行的任何地方都會進入零件履歷（也可以用 Tab 移到該行後按 Enter）。 --}}
            <table class="table table-bordered table-hover bg-white align-middle part-rows" data-onion-primary="1,2">
                <thead class="table-light">
                    <tr>
                        <th class="text-nowrap">零件編號</th>
                        <th>名稱</th>
                        <th>規格</th>
                        <th>品牌／型號</th>
                        <th class="text-end text-nowrap">平均單價</th>
                        <th class="text-end text-nowrap">目前庫存</th>
                        <th class="text-end text-nowrap">安全庫存</th>
                        <th class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($parts as $part)
                        @php($average = $part->averageUnitPrice())
                        <tr class="part-row" data-href="{{ route('parts.show', $part) }}" tabindex="0" role="link" aria-label="查看 {{ $part->part_code }} {{ $part->name }} 的零件履歷">
                            <td class="text-nowrap">{{ $part->part_code }}</td>
                            <td>{{ $part->name }}</td>
                            <td>{{ $part->specification ?? '—' }}</td>
                            <td>{{ $part->brandModel() ?? '—' }}</td>
                            <td class="text-end text-nowrap">{{ $average === null ? '—' : 'NT$ ' . rtrim(rtrim(number_format($average, 1), '0'), '.') }}</td>
                            <td class="text-end text-nowrap">
                                @if ($part->isLowStock())
                                    <span class="badge text-bg-danger">⚠ 低庫存</span>
                                @endif
                                {{ $part->withUnit($part->current_stock) }}
                            </td>
                            <td class="text-end text-nowrap">{{ $part->withUnit($part->safety_stock) }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('parts.show', $part) }}" class="btn btn-sm btn-outline-primary">查看履歷</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $parts->links() }}
    @endif

    {{-- 低庫存品項視窗：列出所有「目前庫存低於安全庫存」的零件，點一行可進入零件履歷。 --}}
    <div class="modal fade" id="lowStockModal" tabindex="-1" aria-labelledby="lowStockModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="lowStockModalTitle">目前低庫存品項（{{ $lowStockParts->count() }}）</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="關閉"></button>
                </div>
                <div class="modal-body">
                    @if ($lowStockParts->isEmpty())
                        <p class="text-muted text-center my-4">目前沒有低庫存的零件。</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle mb-0 part-rows">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-nowrap">零件編號</th>
                                        <th>名稱</th>
                                        <th>規格</th>
                                        <th>品牌／型號</th>
                                        <th class="text-end text-nowrap">目前庫存</th>
                                        <th class="text-end text-nowrap">安全庫存</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($lowStockParts as $part)
                                        <tr class="part-row" data-href="{{ route('parts.show', $part) }}" tabindex="0" role="link" aria-label="查看 {{ $part->part_code }} {{ $part->name }} 的零件履歷">
                                            <td class="text-nowrap">{{ $part->part_code }}</td>
                                            <td>{{ $part->name }}</td>
                                            <td>{{ $part->specification ?? '—' }}</td>
                                            <td>{{ $part->brandModel() ?? '—' }}</td>
                                            <td class="text-end text-nowrap text-danger fw-semibold">{{ $part->withUnit($part->current_stock) }}</td>
                                            <td class="text-end text-nowrap">{{ $part->withUnit($part->safety_stock) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    @if ($lowStockParts->isNotEmpty())
                        <a href="{{ route('parts.low-stock.export') }}" class="btn btn-sm btn-success">
                            <i class="bi bi-file-earmark-excel me-1"></i>匯出 Excel
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <style>
        .part-row { cursor: pointer; }
        .part-row:focus-visible { outline: 2px solid var(--bs-primary); outline-offset: -2px; }
    </style>
    <script>
    // 整行可點：點任何一格（或按 Enter）都進入該零件的履歷；點到行內的按鈕、連結時照按鈕本身的功能。
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.part-row').forEach(function (row) {
            row.addEventListener('click', function (event) {
                if (event.target.closest('a, button, input, select, textarea')) { return; }
                if (window.getSelection().toString()) { return; }   // 正在選取文字時不跳頁
                window.location.href = row.dataset.href;
            });
            row.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') { window.location.href = row.dataset.href; }
            });
        });
    });
    </script>
@endsection
