@csrf
@isset($classroom)
    @method('PUT')
@endisset

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">所屬部門</label>
        <select name="department_id" class="form-select">
            <option value="">－ 未指定 －</option>
            @foreach ($departments as $department)
                <option value="{{ $department->id }}" @selected(old('department_id', $classroom->department_id ?? '') == $department->id)>
                    {{ $department->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">管理人</label>
        <select name="manager_id" class="form-select">
            <option value="">－ 未指定 －</option>
            @foreach ($managers as $manager)
                <option value="{{ $manager->id }}" @selected(old('manager_id', $classroom->manager_id ?? '') == $manager->id)>
                    {{ $manager->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">校區</label>
        <input type="text" name="campus" class="form-control" value="{{ old('campus', $classroom->campus ?? '') }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">大樓</label>
        <input type="text" name="building" class="form-control" value="{{ old('building', $classroom->building ?? '') }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">樓層</label>
        <input type="text" name="floor" class="form-control" value="{{ old('floor', $classroom->floor ?? '') }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">教室代碼</label>
        <input type="text" name="room_code" class="form-control" value="{{ old('room_code', $classroom->room_code ?? '') }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">教室名稱</label>
        <input type="text" name="room_name" class="form-control" value="{{ old('room_name', $classroom->room_name ?? '') }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">教室類型</label>
        <input type="text" name="room_type" class="form-control" value="{{ old('room_type', $classroom->room_type ?? '') }}">
    </div>
</div>

<div class="mt-4">
    <button type="submit" class="btn btn-primary">儲存</button>
    <a href="{{ route('classrooms.index') }}" class="btn btn-outline-secondary">取消</a>
</div>
