<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Services\AuditLogger;
use App\Support\PermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * 身分主檔（像 Discord 的身分組）：新增身分後，從權限清單裡勾選要開放哪些權限。
 * 用戶主檔的「身分權限」下拉選單就是從這張表讀。路由整組需要 roles.manage 權限。
 *
 * 規則：
 * - 系統內建的五個身分（is_system）不能刪除，代碼（slug）也不能改，但可以調整名稱、說明與權限。
 * - 系統管理員（admin）永遠擁有全部權限，畫面上全部勾選且不能取消，後端也會忽略送來的勾選。
 * - 還有用戶使用中的身分不能刪除，請先把那些用戶改成別的身分。
 */
class RoleController extends Controller
{
    public function index(Request $request)
    {
        $roles = Role::query()
            ->withCount('users')
            ->when($request->filled('keyword'), function ($query) use ($request) {
                $keyword = $request->string('keyword')->toString();
                $query->where(fn ($q) => $q->where('name', 'like', "%{$keyword}%")->orWhere('description', 'like', "%{$keyword}%"));
            })
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('roles.index', compact('roles'));
    }

    public function create()
    {
        return view('roles.create', ['groups' => PermissionCatalog::GROUPS]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        $role = Role::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            // 自訂身分的代碼是系統自動產生的內部識別，使用者看不到也不用管；中文名稱沒辦法拿來當代碼。
            'slug' => 'custom_' . Str::lower(Str::random(8)),
            'is_system' => false,
            'permissions' => PermissionCatalog::sanitize($data['permissions'] ?? []),
        ]);

        AuditLogger::log('created', $role, ['name' => $role->name, 'permissions' => $role->permissions]);

        return redirect()->route('roles.index')->with('success', __('roles.flash.created'));
    }

    public function edit(Role $role)
    {
        return view('roles.edit', ['role' => $role, 'groups' => PermissionCatalog::GROUPS]);
    }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate($this->rules($role));

        $before = $role->effectivePermissions();

        $role->fill([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        // 系統管理員永遠全開，不接受權限勾選的變更。
        if (! $role->isAdmin()) {
            $role->permissions = PermissionCatalog::sanitize($data['permissions'] ?? []);
        }

        $role->save();

        $after = $role->effectivePermissions();
        AuditLogger::log('updated', $role, [
            'name' => $role->name,
            'permissions_added' => array_values(array_diff($after, $before)),
            'permissions_removed' => array_values(array_diff($before, $after)),
        ]);

        return redirect()->route('roles.index')->with('success', __('roles.flash.updated'));
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->is_system) {
            return back()->with('error', __('roles.errors.system_role'));
        }

        $userCount = $role->users()->withTrashed()->count();
        if ($userCount > 0) {
            return back()->with('error', __('roles.errors.in_use', ['count' => $userCount]));
        }

        $role->delete();

        AuditLogger::log('deleted', $role, ['name' => $role->name]);

        return redirect()->route('roles.index')->with('success', __('roles.flash.deleted'));
    }

    private function rules(?Role $role = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($role?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(PermissionCatalog::all())],
        ];
    }
}
