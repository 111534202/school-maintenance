@extends('layouts.app')

@section('title', '教室管理')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">教室管理</h3>
        <a href="{{ route('classrooms.create') }}" class="btn btn-primary">新增教室</a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>教室代碼</th>
                        <th>教室名稱</th>
                        <th>所屬部門</th>
                        <th>位置</th>
                        <th>管理人</th>
                        <th>狀態</th>
                        <th class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($classrooms as $classroom)
                        <tr>
                            <td>{{ $classroom->room_code }}</td>
                            <td>{{ $classroom->room_name }}</td>
                            <td>{{ $classroom->department->name ?? '－' }}</td>
                            <td>{{ $classroom->campus }} / {{ $classroom->building }} / {{ $classroom->floor }}</td>
                            <td>{{ $classroom->manager->name ?? '－' }}</td>
                            <td>
                                @if ($classroom->is_active)
                                    <span class="badge bg-success">啟用中</span>
                                @else
                                    <span class="badge bg-secondary">已停用</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('classrooms.edit', $classroom) }}" class="btn btn-sm btn-outline-primary">編輯</a>
                                <form method="POST" action="{{ route('classrooms.toggle', $classroom) }}" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                                        {{ $classroom->is_active ? '停用' : '啟用' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">尚無教室資料</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $classrooms->links() }}</div>
@endsection
