{{-- 自助排除知識庫列表頁（對應 KnowledgeBaseController::index）：所有登入者都能看；新增／編輯／刪除按鈕只有 knowledge-base.manage 權限者看得到。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 套用主版面（導覽列、側邊選單都由它提供）。 --}}
@extends('layouts.app')

@section('title', __('knowledge_base.index_title'))

{{-- 從這裡開始是「放進主版面中間」的內容，到 @endsection 結束。 --}}
@section('content')
    <div class="page-header mb-4">
        <h1 class="h4 mb-0"><i class="bi bi-book me-2"></i>{{ __('knowledge_base.index_heading') }}</h1>
        {{-- @can：有「管理知識庫」權限才顯示「新增」按鈕。 --}}
        @can('knowledge-base.manage')
            <a class="btn btn-primary" href="{{ route('knowledge-base.create') }}">
                <i class="bi bi-plus-lg me-1"></i>{{ __('knowledge_base.add_entry') }}
            </a>
        @endcan
    </div>

    {{-- 沒有任何文章時顯示提示；否則顯示表格。 --}}
    @if ($knowledgeBaseEntries->isEmpty())
        <div class="alert alert-info mb-0">{{ __('knowledge_base.empty_list') }}</div>
    @else
        <div class="card shadow-sm">
            <div class="table-responsive">
                {{-- 資料表格（有框線）。 --}}
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
                        {{-- 逐一列出每篇文章。 --}}
                        @foreach ($knowledgeBaseEntries as $entry)
                            <tr>
                                <td><a href="{{ route('knowledge-base.show', $entry) }}" class="text-decoration-none">{{ $entry->title }}</a></td>
                                <td>{{ $entry->category ?? __('knowledge_base.uncategorized') }}</td>
                                <td class="text-center">
                                    {{-- 上架狀態徽章：綠色「已上架」或灰色「未上架」。 --}}
                                    @if ($entry->is_published)
                                        <span class="badge text-bg-success">{{ __('knowledge_base.published') }}</span>
                                    @else
                                        <span class="badge text-bg-secondary">{{ __('knowledge_base.unpublished') }}</span>
                                    @endif
                                </td>
                                {{-- 最後更新時間，格式化成「年-月-日 時:分」。 --}}
                                <td>{{ $entry->updated_at->format('Y-m-d H:i') }}</td>
                                <td class="text-center">
                                    <div class="d-inline-flex gap-1">
                                        {{-- 檢視按鈕（眼睛圖示）：所有人都有。 --}}
                                        <a class="btn btn-outline-secondary btn-sm icon-btn" href="{{ route('knowledge-base.show', $entry) }}"
                                            title="{{ __('common.buttons.view') }}" aria-label="{{ __('common.buttons.view') }}"><i class="bi bi-eye"></i></a>
                                        @can('knowledge-base.manage')
                                            <a class="btn btn-outline-primary btn-sm icon-btn" href="{{ route('knowledge-base.edit', $entry) }}"
                                                title="{{ __('common.buttons.edit') }}" aria-label="{{ __('common.buttons.edit') }}"><i class="bi bi-pencil"></i></a>
                                            {{-- 刪除按鈕（垃圾桶圖示）：只有管理權限者看得到，送出前先確認。 --}}
                                            <form method="POST" action="{{ route('knowledge-base.destroy', $entry) }}"
                                                onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('knowledge_base.confirm_delete', ['title' => $entry->title])) }});">
                                                @csrf
                                                {{-- 用隱藏欄位把 POST 偽裝成 DELETE。 --}}
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
            {{-- 分頁按鈕。 --}}
            {{ $knowledgeBaseEntries->links() }}
        </div>
    @endif
@endsection
