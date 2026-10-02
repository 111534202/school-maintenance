<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * 部門主檔：新增、列表／篩選、修改、啟用／停用、刪除。
 * 用戶主檔與教室主檔的「部門」下拉選單都是從這張表讀（只列啟用中的部門）。
 * 路由整組需要 departments.manage 權限（見 routes/web.php）。
 *
 * 刪除規則：還有用戶或教室隸屬於這個部門時不能刪除（避免資料變成「不明部門」），
 * 請先把他們改到別的部門，或改用「停用」讓它從下拉選單消失但保留歷史資料。
 */
class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        $departments = Department::query()
            ->withCount(['users', 'classrooms'])
            ->when($request->filled('keyword'), function ($query) use ($request) {
                $keyword = $request->string('keyword')->toString();
                $query->where(function ($q) use ($keyword) {
                    $q->where('name', 'like', "%{$keyword}%")
                        ->orWhere('code', 'like', "%{$keyword}%")
                        ->orWhere('description', 'like', "%{$keyword}%");
                });
            })
            ->when($request->string('status')->toString() === 'active', fn ($query) => $query->where('is_active', true))
            ->when($request->string('status')->toString() === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('departments.index', compact('departments'));
    }

    public function create()
    {
        return view('departments.create');
    }

    public function store(Request $request)
    {
        $department = Department::create($this->validated($request));

        AuditLogger::log('created', $department, $department->only(['name', 'code', 'is_active']));

        return redirect()->route('departments.index')->with('success', __('departments.flash.created'));
    }

    public function edit(Department $department)
    {
        return view('departments.edit', compact('department'));
    }

    public function update(Request $request, Department $department)
    {
        $department->update($this->validated($request, $department));

        AuditLogger::log('updated', $department, $department->only(['name', 'code', 'is_active']));

        return redirect()->route('departments.index')->with('success', __('departments.flash.updated'));
    }

    public function toggle(Department $department): RedirectResponse
    {
        $department->update(['is_active' => ! $department->is_active]);

        AuditLogger::log('status_changed', $department, ['is_active' => $department->is_active]);

        return back()->with('success', $department->is_active ? __('departments.flash.activated') : __('departments.flash.deactivated'));
    }

    public function destroy(Department $department): RedirectResponse
    {
        $userCount = $department->users()->count();
        $classroomCount = $department->classrooms()->count();

        if ($userCount > 0 || $classroomCount > 0) {
            return back()->with('error', __('departments.errors.in_use', ['users' => $userCount, 'classrooms' => $classroomCount]));
        }

        $department->delete();

        AuditLogger::log('deleted', $department, $department->only(['name', 'code']));

        return redirect()->route('departments.index')->with('success', __('departments.flash.deleted'));
    }

    private function validated(Request $request, ?Department $department = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('departments', 'name')->ignore($department?->id)],
            'code' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('departments', 'code')->ignore($department?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
