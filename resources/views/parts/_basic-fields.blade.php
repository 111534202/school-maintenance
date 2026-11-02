{{-- 零件基本資料欄位（新增與修改共用）。$part 為 null 代表新增。 --}}
<div class="mb-3">
    <label for="name" class="form-label">零件名稱 <span class="text-danger">*</span></label>
    <input type="text" id="name" name="name" maxlength="255" required autofocus
        class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $part?->name) }}">
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label for="specification" class="form-label">規格</label>
    <input type="text" id="specification" name="specification" maxlength="255" placeholder="例如 2 公尺"
        class="form-control @error('specification') is-invalid @enderror" value="{{ old('specification', $part?->specification) }}">
    @error('specification')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-6">
        <label for="brand" class="form-label">品牌</label>
        <input type="text" id="brand" name="brand" maxlength="255"
            class="form-control @error('brand') is-invalid @enderror" value="{{ old('brand', $part?->brand) }}">
        @error('brand')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-sm-6">
        <label for="model" class="form-label">型號</label>
        <input type="text" id="model" name="model" maxlength="255"
            class="form-control @error('model') is-invalid @enderror" value="{{ old('model', $part?->model) }}">
        @error('model')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-6">
        <label for="unit" class="form-label">單位 <span class="text-danger">*</span></label>
        {{-- 可以直接從常用單位選，也可以自己輸入。 --}}
        <input type="text" id="unit" name="unit" maxlength="10" required list="unitOptions" placeholder="例如 條、個"
            class="form-control @error('unit') is-invalid @enderror" value="{{ old('unit', $part?->unit) }}">
        <datalist id="unitOptions">
            @foreach (['個', '條', '片', '顆', '組', '台', '支', '捲', '盒', '包'] as $unitOption)
                <option value="{{ $unitOption }}">
            @endforeach
        </datalist>
        @error('unit')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-sm-6">
        <label for="safety_stock" class="form-label">安全庫存 <span class="text-danger">*</span></label>
        <input type="number" id="safety_stock" name="safety_stock" min="0" required
            class="form-control @error('safety_stock') is-invalid @enderror" value="{{ old('safety_stock', $part?->safety_stock ?? 0) }}">
        @error('safety_stock')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
<div class="form-text mt-n2 mb-3">目前庫存低於安全庫存時，列表會顯示「低庫存」。</div>
