<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * 用戶主檔（帳號管理）：列表／篩選、新增、編輯、啟用／停用、密碼重設、刪除（軟刪除）與還原。
 * 路由整組只開放 admin 角色（見 routes/web.php），資訊組主管等其他角色看不到也進不來。
 *
 * 一般企業帳號管理會有的防呆規則都集中在這支 Controller：
 * - 不能刪除、停用自己，也不能把自己的角色降級（避免把自己鎖在系統外面）。
 * - 系統一定要留著至少一位「啟用中的系統管理員」，最後一位不能被刪除、停用或降級。
 * - 停用、刪除、重設密碼後，該帳號目前已登入的連線（sessions）會被立刻踢掉。
 * - 刪除是軟刪除，帳號與歷史紀錄（報修單、操作紀錄）保留，可以還原。
 * 所有異動都寫進共用的 audit_logs（AuditLogger），密碼不會寫進紀錄。
 */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->string('status')->toString();

        $users = User::query()
            ->with(['role', 'department'])
            ->when($status === 'deleted', fn ($query) => $query->onlyTrashed())
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($request->filled('keyword'), function ($query) use ($request) {
                $keyword = $request->string('keyword')->toString();
                $query->where(function ($q) use ($keyword) {
                    $q->where('username', 'like', "%{$keyword}%")
                        ->orWhere('name', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%")
                        ->orWhere('phone', 'like', "%{$keyword}%");
                });
            })
            ->when($request->filled('role_id'), fn ($query) => $query->where('role_id', $request->integer('role_id')))
            ->when($request->filled('department_id'), fn ($query) => $query->where('department_id', $request->integer('department_id')))
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'roles' => Role::orderBy('id')->get(),
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('users.create', $this->formData());
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules() + [
            'password' => ['required', Password::min(8), 'confirmed'],
        ]);

        $user = User::create($data);

        AuditLogger::log('created', $user, Arr::except($data, ['password']));

        return redirect()->route('users.index')->with('success', __('users.flash.created'));
    }

    public function edit(User $user)
    {
        return view('users.edit', $this->formData($user->department_id) + ['user' => $user]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate($this->rules($user));

        $newRole = Role::find($data['role_id']);
        $willBeAdmin = $newRole?->slug === 'admin';
        $willBeActive = (bool) $data['is_active'];

        // 不能把自己降級或停用自己，不然會把自己鎖在系統外面。
        if ($user->id === Auth::id() && ($data['role_id'] != $user->role_id || ! $willBeActive)) {
            return back()->withInput()->with('error', __('users.errors.self_protected'));
        }

        // 最後一位啟用中的管理員不能被降級或停用。
        if ($this->isLastActiveAdmin($user) && (! $willBeAdmin || ! $willBeActive)) {
            return back()->withInput()->with('error', __('users.errors.last_admin'));
        }

        $wasActive = $user->is_active;
        $user->update($data);

        AuditLogger::log('updated', $user, $data);

        if ($wasActive && ! $user->is_active) {
            $this->revokeSessions($user);
        }

        return redirect()->route('users.index')->with('success', __('users.flash.updated'));
    }

    /** 啟用／停用切換（列表上的快速按鈕）。 */
    public function toggle(User $user): RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', __('users.errors.self_protected'));
        }

        if ($user->is_active && $this->isLastActiveAdmin($user)) {
            return back()->with('error', __('users.errors.last_admin'));
        }

        $user->update(['is_active' => ! $user->is_active]);

        AuditLogger::log('status_changed', $user, ['is_active' => $user->is_active]);

        if (! $user->is_active) {
            $this->revokeSessions($user);
        }

        return back()->with('success', $user->is_active ? __('users.flash.activated') : __('users.flash.deactivated'));
    }

    /** 管理員直接幫使用者設定新密碼（使用者忘記密碼時用）。 */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', Password::min(8), 'confirmed'],
        ]);

        $user->update(['password' => $data['password']]);

        // 密碼本身絕對不寫進操作紀錄，只記「重設過密碼」這件事。
        AuditLogger::log('password_reset', $user);
        $this->revokeSessions($user);

        return back()->with('success', __('users.flash.password_reset'));
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', __('users.errors.self_protected'));
        }

        if ($this->isLastActiveAdmin($user)) {
            return back()->with('error', __('users.errors.last_admin'));
        }

        $user->delete();

        AuditLogger::log('deleted', $user);
        $this->revokeSessions($user);

        return redirect()->route('users.index')->with('success', __('users.flash.deleted'));
    }

    /** 還原被刪除（軟刪除）的帳號。路由有加 withTrashed()，才找得到已刪除的帳號。 */
    public function restore(User $user): RedirectResponse
    {
        $user->restore();

        AuditLogger::log('restored', $user);

        return redirect()->route('users.index')->with('success', __('users.flash.restored'));
    }

    /** 新增／編輯共用的驗證規則；編輯時要排除自己，帳號與 Email 才不會跟自己衝突。 */
    private function rules(?User $user = null): array
    {
        return [
            'username' => [
                'required', 'string', 'min:3', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($user?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role_id' => ['required', 'exists:roles,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** 身分與部門下拉選單的來源：身分主檔、部門主檔（部門只列啟用中的，已選的那個即使被停用也保留）。 */
    private function formData(?int $currentDepartmentId = null): array
    {
        return [
            'roles' => Role::orderBy('id')->get(),
            'departments' => Department::forSelect($currentDepartmentId)->get(),
        ];
    }

    /** 這個帳號是不是系統裡「最後一位啟用中的系統管理員」。 */
    private function isLastActiveAdmin(User $user): bool
    {
        if (! $user->isAdmin() || ! $user->is_active || $user->trashed()) {
            return false;
        }

        return ! User::query()
            ->where('id', '!=', $user->id)
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->where('slug', 'admin'))
            ->exists();
    }

    /** 把這個帳號目前所有已登入的連線踢掉（sessions 用資料庫儲存時才有這張表可清）。 */
    private function revokeSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }
}
