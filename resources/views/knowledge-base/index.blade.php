@extends('layouts.app')

@section('title', __('knowledge_base.index_title'))

@section('content')
    <div class="page-header mb-4">
        <h1 class="h4 mb-0"><i class="bi bi-book me-2"></i>{{ __('knowledge_base.index_heading') }}</h1>
        @can('knowledge-base.manage')
            <a class="btn btn-primary" href="{{ route('knowledge-base.create') }}">
                <i class="bi bi-plus-lg me-1"></i>{{ __('knowledge_base.add_entry') }}
            </a>
        @endcan
    </div>

    @if ($knowledgeBaseEntries->isEmpty())
        <div class="alert alert-info mb-0">{{ __('knowledge_base.empty_list') }}</div>
    @else
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('knowledge_base.table.title') }}</th>
                            <th>{{ __('knowledge_base.table.category') }}</th>
                            <th class="text-center">{{ __('knowledge_base.table.status') }}</th>
                            <th>{{ __('knowledge_base.table.updated_at') }}</th>
                            <th class="text-center">{{ __('knowledge_base.table.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($knowledgeBaseEntries as $entry)
                            <tr>
                                <td><a href="{{ route('knowledge-base.show', $entry) }}" class="text-decoration-none">{{ $entry->title }}</a></td>
                                <td>{{ $entry->category ?? __('knowledge_base.uncategorized') }}</td>
                                <td class="text-center">
                                    @if ($entry->is_published)
                                        <span class="badge text-bg-success">{{ __('knowledge_base.published') }}</span>
                                    @else
                                        <span class="badge text-bg-secondary">{{ __('knowledge_base.unpublished') }}</span>
                                    @endif
                                </td>
                                <td>{{ $entry->updated_at->format('Y-m-d H:i') }}</td>
                                <td class="text-center">
                                    <div class="d-inline-flex gap-1">
                                        <a class="btn btn-outline-secondary btn-sm icon-btn" href="{{ route('knowledge-base.show', $entry) }}"
                                            title="{{ __('common.buttons.view') }}" aria-label="{{ __('common.buttons.view') }}"><i class="bi bi-eye"></i></a>
                                        @can('knowledge-base.manage')
                                            <a class="btn btn-outline-primary btn-sm icon-btn" href="{{ route('knowledge-base.edit', $entry) }}"
                                                title="{{ __('common.buttons.edit') }}" aria-label="{{ __('common.buttons.edit') }}"><i class="bi bi-pencil"></i></a>
                                            <form method="POST" action="{{ route('knowledge-base.destroy', $entry) }}"
                                                onsubmit="return confirm('{{ __('knowledge_base.confirm_delete', ['title' => $entry->title]) }}');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-outline-danger btn-sm icon-btn" type="submit"
                                                    title="{{ __('common.buttons.delete') }}" aria-label="{{ __('common.buttons.delete') }}"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-3">
            {{ $knowledgeBaseEntries->links() }}
        </div>
    @endif
@endsection
