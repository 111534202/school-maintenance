<?php

// 命名空間：這個類別所在的位置，要跟資料夾路徑對得上。
namespace App\Http\Controllers;

use App\Models\Classroom;      // 教室資料表的模型
use App\Models\Department;     // 部門資料表的模型（教室所屬部門的下拉選單來源）
use App\Models\User;           // 用戶資料表的模型（教室「管理人」的下拉選單來源）
use App\Services\AuditLogger;  // 共用的操作紀錄寫入工具
use Illuminate\Http\Request;   // 這一次瀏覽器送來的請求

/**
 * 教室主檔：列表／篩選、新增、編輯、啟用／停用（需要 classrooms.manage 權限，見 routes/web.php）。
 * 教室不提供刪除，只能停用，這樣歷史的設備與報修資料才不會失去所屬教室。
 *
 * 【Controller 基本觀念】請先看 UserController.php 檔頭；每個 public 方法對應一個網址動作。
 * 【想新增教室欄位】migration 加欄位 → Models/Classroom.php 的 $fillable → 本檔 validated() 加規則
 *   → resources/views/classrooms/_form.blade.php 加輸入框、index.blade.php 加顯示欄。
 */
class ClassroomController extends Controller
{
    /** 教室列表（GET /classrooms）：可依關鍵字（教室代碼／名稱）、部門、啟用狀態篩選，每頁 15 筆。 */
    public function index(Request $request)
    {
        // with(...)：順便查好所屬部門與管理人，畫面顯示名稱時不用每間教室再多查一次。
        $classrooms = Classroom::with(['department', 'manager'])
            ->when($request->filled('keyword'), function ($query) use ($request) {
                $keyword = $request->string('keyword');
                // 教室代碼或名稱包含關鍵字即可；括號包起來避免「或」影響其他篩選。
                $query->where(function ($q) use ($keyword) {
                    $q->where('room_code', 'like', "%{$keyword}%")
                      ->orWhere('room_name', 'like', "%{$keyword}%");
                });
            })
            // 部門篩選：下拉選單送來的是部門編號。
            ->when($request->filled('department_id'), fn ($query) => $query->where('department_id', $request->integer('department_id')))
            // 啟用篩選：網址上 is_active=1 代表「啟用」，其他值（0）代表「停用」。
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->string('is_active') === '1'))
            ->orderBy('room_code')   // 依教室代碼排序
            ->paginate(15)
            ->withQueryString();     // 換頁時保留篩選條件

        // 篩選列的部門下拉選單：列出全部部門（含停用的），才篩得到舊資料。
        $departments = Department::orderBy('name')->get();

        return view('classrooms.index', compact('classrooms', 'departments'));
    }

    /** 顯示新增教室表單（GET /classrooms/create）。 */
    public function create()
    {
        // 部門下拉選單從部門主檔讀，只列啟用中的部門。
        $departments = Department::forSelect()->get();
        $managers = User::orderBy('name')->get();   // 管理人下拉選單：所有用戶

        return view('classrooms.create', compact('departments', 'managers'));
    }

    /** 接收新增表單（POST /classrooms）。 */
    public function store(Request $request)
    {
        // validated() 在檔案最下面，驗證不過會自動導回表單並顯示錯誤，下面不會執行。
        $data = $this->validated($request);

        $classroom = Classroom::create($data);

        AuditLogger::log('created', $classroom, $data);

        return redirect()->route('classrooms.index')->with('success', '教室已新增。');
    }

    /** 顯示編輯表單（GET /classrooms/{classroom}/edit）。 */
    public function edit(Classroom $classroom)
    {
        // 這間教室目前所屬的部門即使被停用，也要保留在選項裡。
        $departments = Department::forSelect($classroom->department_id)->get();
        $managers = User::orderBy('name')->get();

        return view('classrooms.edit', compact('classroom', 'departments', 'managers'));
    }

    /** 接收編輯表單（PUT /classrooms/{classroom}）。 */
    public function update(Request $request, Classroom $classroom)
    {
        // 傳入自己的編號，讓「教室代碼不可重複」的檢查排除自己。
        $data = $this->validated($request, $classroom->id);

        $classroom->update($data);

        AuditLogger::log('updated', $classroom, $data);

        return redirect()->route('classrooms.index')->with('success', '教室已更新。');
    }

    /** 啟用／停用切換（列表上的快速按鈕）：目前啟用就變停用，停用就變啟用。 */
    public function toggle(Classroom $classroom)
    {
        // ! 是「反過來」：true 變 false、false 變 true。
        $classroom->update(['is_active' => !$classroom->is_active]);

        AuditLogger::log('status_changed', $classroom, ['is_active' => $classroom->is_active]);

        return redirect()->route('classrooms.index')
            ->with('success', $classroom->is_active ? '教室已啟用。' : '教室已停用。');
    }

    /**
     * 新增／編輯共用的驗證規則（想改欄位限制就改這裡）。
     * $ignoreId：編輯時傳入自己的編號，唯一性檢查才不會把自己算成「重複」。
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'department_id' => ['nullable', 'exists:departments,id'],   // 部門可不選；有選就必須是真的存在的部門
            'campus' => ['required', 'string', 'max:255'],              // 校區
            'building' => ['required', 'string', 'max:255'],            // 大樓
            'floor' => ['required', 'string', 'max:255'],               // 樓層
            // 教室代碼：必填且不可重複。'unique:資料表,欄位,要排除的編號' 是 Laravel 的簡寫寫法。
            'room_code' => ['required', 'string', 'max:255', 'unique:classrooms,room_code' . ($ignoreId ? ",{$ignoreId}" : '')],
            'room_name' => ['required', 'string', 'max:255'],           // 教室名稱
            'room_type' => ['nullable', 'string', 'max:255'],           // 教室類型，可不填
            'manager_id' => ['nullable', 'exists:users,id'],            // 管理人可不選；有選就必須是真的存在的用戶
        ]);
    }
}
