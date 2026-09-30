@extends('layouts.app')

@section('title', __('repair_requests.create.title'))

@section('content')
    <h1>{{ __('repair_requests.create.title') }}</h1>

    @if ($fromKnowledgeBase)
        <div class="status" style="background:#fff7e6; color:#8a6d00;">
            {{ __('repair_requests.create.from_kb_notice', ['title' => $fromKnowledgeBase->title]) }}
        </div>
    @endif

    <form method="POST" action="{{ route('repair-requests.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="field">
            <label for="title">{{ __('repair_requests.create.title_label') }}</label>
            <input type="text" id="title" name="title"
                value="{{ old('title', $fromKnowledgeBase?->title) }}" required>
        </div>

        <div class="field">
            <label for="device_note">{{ __('repair_requests.create.device_note_label') }}</label>
            <input type="text" id="device_note" name="device_note" value="{{ old('device_note') }}">
            <small style="color:#616e7c;">{{ __('repair_requests.create.device_note_hint') }}</small>
        </div>

        <div class="field">
            <label for="location">{{ __('repair_requests.create.location_label') }}</label>
            <input type="text" id="location" name="location" value="{{ old('location') }}">
        </div>

        <div class="field">
            <label for="description">{{ __('repair_requests.create.description_label') }}</label>
            <textarea id="description" name="description" required>{{ old('description', $fromKnowledgeBase ? __('repair_requests.create.description_prefill', ['title' => $fromKnowledgeBase->title]) : '') }}</textarea>
        </div>

        <div class="field">
            <label for="impact_level">{{ __('repair_requests.create.impact_level_label') }}</label>
            <select id="impact_level" name="impact_level" required>
                <option value="low" @selected(old('impact_level') === 'low')>{{ __('repair_requests.impact_level.low') }}</option>
                <option value="medium" @selected(old('impact_level', 'medium') === 'medium')>{{ __('repair_requests.impact_level.medium') }}</option>
                <option value="high" @selected(old('impact_level') === 'high')>{{ __('repair_requests.impact_level.high') }}</option>
            </select>
        </div>

        <div class="field">
            <label>
                <input type="hidden" name="affects_class" value="0">
                <input type="checkbox" name="affects_class" value="1" style="width:auto; display:inline-block;"
                    {{ old('affects_class') ? 'checked' : '' }}>
                {{ __('repair_requests.create.affects_class_label') }}
            </label>
        </div>

        <div class="field">
            <label for="attachments">{{ __('repair_requests.create.attachments_label') }}</label>
            <input type="file" id="attachments" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.pdf,.mp4,.mov,.webm">
        </div>

        <button class="btn btn-primary" type="submit">{{ __('repair_requests.create.submit') }}</button>
        <a class="btn btn-secondary" href="{{ route('repair-requests.index') }}">{{ __('common.buttons.cancel') }}</a>
    </form>
@endsection
