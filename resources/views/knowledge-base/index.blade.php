@extends('layouts.app')

@section('title', __('knowledge_base.index_title'))

@section('content')
    <div class="toolbar">
        <h1>{{ __('knowledge_base.index_heading') }}</h1>
        <a class="btn btn-primary" href="{{ route('knowledge-base.create') }}">{{ __('knowledge_base.add_entry') }}</a>
    </div>

    @if ($knowledgeBaseEntries->isEmpty())
        <p>{{ __('knowledge_base.empty_list') }}</p>
    @else
        <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>{{ __('knowledge_base.table.title') }}</th>
                    <th>{{ __('knowledge_base.table.category') }}</th>
                    <th>{{ __('knowledge_base.table.status') }}</th>
                    <th>{{ __('knowledge_base.table.updated_at') }}</th>
                    <th>{{ __('knowledge_base.table.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($knowledgeBaseEntries as $entry)
                    <tr>
                        <td><a href="{{ route('knowledge-base.show', $entry) }}">{{ $entry->title }}</a></td>
                        <td>{{ $entry->category ?? __('knowledge_base.uncategorized') }}</td>
                        <td>
                            @if ($entry->is_published)
                                <span class="badge badge-on">{{ __('knowledge_base.published') }}</span>
                            @else
                                <span class="badge badge-off">{{ __('knowledge_base.unpublished') }}</span>
                            @endif
                        </td>
                        <td>{{ $entry->updated_at->format('Y-m-d H:i') }}</td>
                        <td>
                            <a class="btn btn-secondary" href="{{ route('knowledge-base.edit', $entry) }}">{{ __('common.buttons.edit') }}</a>
                            <form class="inline" method="POST" action="{{ route('knowledge-base.destroy', $entry) }}" onsubmit="return confirm('{{ __('knowledge_base.confirm_delete', ['title' => $entry->title]) }}');">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-danger" type="submit">{{ __('common.buttons.delete') }}</button>
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
