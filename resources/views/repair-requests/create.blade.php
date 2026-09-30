@extends('layouts.app')

@section('title', '新增報修案件')

@section('content')
    <h1>新增報修案件</h1>

    @if ($fromKnowledgeBase)
        <div class="status" style="background:#fff7e6; color:#8a6d00;">
            承接自知識庫「{{ $fromKnowledgeBase->title }}」，已排除步驟仍無法解決，請補充下面資訊送出報修。
        </div>
    @endif

    <form method="POST" action="{{ route('repair-requests.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="field">
            <label for="title">報修標題</label>
            <input type="text" id="title" name="title"
                value="{{ old('title', $fromKnowledgeBase?->title) }}" required>
        </div>

        <div class="field">
            <label for="device_note">設備位置／描述（例如：A101 教室投影機）</label>
            <input type="text" id="device_note" name="device_note" value="{{ old('device_note') }}">
            <small style="color:#616e7c;">設備主檔尚未合併進來，暫時用文字描述；之後會改成下拉選單。</small>
        </div>

        <div class="field">
            <label for="location">地點（選填）</label>
            <input type="text" id="location" name="location" value="{{ old('location') }}">
        </div>

        <div class="field">
            <label for="description">故障描述</label>
            <textarea id="description" name="description" required>{{ old('description', $fromKnowledgeBase ? "已依「{$fromKnowledgeBase->title}」的排除步驟嘗試過，仍無法解決：\n" : '') }}</textarea>
        </div>

        <div class="field">
            <label for="impact_level">影響程度</label>
            <select id="impact_level" name="impact_level" required>
                <option value="low" @selected(old('impact_level') === 'low')>輕微</option>
                <option value="medium" @selected(old('impact_level', 'medium') === 'medium')>中等</option>
                <option value="high" @selected(old('impact_level') === 'high')>嚴重</option>
            </select>
        </div>

        <div class="field">
            <label>
                <input type="hidden" name="affects_class" value="0">
                <input type="checkbox" name="affects_class" value="1" style="width:auto; display:inline-block;"
                    {{ old('affects_class') ? 'checked' : '' }}>
                目前正影響上課
            </label>
        </div>

        <div class="field">
            <label for="attachments">故障照片／影片（選填，最多 5 個檔案，jpg/png/pdf/mp4/mov/webm，單檔 20MB 以內）</label>
            <input type="file" id="attachments" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.pdf,.mp4,.mov,.webm">
        </div>

        <button class="btn btn-primary" type="submit">送出報修</button>
        <a class="btn btn-secondary" href="{{ route('repair-requests.index') }}">取消</a>
    </form>
@endsection
