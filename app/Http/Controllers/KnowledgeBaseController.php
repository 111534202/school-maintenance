<?php

// 命名空間：這個類別所在的位置，要跟資料夾路徑對得上。
namespace App\Http\Controllers;

use App\Models\KnowledgeBase;                        // 知識庫文章資料表的模型
use App\Http\Requests\StoreKnowledgeBaseRequest;     // 「新增文章」表單的驗證規則
use App\Http\Requests\UpdateKnowledgeBaseRequest;    // 「編輯文章」表單的驗證規則
use App\Services\AuditLogger;                        // 共用的操作紀錄寫入工具

/**
 * 自助排除知識庫的網頁功能：列表、查看、新增、修改、刪除（標準 CRUD），
 * 再加上「問題已解決」這個小功能。
 * 所有登入者都能看（列表、查看）；新增／修改／刪除需要 knowledge-base.manage 權限（見 routes/web.php）。
 *
 * 這裡的驗證不是寫在 Controller，而是獨立的 FormRequest 類別（app/Http/Requests），
 * 方法參數寫 StoreKnowledgeBaseRequest 時，Laravel 會在進到方法之前先驗證，不通過就自動導回表單。
 */
class KnowledgeBaseController extends Controller
{
    /** 知識庫文章列表頁，最新的排前面，每頁 10 筆。 */
    public function index()
    {
        // latest()：依建立時間由新到舊；paginate(10)：分頁，每頁 10 筆。
        $knowledgeBaseEntries = KnowledgeBase::latest()->paginate(10);

        return view('knowledge-base.index', compact('knowledgeBaseEntries'));
    }

    /** 顯示「新增知識庫項目」的空白表單。 */
    public function create()
    {
        return view('knowledge-base.create');
    }

    /** 使用者送出「新增」表單後，把資料存進資料庫。 */
    public function store(StoreKnowledgeBaseRequest $request)
    {
        // validated()：只取出「通過驗證的欄位」，避免有人偷塞表單裡沒有的欄位進資料庫。
        $knowledgeBase = KnowledgeBase::create($request->validated());

        AuditLogger::log('created', $knowledgeBase, ['title' => $knowledgeBase->title, 'is_published' => $knowledgeBase->is_published]);

        return redirect()
            ->route('knowledge-base.index')
            ->with('success', __('knowledge_base.flash.created'));
    }

    /** 單篇知識庫文章的詳細頁，底下會有「問題已解決」／「前往報修」兩個按鈕。 */
    public function show(KnowledgeBase $knowledgeBase)
    {
        return view('knowledge-base.show', compact('knowledgeBase'));
    }

    /** 顯示「編輯」表單，內容預先帶入這篇文章目前的資料。 */
    public function edit(KnowledgeBase $knowledgeBase)
    {
        return view('knowledge-base.edit', compact('knowledgeBase'));
    }

    /** 使用者送出「編輯」表單後，更新資料庫裡的資料。 */
    public function update(UpdateKnowledgeBaseRequest $request, KnowledgeBase $knowledgeBase)
    {
        $knowledgeBase->update($request->validated());

        AuditLogger::log('updated', $knowledgeBase, ['title' => $knowledgeBase->title, 'is_published' => $knowledgeBase->is_published]);

        return redirect()
            ->route('knowledge-base.index')
            ->with('success', __('knowledge_base.flash.updated'));
    }

    /** 刪除這篇知識庫文章。 */
    public function destroy(KnowledgeBase $knowledgeBase)
    {
        $knowledgeBase->delete();

        AuditLogger::log('deleted', $knowledgeBase);

        return redirect()
            ->route('knowledge-base.index')
            ->with('success', __('knowledge_base.flash.deleted'));
    }

    /**
     * 自助除錯頁「問題已解決」最小流程。
     * 只做跳轉並顯示感謝訊息，不記錄額外狀態或統計數字（規格明確不要求這週做這個）。
     */
    public function resolved(KnowledgeBase $knowledgeBase)
    {
        return redirect()
            ->route('knowledge-base.index')
            ->with('success', __('knowledge_base.flash.resolved_message', ['title' => $knowledgeBase->title]));
    }
}
