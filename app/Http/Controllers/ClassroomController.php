<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;

class ClassroomController extends Controller
{
    public function index()
    {
        $classrooms = Classroom::with(['department', 'manager'])->orderBy('room_code')->paginate(15);

        return view('classrooms.index', compact('classrooms'));
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

        Classroom::create($data);

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

        return redirect()->route('classrooms.index')->with('success', '教室已更新。');
    }

    public function toggle(Classroom $classroom)
    {
        $classroom->update(['is_active' => !$classroom->is_active]);

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
