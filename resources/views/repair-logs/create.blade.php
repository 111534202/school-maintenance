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
            <label for="parts_used_note">使用備品說明（選填，例如：更換投影機燈泡 x1）</label>
            <input type="text" id="parts_used_note" name="parts_used_note" value="{{ old('parts_used_note') }}">
            <small style="color:#616e7c;">備品主檔尚未合併進來，暫時用文字描述；之後會改成選單並自動扣庫存。</small>
        </div>

        <div class="field">
            <label for="attachments">維修前後照片／影片（選填，最多 5 個檔案，jpg/png/pdf/mp4/mov/webm，單檔 20MB 以內）</label>
            <input type="file" id="attachments" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.pdf,.mp4,.mov,.webm">
        </div>

        <button class="btn btn-primary" type="submit">送出維修紀錄（送出後案件進入待驗收）</button>
        <a class="btn btn-secondary" href="{{ route('repair-requests.show', $repairRequest) }}">取消</a>
    </form>
@endsection
