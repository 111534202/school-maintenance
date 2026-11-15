{{-- 批次匯入的第二步（對應 BulkImportController::preview）：逐列顯示檢查結果，確認後才真的寫入。 --}}
@extends('layouts.app')

@section('title', __('imports.preview_title', ['label' => $importer->label()]))

@section('content')
<div class="tb">
    <div class="tb-header">
        <div>
            <h1 class="tb-title">{{ __('imports.preview_title', ['label' => $importer->label()]) }}</h1>
            <div class="text-muted small">{{ $fileName }}</div>
        </div>
        <a class="tb-btn tb-btn-outline" href="{{ route('imports.index', $importer->type()) }}"><i class="bi bi-arrow-left"></i>{{ __('imports.reupload') }}</a>
    </div>

    <div class="tb-card tb-card-pad mb-3">
        <div class="d-flex flex-wrap align-items-center gap-3">
            <span class="badge tb-pill tb-pill-ok">{{ __('imports.ok_count', ['count' => $okCount]) }}</span>
            @if ($errorCount > 0)
                <span class="badge tb-pill tb-pill-danger">{{ __('imports.error_count', ['count' => $errorCount]) }}</span>
            @endif
            <form method="POST" action="{{ route('imports.confirm', $importer->type()) }}" class="ms-auto"
                @if ($errorCount > 0) data-confirm="{{ __('imports.confirm_with_errors', ['ok' => $okCount, 'error' => $errorCount]) }}" data-confirm-ok="{{ __('imports.confirm_ok') }}" @endif>
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <button type="submit" class="btn btn-primary" @disabled($okCount === 0)>
                    <i class="bi bi-check2-circle me-1"></i>{{ __('imports.confirm_button', ['count' => $okCount]) }}
                </button>
            </form>
        </div>
        @if ($errorCount > 0)
            <div class="alert alert-warning small mt-3 mb-0">{{ __('imports.errors_skipped_hint') }}</div>
        @endif
    </div>

    <section class="tb-table-wrap">
        <div class="table-responsive">
            <table class="tb-table tb-table-hover">
                <thead>
                    <tr>
                        <th class="text-center">{{ __('imports.col.line') }}</th>
                        <th class="text-center">{{ __('imports.col.result') }}</th>
                        @foreach ($importer->columns() as $column)
                            @if ($column['key'] !== 'password')<th>{{ $column['label'] }}</th>@endif
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($results as $result)
                        <tr @class(['table-danger' => $result['errors']])>
                            <td class="text-center">{{ $result['line'] }}</td>
                            <td class="text-center">
                                @if ($result['errors'])
                                    <span class="badge tb-pill tb-pill-danger">{{ __('imports.row_error') }}</span>
                                @else
                                    <span class="badge tb-pill tb-pill-ok">{{ __('imports.row_ok') }}</span>
                                @endif
                            </td>
                            @foreach ($importer->columns() as $column)
                                {{-- 密碼不顯示在預覽畫面。 --}}
                                @if ($column['key'] !== 'password')<td>{{ $result['values'][$column['key']] ?? '' }}</td>@endif
                            @endforeach
                        </tr>
                        @if ($result['errors'])
                            <tr class="table-danger">
                                <td></td>
                                <td colspan="{{ count($importer->columns()) }}" class="small text-danger">
                                    <i class="bi bi-exclamation-circle me-1"></i>{{ implode('；', $result['errors']) }}
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
