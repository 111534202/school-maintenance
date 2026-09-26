@extends('layouts.app')

@section('title', '填寫維修紀錄')

@section('content')
    <h1>填寫維修紀錄</h1>
    <p style="color:#616e7c;">案件：{{ $repairRequest->title }}（{{ $repairRequest->device_note ?? $repairRequest->location ?? '未填寫地點' }}）</p>

    <form method="POST" action="{{ route('repair-logs.store', $repairRequest) }}" enctype="multipart/form-data">
        @csrf

        <div class="field">
            <label for="cause">故障原因說明</label>
            <textarea id="cause" name="cause" required>{{ old('cause') }}</textarea>
        </div>

        <div class="field">
            <label for="resolution">處置方式</label>
            <textarea id="resolution" name="resolution" required>{{ old('resolution') }}</textarea>
        </div>

        <div class="field">
            <label for="started_at">開始時間</label>
            <input type="datetime-local" id="started_at" name="started_at" value="{{ old('started_at') }}" required>
        </div>

        <div class="field">
            <label for="ended_at">結束時間</label>
            <input type="datetime-local" id="ended_at" name="ended_at" value="{{ old('ended_at') }}" required>
        </div>

        <div class="field">
            <label for="attachments">維修前後照片（選填，最多 5 個檔案，jpg/png/pdf，單檔 5MB 以內）</label>
            <input type="file" id="attachments" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.pdf">
        </div>

        <button class="btn btn-primary" type="submit">送出維修紀錄（送出後案件進入待驗收）</button>
        <a class="btn btn-secondary" href="{{ route('repair-requests.show', $repairRequest) }}">取消</a>
    </form>
@endsection
