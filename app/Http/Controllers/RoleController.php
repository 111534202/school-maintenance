<?php

// 命名空間：這個類別所在的位置，要跟資料夾路徑對得上。
namespace App\Http\Controllers;

use App\Models\Role;                   // 身分資料表的模型
use App\Services\AuditLogger;          // 共用的操作紀錄寫入工具
use App\Support\PermissionCatalog;     // 「權限清單」：系統有哪些權限、怎麼分組，全部定義在這個檔案
use Illuminate\Http\RedirectResponse;  // 「導向別頁」的回應型別
use Illuminate\Http\Request;           // 這一次瀏覽器送來的請求
use Illuminate\Support\Str;            // 字串小工具（這裡用來產生隨機代碼）
use Illuminate\Validation\Rule;        // 進階驗證規則

/**
 * 身分主檔（像 Discord 的身分組）：新增身分後，從權限清單裡勾選要開放哪些權限。
 * 用戶主檔的「身分權限」下拉選單就是從這張表讀。路由整組需要 roles.manage 權限。
 *
 * 規則：
 * - 系統內建的五個身分（is_system）不能刪除，代碼（slug）也不能改，但可以調整名稱、說明與權限。
 * - 系統管理員（admin）永遠擁有全部權限，畫面上全部勾選且不能取消，後端也會忽略送來的勾選。
 * - 還有用戶使用中的身分不能刪除，請先把那些用戶改成別的身分。
 *
 * 【想新增一種權限（例如「保養管理」）】不用改這支 Controller：
 *   到 app/Support/PermissionCatalog.php 的 GROUPS 加上代碼，再到 lang/各語言資料夾/permissions.php 加中英文名稱，
 *   身分主檔的勾選畫面就會自動出現新的選項；要擋住某個頁面，路由加 ->middleware('can:新代碼') 即可。
 */
class RoleController extends Controller
{
    /** 身分列表（GET /roles）：可用關鍵字（名稱／說明）篩選，每頁 15 筆，並顯示每個身分有幾位用戶。 */
    public function index(Request $request)
    {
        $roles = Role::query()
            ->withCount('users')   // 順便算出「有幾位用戶屬於這個身分」，列表顯示用，也用來判斷能不能刪
            ->when($request->filled('keyword'), function ($query) use ($request) {
                $keyword = $request->string('keyword')->toString();
                // 名稱或說明包含關鍵字即可（括號包起來避免「或」影響其他條件）。
                $query->where(fn ($q) => $q->where('name', 'like', "%{$keyword}%")->orWhere('description', 'like', "%{$keyword}%"));
            })
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();   // 換頁時保留篩選條件

        return view('roles.index', compact('roles'));
    }

    /** 顯示新增身分表單；把權限清單（分組）交給畫面，畫面才畫得出一排排的勾選框。 */
    public function create()
    {
        return view('roles.create', ['groups' => PermissionCatalog::GROUPS]);
    }

    /** 接收新增表單（POST /roles）。 */
    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        $role = Role::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,   // ?? null：沒填說明就存成空值
            // 自訂身分的代碼是系統自動產生的內部識別，使用者看不到也不用管；中文名稱沒辦法拿來當代碼。
            'slug' => 'custom_' . Str::lower(Str::random(8)),
            'is_system' => false,   // 使用者自己建的身分一律不是「系統內建」，所以之後可以刪除
            // sanitize：只留下清單裡真的存在的權限代碼，防止有人偽造表單塞入不存在或亂寫的權限。
            'permissions' => PermissionCatalog::sanitize($data['permissions'] ?? []),
        ]);

        AuditLogger::log('created', $role, ['name' => $role->name, 'permissions' => $role->permissions]);

        return redirect()->route('roles.index')->with('success', __('roles.flash.created'));
    }

    /** 顯示編輯表單（GET /roles/{role}/edit）。 */
    public function edit(Role $role)
    {
        return view('roles.edit', ['role' => $role, 'groups' => PermissionCatalog::GROUPS]);
    }

    /** 接收編輯表單（PUT /roles/{role}）：改名稱、說明、權限勾選。 */
    public function update(Request $request, Role $role)
    {
        $data = $request->validate($this->rules($role));

        // 記住修改前實際生效的權限，等下才算得出「新增了哪些、移除了哪些」寫進操作紀錄。
        $before = $role->effectivePermissions();

        // fill：先把名稱、說明放進模型（這時還沒存進資料庫）。
        $role->fill([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        // 系統管理員永遠全開，不接受權限勾選的變更。
        if (! $role->isAdmin()) {
            $role->permissions = PermissionCatalog::sanitize($data['permissions'] ?? []);
        }

        $role->save();   // 這裡才真的寫進資料庫

        $after = $role->effectivePermissions();
        // array_diff 找出兩邊的差異：after 有 before 沒有 = 新增的權限；反過來 = 被移除的權限。
        // array_values 把陣列重新編號，存成 JSON 時才會是乾淨的清單。
        AuditLogger::log('updated', $role, [
            'name' => $role->name,
            'permissions_added' => array_values(array_diff($after, $before)),
            'permissions_removed' => array_values(array_diff($before, $after)),
        ]);

        return redirect()->route('roles.index')->with('success', __('roles.flash.updated'));
    }

    /** 刪除身分（DELETE /roles/{role}）：系統內建的、或還有人在用的都不能刪。 */
    public function destroy(Role $role): RedirectResponse
    {
        // 系統內建身分（管理員、主管等）是整個系統運作的基礎，不允許刪除。
        if ($role->is_system) {
            return back()->with('error', __('roles.errors.system_role'));
        }

        // withTrashed：連「已被軟刪除的用戶」也算進來，因為還原他們時需要這個身分還在。
        $userCount = $role->users()->withTrashed()->count();
        if ($userCount > 0) {
            return back()->with('error', __('roles.errors.in_use', ['count' => $userCount]));
        }

        $role->delete();

        AuditLogger::log('deleted', $role, ['name' => $role->name]);

        return redirect()->route('roles.index')->with('success', __('roles.flash.deleted'));
    }

    /** 新增／編輯共用的驗證規則。編輯時傳入 $role，名稱不可重複的檢查會排除自己。 */
    private function rules(?Role $role = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($role?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],   // 勾選的權限是一個陣列；一個都沒勾也可以
            // permissions.* 代表陣列裡的每一個元素：必須是文字，而且一定要在權限清單裡（Rule::in）。
            'permissions.*' => ['string', Rule::in(PermissionCatalog::all())],
        ];
    }
}
