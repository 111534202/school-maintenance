@extends('layouts.app')

@section('title', '設備管理')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">設備管理</h3>
        <a href="{{ route('devices.create') }}" class="btn btn-primary">新增設備</a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>設備編號</th>
                        <th>類別</th>
                        <th>品牌/型號</th>
                        <th>所在教室</th>
                        <th>狀態</th>
                        <th>核心設備</th>
                        <th class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($devices as $device)
                        <tr>
                            <td><a href="{{ route('devices.show', $device) }}">{{ $device->device_code }}</a></td>
                            <td>{{ $device->category->name ?? '－' }}</td>
                            <td>{{ $device->brand }} {{ $device->model }}</td>
                            <td>{{ $device->classroom->room_name ?? '－' }}</td>
                            <td><span class="badge bg-info text-dark">{{ $device->status }}</span></td>
                            <td>
                                @if ($device->is_core)
                                    <span class="badge bg-warning text-dark">核心</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('devices.show', $device) }}" class="btn btn-sm btn-outline-secondary">詳細</a>
                                <a href="{{ route('devices.edit', $device) }}" class="btn btn-sm btn-outline-primary">編輯</a>
                                <form method="POST" action="{{ route('devices.disable', $device) }}" class="d-inline" onsubmit="return confirm('確定要停用此設備嗎？');">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">停用</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">尚無設備資料</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $devices->links() }}</div>
@endsection
