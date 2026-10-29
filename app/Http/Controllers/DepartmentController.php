<?php

// 命名空間：這個類別所在的位置，要跟資料夾路徑對得上。
namespace App\Http\Controllers;

use App\Models\Department;             // 部門資料表的模型（操作 departments 資料表都透過它）
use App\Services\AuditLogger;          // 共用的操作紀錄寫入工具
use Illuminate\Http\RedirectResponse;  // 「導向別頁」的回應型別
use Illuminate\Http\Request;           // 這一次瀏覽器送來的請求（表單內容、網址參數）
use Illuminate\Validation\Rule;        // 進階驗證規則（例如「不可重複但排除自己」）

/**
 * 部門主檔：新增、列表／篩選、修改、啟用／停用、刪除。
 * 用戶主檔與教室主檔的「部門」下拉選單都是從這張表讀（只列啟用中的部門）。
 * 路由整組需要 departments.manage 權限（見 routes/web.php）。
 *
 * 刪除規則：還有用戶或教室隸屬於這個部門時不能刪除（避免資料變成「不明部門」），
 * 請先把他們改到別的部門，或改用「停用」讓它從下拉選單消失但保留歷史資料。
 *
 * 【Controller 基本觀念】請先看 UserController.php 檔頭；每個 public 方法對應一個網址動作。
 * 【想新增部門欄位】migration 加欄位 → Models/Department.php 的 $fillable → 本檔 validated() 加規則
 *   → resources/views/departments/_form.blade.php 加輸入框 → lang/各語言資料夾/departments.php 加文字。
 */
class DepartmentController extends Controller
{
    /** 部門列表（GET /departments）：可用關鍵字（名稱／代碼／說明）與啟用狀態篩選，每頁 15 筆。 */
    public function index(Request $request)
    {
        $departments = Department::query()
            // withCount：順便算出「有幾位用戶、幾間教室屬於這個部門」，列表上要顯示，也用來提示能不能刪除。
            ->withCount(['users', 'classrooms'])
            // 有填關鍵字才套用；括號包起來，確保「或」只在三個欄位之間生效。
            ->when($request->filled('keyword'), function ($query) use ($request) {
                $keyword = $request->string('keyword')->toString();
                $query->where(function ($q) use ($keyword) {
                    $q->where('name', 'like', "%{$keyword}%")        // like %字% = 包含這段字
                        ->orWhere('code', 'like', "%{$keyword}%")
                        ->orWhere('description', 'like', "%{$keyword}%");
                });
            })
            // 啟用狀態篩選：選「啟用」只看 is_active=true，選「停用」只看 false，沒選就都看。
            ->when($request->string('status')->toString() === 'active', fn ($query) => $query->where('is_active', true))
            ->when($request->string('status')->toString() === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('id')       // 依編號由小到大
            ->paginate(15)        // 分頁，每頁 15 筆
            ->withQueryString();  // 換頁時保留篩選條件

        // compact('departments') 等於 ['departments' => $departments]，把資料交給畫面。
        return view('departments.index', compact('departments'));
    }

    /** 顯示新增部門表單（GET /departments/create）。 */
    public function create()
    {
        return view('departments.create');
    }

    /** 接收新增表單（POST /departments）：驗證通過就建立，並寫操作紀錄。 */
    public function store(Request $request)
    {
        // validated() 在檔案最下面，驗證不過會自動導回表單並顯示錯誤，不會繼續往下執行。
        $department = Department::create($this->validated($request));

        // 記錄「新增了部門」，只挑名稱、代碼、啟用狀態三個欄位放進紀錄。
        AuditLogger::log('created', $department, $department->only(['name', 'code', 'is_active']));

        return redirect()->route('departments.index')->with('success', __('departments.flash.created'));
    }

    /** 顯示編輯表單（GET /departments/{department}/edit）；Department $department 由網址上的編號自動帶入。 */
    public function edit(Department $department)
    {
        return view('departments.edit', compact('department'));
    }

    /** 接收編輯表單（PUT /departments/{department}）。 */
    public function update(Request $request, Department $department)
    {
        // 傳入 $department 是為了讓「名稱／代碼不可重複」的檢查排除自己。
        $department->update($this->validated($request, $department));

        AuditLogger::log('updated', $department, $department->only(['name', 'code', 'is_active']));

        return redirect()->route('departments.index')->with('success', __('departments.flash.updated'));
    }

    /** 啟用／停用切換（列表上的快速按鈕）。停用後該部門不會出現在用戶、教室的下拉選單，但舊資料保留。 */
    public function toggle(Department $department): RedirectResponse
    {
        // ! 是「反過來」：true 變 false、false 變 true。
        $department->update(['is_active' => ! $department->is_active]);

        AuditLogger::log('status_changed', $department, ['is_active' => $department->is_active]);

        // 依切換後的結果顯示「已啟用」或「已停用」。
        return back()->with('success', $department->is_active ? __('departments.flash.activated') : __('departments.flash.deactivated'));
    }

    /** 刪除部門（DELETE /departments/{department}）。有人或教室還在用就擋下來。 */
    public function destroy(Department $department): RedirectResponse
    {
        // 算出還有幾位用戶、幾間教室屬於這個部門。
        $userCount = $department->users()->count();
        $classroomCount = $department->classrooms()->count();

        // 只要有任何一個還在用，就不能刪，並告訴使用者各有幾筆。
        if ($userCount > 0 || $classroomCount > 0) {
            return back()->with('error', __('departments.errors.in_use', ['users' => $userCount, 'classrooms' => $classroomCount]));
        }

        $department->delete();

        AuditLogger::log('deleted', $department, $department->only(['name', 'code']));

        return redirect()->route('departments.index')->with('success', __('departments.flash.deleted'));
    }

    /**
     * 新增／編輯共用的驗證規則（想改欄位限制就改這裡）。
     * 編輯時傳入 $department，唯一性檢查會排除它自己，這樣沒改名稱直接存檔才不會被說「名稱已存在」。
     */
    private function validated(Request $request, ?Department $department = null): array
    {
        return $request->validate([
            // 名稱：必填、最長 255 字、不可與其他部門重複。
            'name' => ['required', 'string', 'max:255', Rule::unique('departments', 'name')->ignore($department?->id)],
            // 代碼：可以不填；有填的話只能是英數與 . _ -，而且不可重複。
            'code' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('departments', 'code')->ignore($department?->id)],
            'description' => ['nullable', 'string', 'max:255'],   // 說明：可不填
            'is_active' => ['required', 'boolean'],               // 是否啟用：true / false
        ]);
    }
}
