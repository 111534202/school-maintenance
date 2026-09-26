<?php

namespace App\Http\Controllers;

use App\Models\KnowledgeBase;
use App\Http\Requests\StoreKnowledgeBaseRequest;
use App\Http\Requests\UpdateKnowledgeBaseRequest;

class KnowledgeBaseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $knowledgeBaseEntries = KnowledgeBase::latest()->paginate(10);

        return view('knowledge-base.index', compact('knowledgeBaseEntries'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('knowledge-base.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreKnowledgeBaseRequest $request)
    {
        KnowledgeBase::create($request->validated());

        return redirect()
            ->route('knowledge-base.index')
            ->with('status', '已新增知識庫項目。');
    }

    /**
     * Display the specified resource.
     */
    public function show(KnowledgeBase $knowledgeBase)
    {
        return view('knowledge-base.show', compact('knowledgeBase'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(KnowledgeBase $knowledgeBase)
    {
        return view('knowledge-base.edit', compact('knowledgeBase'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateKnowledgeBaseRequest $request, KnowledgeBase $knowledgeBase)
    {
        $knowledgeBase->update($request->validated());

        return redirect()
            ->route('knowledge-base.index')
            ->with('status', '已更新知識庫項目。');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(KnowledgeBase $knowledgeBase)
    {
        $knowledgeBase->delete();

        return redirect()
            ->route('knowledge-base.index')
            ->with('status', '已刪除知識庫項目。');
    }

    /**
     * 自助除錯頁「問題已解決」最小流程（依《第四週進度安排》第 2 項）。
     * 只做跳轉並顯示感謝訊息，不記錄額外狀態或统计。
     */
    public function resolved(KnowledgeBase $knowledgeBase)
    {
        return redirect()
            ->route('knowledge-base.index')
            ->with('status', "太好了，很高興「{$knowledgeBase->title}」幫你解決了問題！");
    }
}
