@extends('layouts.app')

@section('title', __('repair_requests.create.title'))

@section('content')
    <h1>{{ __('repair_requests.create.title') }}</h1>

    @if ($fromKnowledgeBase)
        <div class="alert alert-warning">
            {{ __('repair_requests.create.from_kb_notice', ['title' => $fromKnowledgeBase->title]) }}
        </div>
    @endif

    <form method="POST" action="{{ route('repairs.store') }}" enctype="multipart/form-data">
        @csrf

        {{-- 資產報修（簡化流程）：掃描或輸入設備條碼，JS 立刻查詢並自動帶入
             下面的設備資訊，不用再手動描述設備；device_id 是真正寫進資料庫的外鍵，
             device_code 輸入框只是給人看/給掃描器打字用的介面。 --}}
        <div class="field">
            <label for="device_code_input">{{ __('repair_requests.create.device_code_label') }}</label>
            <input type="text" id="device_code_input" autocomplete="off"
                placeholder="{{ __('repair_requests.create.device_code_placeholder') }}"
                value="{{ $device->device_code ?? '' }}">
            <small style="color:#616e7c;">{{ __('repair_requests.create.device_code_hint') }}</small>
            <div id="device_lookup_error" class="text-danger small" style="display:none;">
                {{ __('repair_requests.create.device_lookup_error') }}
            </div>
            <div id="device_display" style="margin-top:0.3rem; {{ $device ? '' : 'display:none;' }}">
                <span class="badge bg-success">{{ __('repair_requests.create.device_display_label') }}<span id="device_display_text">{{ $device?->device_code }} {{ $device?->category?->name }} {{ $device?->brand }} {{ $device?->model }}（{{ $device?->classroom?->room_name ?? $device?->classroom?->room_code }}）</span></span>
            </div>
            <input type="hidden" id="device_id" name="device_id" value="{{ $device->id ?? '' }}">
        </div>

        <div class="field">
            <label for="title">{{ __('repair_requests.create.title_label') }}</label>
            <input type="text" id="title" name="title"
                value="{{ old('title', $fromKnowledgeBase?->title ?? ($device ? trim(($device->classroom->room_code ?? '') . ' ' . ($device->category->name ?? '') . '故障') : '')) }}" required>
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
        <a class="btn btn-secondary" href="{{ route('repairs.index') }}">{{ __('common.buttons.cancel') }}</a>
    </form>

    <script>
        (function () {
            var input = document.getElementById('device_code_input');
            var errorBox = document.getElementById('device_lookup_error');
            var displayBox = document.getElementById('device_display');
            var displayText = document.getElementById('device_display_text');
            var deviceIdField = document.getElementById('device_id');
            var titleField = document.getElementById('title');

            // 條碼掃描器對電腦來說就是鍵盤輸入 + 最後自動送出 Enter，
            // 所以偵測 Enter 或欄位失焦時觸發查詢即可，不需要相機或解碼函式庫。
            function lookup() {
                var code = input.value.trim();
                if (!code) {
                    return;
                }

                fetch('{{ url('repairs/device-lookup') }}/' + encodeURIComponent(code), {
                    headers: { 'Accept': 'application/json' },
                })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('not found');
                        }
                        return response.json();
                    })
                    .then(function (data) {
                        errorBox.style.display = 'none';
                        deviceIdField.value = data.id;
                        displayText.textContent = data.display;
                        displayBox.style.display = '';
                        if (!titleField.value) {
                            titleField.value = data.title_suggestion;
                        }
                    })
                    .catch(function () {
                        deviceIdField.value = '';
                        displayBox.style.display = 'none';
                        errorBox.style.display = '';
                    });
            }

            input.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    lookup();
                }
            });
            input.addEventListener('blur', lookup);
        })();
    </script>
@endsection
