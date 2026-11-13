{{-- 自助排除知識庫列表頁（對應 KnowledgeBaseController::index）：所有登入者都能看；新增／編輯／刪除按鈕只有 knowledge-base.manage 權限者看得到。 --}}
{{-- （Blade 的基本觀念見 layouts/app.blade.php 檔頭。） --}}
{{-- 套用主版面（導覽列、側邊選單都由它提供）。 --}}
@extends('layouts.app')

@section('title', __('knowledge_base.index_title'))

{{-- 從這裡開始是「放進主版面中間」的內容，到 @endsection 結束。 --}}
@section('content')
<div class="tb">
    <div class="tb-header">
        <div>
            <h1 class="tb-title">{{ __('knowledge_base.index_heading') }}</h1>
        </div>
        {{-- @can：有「管理知識庫」權限才顯示「新增」按鈕。 --}}
        @can('knowledge-base.manage')
            <a class="tb-btn tb-btn-primary" href="{{ route('knowledge-base.create') }}" data-modal-frame data-modal-title="{{ __('knowledge_base.add_entry') }}" data-modal-size="xl">
                <i class="bi bi-plus-lg"></i>{{ __('knowledge_base.add_entry') }}
            </a>
        @endcan
    </div>

    {{-- 搜尋：關鍵字比對標題、分類、故障現象與排除步驟；也可以只看某一個分類。 --}}
    @php
        $searching = $keyword !== '' || $category !== '';
    @endphp
    <div class="tb-card tb-card-pad">
        <div>
            {{-- 搜尋列：用全站共用的 filter-bar，洋蔥式會收成「篩選條件」摘要列，點開從右邊滑出（見 layouts/partials/filter-onion）。 --}}
            <form method="GET" action="{{ route('knowledge-base.index') }}" class="filter-bar tb-form-row tb-filter" data-no-onion role="search">
                <div>
                    <label for="kb-keyword" class="form-label small mb-1">{{ __('knowledge_base.search.keyword') }}</label>
                    <input type="search" id="kb-keyword" name="keyword" class="form-control form-control-sm" style="width: 18rem;" value="{{ $keyword }}"
                        placeholder="{{ __('knowledge_base.search.placeholder') }}">
                </div>
                <div>
                    <label for="kb-category" class="form-label small mb-1">{{ __('knowledge_base.search.category') }}</label>
                    <select id="kb-category" name="category" class="form-select form-select-sm" style="width: 10rem;">
                        <option value="">{{ __('knowledge_base.search.all_categories') }}</option>
                        @foreach ($categories as $option)
                            <option value="{{ $option }}" @selected($category === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
                @include('layouts.partials.filter-actions', ['reset' => route('knowledge-base.index'), 'active' => $searching])
            </form>
            @if ($searching)
                <div class="small text-muted mt-2">{{ __('knowledge_base.search.result_count', ['count' => $knowledgeBaseEntries->total()]) }}</div>
            @endif
        </div>
    </div>

    {{-- 沒有任何文章時顯示提示；否則顯示表格。 --}}
    @if ($knowledgeBaseEntries->isEmpty())
        <div class="tb-card tb-empty">{{ $searching ? __('knowledge_base.search.no_results') : __('knowledge_base.empty_list') }}</div>
    @else
        <section class="tb-table-wrap" data-no-fit>
            <div class="table-responsive">
                {{-- 資料表格（有框線）。 --}}
                <table class="tb-table tb-table-hover">
                    <thead>
                        <tr>
                            <th>{{ __('knowledge_base.table.title') }}</th>
                            <th>{{ __('knowledge_base.table.category') }}</th>
                            <th class="text-center">{{ __('knowledge_base.table.status') }}</th>
                            <th>{{ __('knowledge_base.table.updated_at') }}</th>
                            {{-- 查看次數：打開文章一次加 1，列表依這個數字由多到少排；所有人都看得到。 --}}
                            <th class="text-end text-nowrap">查看次數</th>
                            <th class="text-end tb-col-fit">{{ __('knowledge_base.table.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- 逐一列出每篇文章。 --}}
                        @foreach ($knowledgeBaseEntries as $entry)
                            {{-- 點這一列的任何地方都會在畫面中的對話框打開文章（data-modal-frame-href，行為在共用版型）。 --}}
                            <tr data-modal-frame-href="{{ route('knowledge-base.show', $entry) }}" data-modal-title="知識庫文章" data-modal-size="xl" tabindex="0" role="link">
                                <td class="fw-semibold">{{ $entry->title }}</td>
                                <td>{{ $entry->category ?? __('knowledge_base.uncategorized') }}</td>
                                <td class="text-center">
                                    {{-- 上架狀態徽章：綠色「已上架」或灰色「未上架」。 --}}
                                    @if ($entry->is_published)
                                        <span class="badge tb-pill tb-pill-ok">{{ __('knowledge_base.published') }}</span>
                                    @else
                                        <span class="badge tb-pill tb-pill-muted">{{ __('knowledge_base.unpublished') }}</span>
                                    @endif
                                </td>
                                {{-- 最後更新時間，格式化成「年-月-日 時:分」。 --}}
                                <td>{{ $entry->updated_at->format('Y-m-d H:i') }}</td>
                                <td class="text-end tb-num">
                                    {{-- 查看次數：灰色、不能按的膠囊（眼睛圖示＋次數）。 --}}
                                    <span class="tb-view-count" title="查看次數" data-onion-keep><i class="bi bi-eye" aria-hidden="true"></i>{{ number_format($entry->view_count) }}</span>
                                </td>
                                <td class="text-end tb-col-fit">
                                    <div class="d-inline-flex gap-1">
                                        {{-- 閱讀文章：點這一列在對話框打開（不另外放「查看」按鈕）。 --}}
                                        @can('knowledge-base.manage')
                                            <a class="btn btn-outline-primary btn-sm icon-btn" href="{{ route('knowledge-base.edit', $entry) }}" data-modal-frame data-modal-title="修改文章" data-modal-size="lg"
                                                title="{{ __('common.buttons.edit') }}" aria-label="{{ __('common.buttons.edit') }}"><i class="bi bi-pencil"></i></a>
                                            {{-- 刪除按鈕（垃圾桶圖示）：只有管理權限者看得到，送出前先確認。 --}}
                                            <form method="POST" action="{{ route('knowledge-base.destroy', $entry) }}"
                                                data-confirm="{{ __('knowledge_base.confirm_delete', ['title' => $entry->title]) }}">
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
        </section>

        <div>
            {{-- 分頁按鈕。 --}}
            @include('layouts.pager', ['paginator' => $knowledgeBaseEntries])
        </div>
    @endif
</div>
@endsection
