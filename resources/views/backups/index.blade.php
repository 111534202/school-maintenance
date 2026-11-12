@extends('layouts.app')

@section('title', '資料備份')

@section('content')
{{-- 資料備份（系統管理）：每月 1 號凌晨 0 點自動備份，也可以手動備份；可下載、還原、設定保留天數。
     備份與還原的做法見 App\Services\DatabaseBackup。 --}}
<div class="tb">
    <div class="tb-header">
        <div>
            <h1 class="tb-title">資料備份</h1>
            <p class="tb-subtitle">每月 1 號凌晨 0 點自動備份整個資料庫；有人改錯資料時，可以還原到某一份備份。</p>
        </div>
        <form method="POST" action="{{ route('backups.store') }}"
            data-confirm="請問確定要立即備份目前所有資料嗎？" data-confirm-ok="確認備份">
            @csrf
            <button type="submit" class="tb-btn tb-btn-primary"><i class="bi bi-cloud-arrow-up" aria-hidden="true"></i>立即備份</button>
        </form>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h2 class="tb-section-title">保留天數</h2>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('backups.settings') }}" class="d-flex flex-wrap align-items-center gap-2">
                @csrf
                <label for="keep_days" class="mb-0">備份保留</label>
                <div class="input-group" style="width: 9rem">
                    <input type="number" id="keep_days" name="keep_days" min="0" max="3650" required
                        class="form-control @error('keep_days') is-invalid @enderror" value="{{ old('keep_days', $keepDays) }}">
                    <span class="input-group-text">天</span>
                </div>
                <button type="submit" class="btn btn-outline-primary">儲存</button>
                @error('keep_days')<div class="text-danger small w-100">{{ $message }}</div>@enderror
            </form>
            <p class="text-muted small mb-0 mt-2">超過天數的備份在下次備份時自動刪除（最新的一份一定保留）；填 0 代表永遠不刪。</p>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h2 class="tb-section-title">備份清單</h2>
            <span class="text-muted small">共 {{ $backups->count() }} 份</span>
        </div>
        @if ($backups->isEmpty())
            <div class="card-body text-muted small">目前還沒有備份。按右上角「立即備份」建立第一份。</div>
        @else
            <ul class="list-group list-group-flush">
                @foreach ($backups as $backup)
                    <li class="list-group-item d-flex flex-wrap align-items-center gap-2 py-3 px-4">
                        <div class="me-auto">
                            <div class="fw-bold tb-num">{{ $backup['created_at']->format('Y-m-d H:i') }}</div>
                            <div class="text-muted small">{{ $backup['reason_label'] }} · {{ \App\Support\UploadLimit::human($backup['size']) }} · {{ $backup['with_attachments'] ? '資料庫＋附件' : '只有資料庫（舊格式）' }}</div>
                        </div>
                        <a href="{{ route('backups.download', $backup['name']) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-download" aria-hidden="true"></i> 下載
                        </a>
                        <form method="POST" action="{{ route('backups.restore', $backup['name']) }}"
                            data-confirm="請問確定要把所有資料還原到 {{ $backup['created_at']->format('Y-m-d H:i') }} 的備份嗎？這個時間之後新增或修改的資料都會不見（還原前會先自動備份目前的資料）。"
                            data-confirm-ok="確認還原">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-danger">還原</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <p class="text-muted small mb-0">
        備份包含資料庫內容（零件、報修、預約、帳號等）與上傳的附件檔案（報修照片、採購發票等）；還原時兩者一起換回備份的內容。
        還原後，登入中的帳號如果在備份裡不存在，需要重新登入。
    </p>
</div>
@endsection
