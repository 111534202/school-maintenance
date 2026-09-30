@extends('layouts.app')

@section('title', __('repair_logs.create_title'))

@section('content')
    <h1>{{ __('repair_logs.create_title') }}</h1>
    <p style="color:#616e7c;">{{ __('repair_logs.case_line', ['title' => $repairRequest->title, 'location' => $repairRequest->device_note ?? $repairRequest->location ?? __('repair_logs.not_filled_location')]) }}</p>

    <form method="POST" action="{{ route('repair-logs.store', $repairRequest) }}" enctype="multipart/form-data">
        @csrf

        <div class="field">
            <label for="cause">{{ __('repair_logs.form.cause') }}</label>
            <textarea id="cause" name="cause" required>{{ old('cause') }}</textarea>
        </div>

        <div class="field">
            <label for="resolution">{{ __('repair_logs.form.resolution') }}</label>
            <textarea id="resolution" name="resolution" required>{{ old('resolution') }}</textarea>
        </div>

        <div class="field">
            <label for="started_at">{{ __('repair_logs.form.started_at') }}</label>
            <input type="datetime-local" id="started_at" name="started_at" value="{{ old('started_at') }}" required>
        </div>

        <div class="field">
            <label for="ended_at">{{ __('repair_logs.form.ended_at') }}</label>
            <input type="datetime-local" id="ended_at" name="ended_at" value="{{ old('ended_at') }}" required>
        </div>

        <div class="field">
            <label for="parts_used_note">{{ __('repair_logs.form.parts_used_note') }}</label>
            <input type="text" id="parts_used_note" name="parts_used_note" value="{{ old('parts_used_note') }}">
            <small style="color:#616e7c;">{{ __('repair_logs.form.parts_used_note_hint') }}</small>
        </div>

        <div class="field">
            <label for="attachments">{{ __('repair_logs.form.attachments') }}</label>
            <input type="file" id="attachments" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.pdf,.mp4,.mov,.webm">
        </div>

        <button class="btn btn-primary" type="submit">{{ __('repair_logs.submit') }}</button>
        <a class="btn btn-secondary" href="{{ route('repair-requests.show', $repairRequest) }}">{{ __('common.buttons.cancel') }}</a>
    </form>
@endsection
