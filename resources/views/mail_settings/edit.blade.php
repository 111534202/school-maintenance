{{-- 寄信設定頁（對應 MailSettingController::edit）。管理員在這裡填寄信用的信箱帳號，啟用後全站寄信改用這組設定。 --}}
@extends('layouts.app')

@section('title', __('mail_settings.title'))

@section('content')
    <div class="page-narrow">
        <h1 class="h4 mb-3"><i class="bi bi-envelope-at me-2"></i>{{ __('mail_settings.title') }}</h1>

        {{-- 目前實際用哪一種方式寄信：一眼看出「現在有沒有真的在寄」。 --}}
        @if ($setting && $setting->is_enabled)
            <div class="alert alert-success small">{{ __('mail_settings.status_on', ['host' => $setting->host]) }}</div>
        @else
            <div class="alert alert-warning small">{{ __('mail_settings.status_off', ['mailer' => $envMailer]) }}</div>
        @endif

        <form method="POST" action="{{ route('mail-settings.update') }}" autocomplete="off">
            @csrf
            @method('PUT')
            <div class="card shadow-sm mb-4">
                <div class="card-body p-4">
                    <div class="mb-3">
                        <div class="form-label">{{ __('mail_settings.preset') }}</div>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach ($presets as $key => $preset)
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-preset="{{ $key }}"
                                    data-host="{{ $preset['host'] }}" data-port="{{ $preset['port'] }}" data-encryption="{{ $preset['encryption'] }}">
                                    {{ __('mail_settings.presets.' . $key) }}
                                </button>
                            @endforeach
                        </div>
                        <div class="form-text">{{ __('mail_settings.preset_hint') }}</div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="host" class="form-label">{{ __('mail_settings.host') }}</label>
                            <input type="text" class="form-control @error('host') is-invalid @enderror" id="host" name="host"
                                value="{{ old('host', $setting->host ?? '') }}" required>
                            @error('host')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-6 col-md-3">
                            <label for="port" class="form-label">{{ __('mail_settings.port') }}</label>
                            <input type="number" class="form-control @error('port') is-invalid @enderror" id="port" name="port" min="1" max="65535"
                                value="{{ old('port', $setting->port ?? 587) }}" required>
                            @error('port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-6 col-md-3">
                            <label for="encryption" class="form-label">{{ __('mail_settings.encryption') }}</label>
                            <select class="form-select @error('encryption') is-invalid @enderror" id="encryption" name="encryption">
                                @foreach (['tls', 'ssl', 'none'] as $option)
                                    <option value="{{ $option }}" @selected(old('encryption', $setting->encryption ?? 'tls') === $option)>{{ __('mail_settings.encryptions.' . $option) }}</option>
                                @endforeach
                            </select>
                            @error('encryption')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="smtp_account" class="form-label">{{ __('mail_settings.smtp_account') }}</label>
                            <input type="text" class="form-control @error('smtp_account') is-invalid @enderror" id="smtp_account" name="smtp_account"
                                value="{{ old('smtp_account', $setting->smtp_account ?? '') }}" autocomplete="off">
                            @error('smtp_account')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">{{ __('mail_settings.smtp_account_hint') }}</div>
                        </div>
                        <div class="col-md-6">
                            <label for="password" class="form-label">{{ __('mail_settings.password') }}</label>
                            <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password"
                                autocomplete="new-password" placeholder="{{ filled($setting?->password) ? __('mail_settings.password_saved') : '' }}">
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">{{ __('mail_settings.password_hint') }}</div>
                        </div>
                        <div class="col-md-6">
                            <label for="from_address" class="form-label">{{ __('mail_settings.from_address') }}</label>
                            <input type="email" class="form-control @error('from_address') is-invalid @enderror" id="from_address" name="from_address"
                                value="{{ old('from_address', $setting->from_address ?? '') }}" required>
                            @error('from_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">{{ __('mail_settings.from_address_hint') }}</div>
                        </div>
                        <div class="col-md-6">
                            <label for="from_label" class="form-label">{{ __('mail_settings.from_label') }}</label>
                            <input type="text" class="form-control @error('from_label') is-invalid @enderror" id="from_label" name="from_label" maxlength="100"
                                value="{{ old('from_label', $setting->from_label ?? config('app.name')) }}">
                            @error('from_label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="is_enabled" name="is_enabled" value="1"
                                    @checked(old('is_enabled', $setting->is_enabled ?? false))>
                                <label class="form-check-label" for="is_enabled">{{ __('mail_settings.is_enabled') }}</label>
                            </div>
                            <div class="form-text">{{ __('mail_settings.is_enabled_hint') }}</div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent px-4 py-3">
                    <button type="submit" class="btn btn-primary">{{ __('mail_settings.save') }}</button>
                </div>
            </div>
        </form>

        {{-- 寄測試信：用「已儲存」的設定寄，先測成功再啟用。 --}}
        <form method="POST" action="{{ route('mail-settings.test') }}" class="card shadow-sm mb-4">
            @csrf
            <div class="card-body p-4">
                <h2 class="h6 mb-2">{{ __('mail_settings.test_title') }}</h2>
                <p class="small text-muted mb-3">{{ __('mail_settings.test_hint') }}</p>
                <div class="row g-2 align-items-end">
                    <div class="col-md-8">
                        <label for="recipient" class="form-label">{{ __('mail_settings.recipient') }}</label>
                        <input type="email" class="form-control @error('recipient') is-invalid @enderror" id="recipient" name="recipient"
                            value="{{ old('recipient', auth()->user()->email) }}" required>
                        @error('recipient')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-outline-primary w-100" @disabled(! $setting)>{{ __('mail_settings.test_button') }}</button>
                    </div>
                </div>
            </div>
        </form>

        <div class="small text-muted">
            <div class="fw-semibold mb-1">{{ __('mail_settings.help_title') }}</div>
            <ol class="ps-3 mb-0">
                @foreach (__('mail_settings.help') as $line)
                    <li class="mb-1">{{ $line }}</li>
                @endforeach
            </ol>
        </div>
    </div>

    <script>
        // 按下「Gmail／Outlook」預設按鈕：自動帶入伺服器、連接埠與加密方式。
        document.querySelectorAll('[data-preset]').forEach(function (button) {
            button.addEventListener('click', function () {
                document.getElementById('host').value = button.dataset.host;
                document.getElementById('port').value = button.dataset.port;
                document.getElementById('encryption').value = button.dataset.encryption;
            });
        });
    </script>
@endsection
