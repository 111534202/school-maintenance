{{-- 修改報修單頁（對應 RepairRequestController::edit / update）：只有有派工權限的人能改，已結案、已退回的不能改。
     可以改標題、故障描述、影響程度、是否影響上課、設備位置／描述、發現地點；綁定的設備、附件、狀態不在這裡改。
     從報修案件的對話框按「修改」時，會在上面再疊一層對話框打開（不換頁）；整頁打開也可以。
     版面跟新增報修一樣：「設備」「問題描述」兩個區塊，取消／儲存放在外框外面靠右。 --}}
@extends('layouts.app')

@section('title', '修改報修單：' . $repairRequest->title)

@section('content')
    <h1 class="tb-title mb-3" data-modal-hide>修改報修單</h1>

    <form method="POST" action="{{ route('repairs.update', $repairRequest) }}">
        @csrf
        @method('PUT')

        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white form-section-title"><i class="bi bi-pc-display me-2"></i>設備</div>
            <div class="card-body">
                {{-- 已綁定的設備只顯示、不能改（要換設備請退回這張單後重新報修）。 --}}
                @if ($repairRequest->device)
                    <div class="mb-3">
                        <label class="form-label">設備</label>
                        <div class="form-control-plaintext">{{ $repairRequest->device->device_code }} {{ $repairRequest->device->category->name ?? '' }} {{ $repairRequest->device->brand }} {{ $repairRequest->device->model }}</div>
                    </div>
                @endif

                <div class="row g-3">
                    <div class="col-md-7">
                        <label for="device_note" class="form-label">設備位置／描述（沒有條碼時填寫）</label>
                        <input type="text" id="device_note" name="device_note" class="form-control @error('device_note') is-invalid @enderror" maxlength="255" value="{{ old('device_note', $repairRequest->device_note) }}">
                        @error('device_note')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-5 position-relative">
                        {{-- 發現地點：跟新增報修一樣，可以輸入教室名稱、代碼搜尋，也可以點開清單直接選。 --}}
                        @include('repairs._classroom-picker', ['selectedId' => old('classroom_id', $repairRequest->classroom_id), 'label' => __('repair_requests.create.location_label')])
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white form-section-title"><i class="bi bi-exclamation-triangle me-2"></i>問題描述</div>
            <div class="card-body">
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

        {{-- 取消／儲存放在外框外面、靠右（跟其他表單一樣）。 --}}
        <div class="d-flex justify-content-end gap-2">
            <a class="btn btn-outline-secondary" href="{{ route('repairs.show', $repairRequest) }}"><i class="bi bi-x-lg me-1"></i>取消</a>
            <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i>儲存</button>
        </div>
    </form>
@endsection
