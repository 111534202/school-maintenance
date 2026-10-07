<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Department;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClassroomController extends Controller
{
    public function index(Request $request)
    {
        $classrooms = Classroom::with(['department', 'manager'])
            ->when($request->filled('keyword'), function ($query) use ($request) {
                $keyword = $request->string('keyword');
                $query->where(function ($q) use ($keyword) {
                    $q->where('room_code', 'like', "%{$keyword}%")
                      ->orWhere('room_name', 'like', "%{$keyword}%");
                });
            })
            ->when($request->filled('department_id'), fn ($query) => $query->where('department_id', $request->integer('department_id')))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->string('is_active') === '1'))
            ->orderBy('room_code')
            ->paginate(15)
            ->withQueryString();

        $departments = Department::orderBy('name')->get();

        return view('classrooms.index', compact('classrooms', 'departments'));
    }

    public function create()
    {
        $departments = Department::orderBy('name')->get();
        $managers = User::orderBy('name')->get();

        return view('classrooms.create', compact('departments', 'managers'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $classroom = Classroom::create($data);

        AuditLogger::log('created', $classroom, $data);

        return redirect()->route('classrooms.index')->with('success', '教室已新增。');
    }

    public function edit(Classroom $classroom)
    {
        $departments = Department::orderBy('name')->get();
        $managers = User::orderBy('name')->get();

        return view('classrooms.edit', compact('classroom', 'departments', 'managers'));
    }

    public function update(Request $request, Classroom $classroom)
    {
        $data = $this->validated($request, $classroom->id);

        $classroom->update($data);

        AuditLogger::log('updated', $classroom, $data);

        return redirect()->route('classrooms.index')->with('success', '教室已更新。');
    }

    public function toggle(Classroom $classroom)
    {
        $classroom->update(['is_active' => !$classroom->is_active]);

        AuditLogger::log('status_changed', $classroom, ['is_active' => $classroom->is_active]);

        return redirect()->route('classrooms.index')
            ->with('success', $classroom->is_active ? '教室已啟用。' : '教室已停用。');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'department_id' => ['nullable', 'exists:departments,id'],
            'campus' => ['required', 'string', 'max:255'],
            'building' => ['required', 'string', 'max:255'],
            'floor' => ['required', 'string', 'max:255'],
            'room_code' => ['required', 'string', 'max:255', 'unique:classrooms,room_code' . ($ignoreId ? ",{$ignoreId}" : '')],
            'room_name' => ['required', 'string', 'max:255'],
            'room_type' => ['nullable', 'string', 'max:255'],
            // 管理人不能指到已被軟刪除（帳號停用）的使用者，exists 預設不看 deleted_at
            'manager_id' => ['nullable', Rule::exists('users', 'id')->whereNull('deleted_at')],
        ], [
            'department_id.exists' => '所屬部門不存在，請重新選擇。',
            'campus.required' => '請填寫校區。',
            'building.required' => '請填寫大樓。',
            'floor.required' => '請填寫樓層。',
            'room_code.required' => '請填寫教室代碼。',
            'room_code.unique' => '這個教室代碼已經有人使用了，請換一個。',
            'room_name.required' => '請填寫教室名稱。',
            'manager_id.exists' => '所選的管理人帳號不存在或已停用，請重新選擇。',
            'max' => '內容長度超過上限（255 字）。',
        ]);
    }
}
