@extends('layouts.app')

@section('title', '設備履歷')

@section('content')
    <h1 class="h3 mb-3">設備履歷</h1>

    <form method="GET" class="row row-cols-1 row-cols-sm-2 row-cols-md-auto g-2 align-items-end bg-white p-3 rounded shadow-sm mb-3">
        <div class="col">
            <label for="keyword" class="form-label small mb-1">搜尋（設備編號／資產編號／品牌／型號）</label>
            <input type="text" name="keyword" id="keyword" class="form-control form-control-sm" value="{{ request('keyword') }}" placeholder="例如 DEV-0001">
        </div>
        <div class="col">
            <button type="submit" class="btn btn-sm btn-primary">搜尋</button>
            <a href="{{ route('device-profile.index') }}" class="btn btn-sm btn-outline-secondary">清除</a>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-bordered bg-white align-middle text-nowrap">
            <thead class="table-light">
                <tr>
                    <th>設備編號</th>
                    <th>類別</th>
                    <th>品牌/型號</th>
                    <th>所在教室</th>
                    <th>狀態</th>
                    <th class="text-end">操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($devices as $device)
                    <tr>
                        <td>{{ $device->device_code }}</td>
                        <td>{{ $device->category?->name ?? '—' }}</td>
                        <td>{{ $device->brand }} {{ $device->model }}</td>
                        <td>{{ $device->classroom?->room_name ?? '—' }}</td>
                        <td><span class="badge text-bg-secondary">{{ $device->status }}</span></td>
                        <td class="text-end">
                            <a href="{{ route('device-profile.show', $device) }}" class="btn btn-sm btn-outline-primary">查看履歷</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">目前沒有設備資料</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $devices->links('pagination::bootstrap-5') }}
@endsection
