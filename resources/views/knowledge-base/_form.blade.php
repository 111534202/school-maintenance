@php($entry = $entry ?? null)

<div class="card shadow-sm">
    <div class="card-body p-4">
        <div class="row g-3">
            <div class="col-md-8">
                <label for="title" class="form-label">{{ __('knowledge_base.form.title') }}</label>
                <input type="text" class="form-control" id="title" name="title" value="{{ old('title', $entry->title ?? '') }}" required>
            </div>
            <div class="col-md-4">
                <label for="category" class="form-label">{{ __('knowledge_base.form.category') }}</label>
                <input type="text" class="form-control" id="category" name="category" value="{{ old('category', $entry->category ?? '') }}">
            </div>
            <div class="col-12">
                <label for="symptom" class="form-label">{{ __('knowledge_base.form.symptom') }}</label>
                <textarea class="form-control" id="symptom" name="symptom" rows="4" required>{{ old('symptom', $entry->symptom ?? '') }}</textarea>
            </div>
            <div class="col-12">
                <label for="solution" class="form-label">{{ __('knowledge_base.form.solution') }}</label>
                <textarea class="form-control" id="solution" name="solution" rows="6" required>{{ old('solution', $entry->solution ?? '') }}</textarea>
            </div>
            <div class="col-12">
                {{-- 瀏覽器在 checkbox 未勾選時完全不會送出這個欄位，靠這個隱藏欄位確保一定會送出 0 或 1，
                     checkbox 若勾選會排在後面覆蓋隱藏欄位的值，是 Laravel 處理 checkbox 的標準寫法。 --}}
                <input type="hidden" name="is_published" value="0">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="is_published" name="is_published" value="1"
                        {{ old('is_published', $entry->is_published ?? true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_published">{{ __('knowledge_base.form.is_published') }}</label>
                </div>
            </div>
        </div>
    </div>
    <div class="card-footer bg-white d-flex justify-content-end gap-2 py-3">
        <a class="btn btn-outline-secondary" href="{{ route('knowledge-base.index') }}"><i class="bi bi-x-lg me-1"></i>{{ __('common.buttons.cancel') }}</a>
        <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i>{{ __('common.buttons.save') }}</button>
    </div>
</div>
