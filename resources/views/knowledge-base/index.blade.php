@extends('layouts.app')

@section('title', '知識庫列表')

@section('content')
    <div class="toolbar">
        <h1>自助排除知識庫</h1>
        <a class="btn btn-primary" href="{{ route('knowledge-base.create') }}">＋ 新增項目</a>
    </div>

    @if ($knowledgeBaseEntries->isEmpty())
        <p>目前還沒有任何知識庫項目。</p>
    @else
        <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>標題</th>
                    <th>分類</th>
                    <th>狀態</th>
                    <th>更新時間</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($knowledgeBaseEntries as $entry)
                    <tr>
                        <td><a href="{{ route('knowledge-base.show', $entry) }}">{{ $entry->title }}</a></td>
                        <td>{{ $entry->category ?? '未分類' }}</td>
                        <td>
                            @if ($entry->is_published)
                                <span class="badge badge-on">已上架</span>
                            @else
                                <span class="badge badge-off">未上架</span>
                            @endif
                        </td>
                        <td>{{ $entry->updated_at->format('Y-m-d H:i') }}</td>
                        <td>
                            <a class="btn btn-secondary" href="{{ route('knowledge-base.edit', $entry) }}">編輯</a>
                            <form class="inline" method="POST" action="{{ route('knowledge-base.destroy', $entry) }}" onsubmit="return confirm('確定要刪除「{{ $entry->title }}」嗎？');">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-danger" type="submit">刪除</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>

        <div style="margin-top: 1rem;">
            {{ $knowledgeBaseEntries->links() }}
        </div>
    @endif
@endsection
