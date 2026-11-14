{{-- 新增、編輯科系共用的表單欄位（被 majors/create.blade.php 與 majors/edit.blade.php 引入；編輯時多一個 $major 變數）。 --}}
@php($major = $major ?? null)

<div class="card shadow-sm">
    <div class="card-body p-4">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="major_code" class="form-label">{{ __('majors.form.code') }}</label>
                <input type="text" class="form-control @error('major_code') is-invalid @enderror" id="major_code" name="major_code"
                    value="{{ old('major_code', $major->major_code ?? '') }}" autocomplete="off">
                @error('major_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">{{ __('majors.form.code_hint') }}</div>
            </div>
            <div class="col-md-8">
                <label for="name" class="form-label">{{ __('majors.form.name') }}</label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name"
                    value="{{ old('name', $major->name ?? '') }}" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label for="abbreviation" class="form-label">{{ __('majors.form.abbreviation') }}</label>
                <input type="text" class="form-control @error('abbreviation') is-invalid @enderror" id="abbreviation" name="abbreviation" maxlength="10"
                    value="{{ old('abbreviation', $major->abbreviation ?? '') }}" required>
                @error('abbreviation')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">{{ __('majors.form.abbreviation_hint') }}</div>
            </div>
            <div class="col-md-4">
                <label for="grade_count" class="form-label">{{ __('majors.form.grade_count') }}</label>
                <input type="number" class="form-control @error('grade_count') is-invalid @enderror" id="grade_count" name="grade_count" min="1" max="{{ \App\Models\Major::MAX_GRADES }}"
                    value="{{ old('grade_count', $major->grade_count ?? 5) }}" required>
                @error('grade_count')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">{{ __('majors.form.grade_count_hint') }}</div>
            </div>
            <div class="col-md-4">
                <label for="class_count" class="form-label">{{ __('majors.form.class_count') }}</label>
                <input type="number" class="form-control @error('class_count') is-invalid @enderror" id="class_count" name="class_count" min="1" max="{{ count(\App\Models\Major::SECTION_NAMES) }}"
                    value="{{ old('class_count', $major->class_count ?? 2) }}" required>
                @error('class_count')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">{{ __('majors.form.class_count_hint') }}</div>
            </div>
            <div class="col-12">
                {{-- 即時預覽：依簡稱、年級數、班數列出會出現哪些班級名稱，輸入時就看得到結果。 --}}
                <div class="alert alert-light border mb-0 small" id="major_preview" aria-live="polite"></div>
            </div>
            <div class="col-12">
                <input type="hidden" name="is_active" value="0">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                        {{ old('is_active', $major->is_active ?? true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active">{{ __('majors.form.is_active') }}</label>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="d-flex justify-content-end gap-2 mt-3">
    <a class="btn btn-outline-secondary" href="{{ route('majors.index') }}"><i class="bi bi-x-lg me-1"></i>{{ __('common.buttons.cancel') }}</a>
    <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i>{{ __('common.buttons.save') }}</button>
</div>

<script>
(function () {
    var sections = @json(\App\Models\Major::SECTION_NAMES);
    var numerals = ['一', '二', '三', '四', '五', '六', '七', '八', '九'];
    var box = document.getElementById('major_preview');
    var short = document.getElementById('abbreviation');
    var grades = document.getElementById('grade_count');
    var classes = document.getElementById('class_count');
    function render() {
        var g = Math.max(0, Math.min(9, parseInt(grades.value, 10) || 0));
        var c = Math.max(0, Math.min(sections.length, parseInt(classes.value, 10) || 0));
        var s = short.value.trim();
        if (!s || !g || !c) { box.textContent = ''; return; }
        var rows = [];
        for (var i = 1; i <= g; i++) {
            var names = [];
            for (var j = 1; j <= c; j++) names.push(s + numerals[i - 1] + sections[j - 1]);
            rows.push(names.join('、'));
        }
        box.innerHTML = '<strong>{{ __('majors.form.example') }}</strong><br>' + rows.map(function (r) { return r.replace(/&/g, '&amp;').replace(/</g, '&lt;'); }).join('<br>');
    }
    [short, grades, classes].forEach(function (el) { el.addEventListener('input', render); });
    render();
})();
</script>
