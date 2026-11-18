{{-- 批量匯入的第一步（對應 BulkImportController::index）：說明、欄位一覽、下載範本、選檔上傳。設備與用戶共用。
     從設備／用戶主檔右下角「批量匯入」用對話框打開（?modal=1）：標題在對話框上方，頁首不顯示；上傳、預覽、復原都留在對話框裡，
     確認匯入成功後關掉對話框、回到列表顯示結果。 --}}
@extends('layouts.app')

@section('title', __('imports.title', ['label' => $importer->label()]))

@section('content')
<div class="tb">
    <div class="tb-header" data-modal-hide>
        <div>
            <h1 class="tb-title">{{ __('imports.title', ['label' => $importer->label()]) }}</h1>
        </div>
    </div>

    <div class="tb-card tb-card-pad mb-3">
        <h2 class="h6 mb-3">{{ __('imports.steps_title') }}</h2>
        <ol class="mb-0">
            <li class="mb-1">{{ __('imports.step1') }}</li>
            <li class="mb-1">{{ __('imports.step2', ['max' => $maxRows]) }}</li>
            <li class="mb-1">{{ __('imports.step3') }}</li>
        </ol>
    </div>

    <div class="tb-card tb-card-pad mb-3">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            {{-- data-modal-stay：在對話框裡按「下載範本」直接下載，對話框不會關掉。 --}}
            <a class="btn btn-outline-primary" href="{{ route('imports.template', $importer->type()) }}" data-modal-stay><i class="bi bi-download me-1" aria-hidden="true"></i>{{ __('imports.download_template') }}</a>
            <span class="text-muted small">{{ __('imports.template_hint') }}</span>
        </div>

        {{-- 「上傳並預覽」放在選擇檔案框的右邊、同一列對齊；說明文字與錯誤訊息放在下面。 --}}
        <form method="POST" action="{{ route('imports.preview', $importer->type()) }}" enctype="multipart/form-data">
            @csrf
            <label for="file" class="form-label">{{ __('imports.choose_file') }}</label>
            <div class="d-flex align-items-center gap-2">
                <input type="file" class="form-control w-auto @error('file') is-invalid @enderror" id="file" name="file" accept=".xlsx,.csv,.txt" required style="flex: 1 1 0; min-width: 0;">
                <button type="submit" class="btn btn-primary text-nowrap"><i class="bi bi-search me-1"></i>{{ __('imports.preview_button') }}</button>
            </div>
            @error('file')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            <div class="form-text">{{ __('imports.file_hint') }}</div>
        </form>
    </div>

    {{-- data-no-fit：匯入紀錄、欄位說明都整份顯示，手機清單不分頁（不會出現「‹ 1 / 3 ›」）。 --}}
    <div class="tb-card tb-card-pad mb-3" data-no-fit>
        <h2 class="h6 mb-3">{{ __('imports.history_title') }}</h2>
        @if ($batches->isEmpty())
            <div class="text-muted small">{{ __('imports.history_empty') }}</div>
        @else
            <div class="table-responsive">
                <table class="tb-table">
                    <thead><tr>
                        <th>{{ __('imports.history_col.time') }}</th><th>{{ __('imports.history_col.file') }}</th><th>{{ __('imports.history_col.count') }}</th>
                        <th>{{ __('imports.history_col.by') }}</th><th>{{ __('imports.history_col.state') }}</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($batches as $batch)
                            <tr>
                                <td class="text-nowrap">{{ $batch->created_at->format('Y-m-d H:i') }}</td>
                                <td>{{ $batch->name }}</td>
                                <td class="text-nowrap">{{ __('imports.history_count', ['ok' => $batch->imported_count, 'skipped' => $batch->skipped_count]) }}</td>
                                <td>{{ $batch->creator?->name }}</td>
                                <td class="text-nowrap">
                                    @if ($batch->isUndone())
                                        <span class="badge tb-pill tb-pill-muted">{{ __('imports.undone') }}</span>
                                    @else
                                        {{-- 整批復原：送出前先跳出全站共用的確認視窗（data-confirm）。 --}}
                                        <form method="POST" action="{{ route('imports.undo', [$importer->type(), $batch]) }}" class="d-inline"
                                            data-confirm="{{ __('imports.undo_confirm') }}" data-confirm-ok="{{ __('imports.undo_confirm_ok') }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-arrow-counterclockwise me-1"></i>{{ __('imports.undo_button') }}</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="tb-card tb-card-pad" data-no-fit>
        <h2 class="h6 mb-3">{{ __('imports.columns_title') }}</h2>
        <div class="table-responsive">
            <table class="tb-table">
                <thead><tr><th>{{ __('imports.col.name') }}</th><th class="text-center">{{ __('imports.col.required') }}</th><th>{{ __('imports.col.example') }}</th><th>{{ __('imports.col.note') }}</th></tr></thead>
                <tbody>
                    @foreach ($importer->columns() as $column)
                        <tr>
                            <td class="fw-semibold">{{ $column['label'] }}</td>
                            <td class="text-center">@if ($column['required'])<span class="badge tb-pill tb-pill-warn">{{ __('imports.required') }}</span>@endif</td>
                            <td>{{ $column['example'] }}</td>
                            <td class="small text-muted">{{ $column['note'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
