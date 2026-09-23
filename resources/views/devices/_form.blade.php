@csrf
@isset($device)
    @method('PUT')
@endisset

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">設備編號</label>
        <input type="text" name="device_code" class="form-control" value="{{ old('device_code', $device->device_code ?? '') }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">資產編號</label>
        <input type="text" name="asset_code" class="form-control" value="{{ old('asset_code', $device->asset_code ?? '') }}">
    </div>
    <div class="col-md-6">
        <label class="form-label">類別</label>
        <select name="device_category_id" class="form-select" required>
            <option value="">－ 請選擇 －</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected(old('device_category_id', $device->device_category_id ?? '') == $category->id)>
                    {{ $category->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">所在教室</label>
        <select name="classroom_id" class="form-select" required>
            <option value="">－ 請選擇 －</option>
            @foreach ($classrooms as $classroom)
                <option value="{{ $classroom->id }}" @selected(old('classroom_id', $device->classroom_id ?? '') == $classroom->id)>
                    {{ $classroom->room_code }} - {{ $classroom->room_name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">品牌</label>
        <input type="text" name="brand" class="form-control" value="{{ old('brand', $device->brand ?? '') }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">型號</label>
        <input type="text" name="model" class="form-control" value="{{ old('model', $device->model ?? '') }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">序號</label>
        <input type="text" name="serial_number" class="form-control" value="{{ old('serial_number', $device->serial_number ?? '') }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">保固期限</label>
        <input type="date" name="warranty_until" class="form-control" value="{{ old('warranty_until', optional($device->warranty_until ?? null)->format('Y-m-d')) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">狀態</label>
        <select name="status" class="form-select" required>
            @foreach (\App\Models\Device::STATUSES as $status)
                <option value="{{ $status }}" @selected(old('status', $device->status ?? 'normal') == $status)>{{ $status }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4 d-flex align-items-end">
        <div class="form-check">
            <input type="checkbox" name="is_core" value="1" class="form-check-input" id="is_core"
                   @checked(old('is_core', $device->is_core ?? false))>
            <label class="form-check-label" for="is_core">核心設備</label>
        </div>
    </div>
</div>

<div class="mt-4">
    <button type="submit" class="btn btn-primary">儲存</button>
    <a href="{{ route('devices.index') }}" class="btn btn-outline-secondary">取消</a>
</div>
