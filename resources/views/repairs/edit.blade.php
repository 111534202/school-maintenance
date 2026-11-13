{{-- 修改報修單頁（對應 RepairRequestController::edit / update）：只有有派工權限的人能改，已結案的不能改。
     可以改標題、故障描述、影響程度、是否影響上課、設備位置／描述、設備地點；綁定的設備、附件、狀態不在這裡改。
     修改頁在畫面中的對話框打開（網址帶 ?modal=1）或整頁打開都可以。 --}}
@extends('layouts.app')

@section('title', '修改報修單：' . $repairRequest->title)

@section('content')
    <div class="page-narrow">
        <h1 class="h4 mb-4"><i class="bi bi-pencil-square me-2"></i>修改報修單</h1>

        <form method="POST" action="{{ route('repairs.update', $repairRequest) }}">
            @csrf
            @method('PUT')

            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    {{-- 已綁定的設備只顯示、不能改（要換設備請取消這張單後重新報修）。 --}}
                    @if ($repairRequest->device)
                        <div class="mb-3">
                            <label class="form-label">設備</label>
                            <div class="form-control-plaintext">{{ $repairRequest->device->device_code }} {{ $repairRequest->device->category->name ?? '' }} {{ $repairRequest->device->brand }} {{ $repairRequest->device->model }}</div>
                        </div>
                    @endif

                    <div class="mb-3">
                        <label for="device_note" class="form-label">設備位置／描述（沒有條碼時填寫）</label>
                        <input type="text" id="device_note" name="device_note" class="form-control @error('device_note') is-invalid @enderror" maxlength="255" value="{{ old('device_note', $repairRequest->device_note) }}">
                        @error('device_note')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="classroom_id" class="form-label">設備地點</label>
                        <select id="classroom_id" name="classroom_id" class="form-select @error('classroom_id') is-invalid @enderror">
                            <option value="">（不指定）</option>
                            @foreach ($classrooms as $classroom)
                                <option value="{{ $classroom->id }}" @selected((string) old('classroom_id', $repairRequest->classroom_id) === (string) $classroom->id)>{{ $classroom->classroom_code }} - {{ $classroom->name }}</option>
                            @endforeach
                        </select>
                        @error('classroom_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="title" class="form-label">報修標題</label>
                        <input type="text" id="title" name="title" class="form-control @error('title') is-invalid @enderror" maxlength="255" required value="{{ old('title', $repairRequest->title) }}">
                        @error('title')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">故障描述</label>
                        <textarea id="description" name="description" rows="4" class="form-control @error('description') is-invalid @enderror" required>{{ old('description', $repairRequest->description) }}</textarea>
                        @error('description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="impact_level" class="form-label">影響程度</label>
                            <select id="impact_level" name="impact_level" class="form-select @error('impact_level') is-invalid @enderror" required>
                                @foreach (['low' => '輕微', 'medium' => '中等', 'high' => '嚴重'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('impact_level', $repairRequest->impact_level) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('impact_level')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <input type="hidden" name="is_class_affected" value="0">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" role="switch" id="is_class_affected" name="is_class_affected" value="1" @checked(old('is_class_affected', $repairRequest->is_class_affected))>
                                <label class="form-check-label" for="is_class_affected">目前正影響上課</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a class="btn btn-outline-secondary" href="{{ route('repairs.show', $repairRequest) }}">取消</a>
                <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i>儲存修改</button>
            </div>
        </form>
    </div>
@endsection
