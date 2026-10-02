@extends('layouts.app')

@section('title', __('repair_logs.create_title'))

@section('content')
    <div class="page-narrow">
        <h1 class="h4 mb-1"><i class="bi bi-journal-plus me-2"></i>{{ __('repair_logs.create_title') }}</h1>
        <p class="text-muted mb-4">{{ __('repair_logs.case_line', ['title' => $repairRequest->title, 'location' => $repairRequest->device->device_code ?? $repairRequest->device_note ?? $repairRequest->location ?? __('repair_logs.not_filled_location')]) }}</p>

        <form method="POST" action="{{ route('repair-logs.store', $repairRequest) }}" enctype="multipart/form-data">
            @csrf

            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="cause" class="form-label">{{ __('repair_logs.form.cause') }}</label>
                            <textarea class="form-control" id="cause" name="cause" rows="3" required>{{ old('cause') }}</textarea>
                        </div>
                        <div class="col-12">
                            <label for="resolution" class="form-label">{{ __('repair_logs.form.resolution') }}</label>
                            <textarea class="form-control" id="resolution" name="resolution" rows="3" required>{{ old('resolution') }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="started_at" class="form-label">{{ __('repair_logs.form.started_at') }}</label>
                            <input type="datetime-local" class="form-control" id="started_at" name="started_at" value="{{ old('started_at') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="ended_at" class="form-label">{{ __('repair_logs.form.ended_at') }}</label>
                            <input type="datetime-local" class="form-control" id="ended_at" name="ended_at" value="{{ old('ended_at') }}" required>
                        </div>
                        <div class="col-12">
                            <label for="parts_used_note" class="form-label">{{ __('repair_logs.form.parts_used_note') }}</label>
                            <input type="text" class="form-control" id="parts_used_note" name="parts_used_note" value="{{ old('parts_used_note') }}">
                            <div class="form-text">{{ __('repair_logs.form.parts_used_note_hint') }}</div>
                        </div>
                        <div class="col-12">
                            <label for="attachments" class="form-label">{{ __('repair_logs.form.attachments') }}</label>
                            <input type="file" class="form-control" id="attachments" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.pdf,.mp4,.mov,.webm">
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a class="btn btn-outline-secondary" href="{{ route('repairs.show', $repairRequest) }}"><i class="bi bi-x-lg me-1"></i>{{ __('common.buttons.cancel') }}</a>
                <button class="btn btn-primary" type="submit"><i class="bi bi-send me-1"></i>{{ __('repair_logs.submit') }}</button>
            </div>
        </form>
    </div>
@endsection
