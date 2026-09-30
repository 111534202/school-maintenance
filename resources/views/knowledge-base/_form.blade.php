@php($entry = $entry ?? null)

<div class="field">
    <label for="title">{{ __('knowledge_base.form.title') }}</label>
    <input type="text" id="title" name="title" value="{{ old('title', $entry->title ?? '') }}" required>
</div>

<div class="field">
    <label for="category">{{ __('knowledge_base.form.category') }}</label>
    <input type="text" id="category" name="category" value="{{ old('category', $entry->category ?? '') }}">
</div>

<div class="field">
    <label for="symptom">{{ __('knowledge_base.form.symptom') }}</label>
    <textarea id="symptom" name="symptom" required>{{ old('symptom', $entry->symptom ?? '') }}</textarea>
</div>

<div class="field">
    <label for="solution">{{ __('knowledge_base.form.solution') }}</label>
    <textarea id="solution" name="solution" required>{{ old('solution', $entry->solution ?? '') }}</textarea>
</div>

<div class="field">
    <label>
        {{-- 瀏覽器在 checkbox 未勾選時完全不會送出這個欄位，靠這個隱藏欄位確保一定會送出 0 或 1，
             checkbox 若勾選會排在後面覆蓋隱藏欄位的值，是 Laravel 處理 checkbox 的標準寫法。 --}}
        <input type="hidden" name="is_published" value="0">
        <input type="checkbox" name="is_published" value="1" style="width:auto; display:inline-block;"
            {{ old('is_published', $entry->is_published ?? true) ? 'checked' : '' }}>
        {{ __('knowledge_base.form.is_published') }}
    </label>
</div>

<button class="btn btn-primary" type="submit">{{ __('common.buttons.save') }}</button>
<a class="btn btn-secondary" href="{{ route('knowledge-base.index') }}">{{ __('common.buttons.cancel') }}</a>
