<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Device;
use App\Models\DeviceCategory;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function index()
    {
        $devices = Device::with(['category', 'classroom'])->orderBy('device_code')->paginate(15);

        return view('devices.index', compact('devices'));
    }

    public function create()
    {
        $categories = DeviceCategory::orderBy('name')->get();
        $classrooms = Classroom::orderBy('room_code')->get();

        return view('devices.create', compact('categories', 'classrooms'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['is_core'] = $request->boolean('is_core');

        Device::create($data);

        return redirect()->route('devices.index')->with('success', '設備已新增。');
    }

    public function show(Device $device)
    {
        $device->load(['category', 'classroom.department']);

        return view('devices.show', compact('device'));
    }

    public function edit(Device $device)
    {
        $categories = DeviceCategory::orderBy('name')->get();
        $classrooms = Classroom::orderBy('room_code')->get();

        return view('devices.edit', compact('device', 'categories', 'classrooms'));
    }

    public function update(Request $request, Device $device)
    {
        $data = $this->validated($request, $device->id);
        $data['is_core'] = $request->boolean('is_core');

        $device->update($data);

        return redirect()->route('devices.index')->with('success', '設備已更新。');
    }

    public function disable(Device $device)
    {
        $device->update(['status' => 'disabled']);
        $device->delete();

        return redirect()->route('devices.index')->with('success', '設備已停用。');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'device_code' => ['required', 'string', 'max:255', 'unique:devices,device_code' . ($ignoreId ? ",{$ignoreId}" : '')],
            'asset_code' => ['nullable', 'string', 'max:255'],
            'device_category_id' => ['required', 'exists:device_categories,id'],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'warranty_until' => ['nullable', 'date'],
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'status' => ['required', 'in:' . implode(',', Device::STATUSES)],
            'is_core' => ['sometimes', 'boolean'],
        ]);
    }
}
