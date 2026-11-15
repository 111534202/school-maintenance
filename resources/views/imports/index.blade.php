{{-- 批次匯入的第一步（對應 BulkImportController::index）：說明、欄位一覽、下載範本、選檔上傳。設備與用戶共用。 --}}
@extends('layouts.app')

@section('title', __('imports.title', ['label' => $importer->label()]))

@section('content')
<div class="tb">
    <div class="tb-header">
        <div>
            <h1 class="tb-title">{{ __('imports.title', ['label' => $importer->label()]) }}</h1>
        </div>
        <a class="tb-btn tb-btn-outline" href="{{ route($importer->type() === 'users' ? 'users.index' : 'devices.index') }}"><i class="bi bi-arrow-left"></i>{{ __('imports.back') }}</a>
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
            <a class="btn btn-outline-primary" href="{{ route('imports.template', $importer->type()) }}"><i class="bi bi-file-earmark-excel me-1"></i>{{ __('imports.download_template') }}</a>
            <span class="text-muted small">{{ __('imports.template_hint') }}</span>
        </div>

        <form method="POST" action="{{ route('imports.preview', $importer->type()) }}" enctype="multipart/form-data" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-8">
                <label for="file" class="form-label">{{ __('imports.choose_file') }}</label>
                <input type="file" class="form-control @error('file') is-invalid @enderror" id="file" name="file" accept=".xlsx,.csv,.txt" required>
                @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">{{ __('imports.file_hint') }}</div>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>{{ __('imports.preview_button') }}</button>
            </div>
        </form>
    </div>

    <div class="tb-card tb-card-pad mb-3">
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

    <div class="tb-card tb-card-pad">
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
