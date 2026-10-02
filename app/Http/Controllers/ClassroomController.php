<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Department;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

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
        // 部門下拉選單從部門主檔讀，只列啟用中的部門。
        $departments = Department::forSelect()->get();
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
        // 這間教室目前所屬的部門即使被停用，也要保留在選項裡。
        $departments = Department::forSelect($classroom->department_id)->get();
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
            'manager_id' => ['nullable', 'exists:users,id'],
        ]);
    }
}
