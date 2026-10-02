<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Device;
use App\Models\DeviceCategory;
use App\Services\AuditLogger;
use App\Services\DeviceStatusService;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class DeviceController extends Controller
{
    public function index(Request $request)
    {
        $devices = Device::with(['category', 'classroom'])
            ->when($request->filled('keyword'), function ($query) use ($request) {
                $keyword = $request->string('keyword');
                $query->where(function ($q) use ($keyword) {
                    $q->where('device_code', 'like', "%{$keyword}%")
                      ->orWhere('asset_code', 'like', "%{$keyword}%")
                      ->orWhere('brand', 'like', "%{$keyword}%")
                      ->orWhere('model', 'like', "%{$keyword}%");
                });
            })
            ->when($request->filled('classroom_id'), fn ($query) => $query->where('classroom_id', $request->integer('classroom_id')))
            ->when($request->filled('device_category_id'), fn ($query) => $query->where('device_category_id', $request->integer('device_category_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderBy('device_code')
            ->paginate(15)
            ->withQueryString();

        $classrooms = Classroom::orderBy('room_code')->get();
        $categories = DeviceCategory::orderBy('name')->get();

        return view('devices.index', compact('devices', 'classrooms', 'categories'));
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

        $device = Device::create($data);

        AuditLogger::log('created', $device, $data);
        DeviceStatusService::syncClassroom($device->classroom);

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
        $newStatus = $data['status'];
        $newIsCore = $request->boolean('is_core');
        unset($data['status'], $data['is_core']);

        $oldClassroom = $device->classroom;

        $device->update($data);
        AuditLogger::log('updated', $device, $data);

        // 狀態與核心旗標一律透過統一服務寫入，才會觸發 audit log 與教室異常旗標同步
        DeviceStatusService::updateStatus($device, $newStatus);
        DeviceStatusService::setCore($device, $newIsCore);

        if ($oldClassroom && $oldClassroom->id !== $device->classroom_id) {
            DeviceStatusService::syncClassroom($oldClassroom);
        }

        return redirect()->route('devices.index')->with('success', '設備已更新。');
    }

    public function disable(Device $device)
    {
        DeviceStatusService::updateStatus($device, 'disabled', '設備停用');
        $classroom = $device->classroom;
        $device->delete();
        DeviceStatusService::syncClassroom($classroom);

        return redirect()->route('devices.index')->with('success', '設備已停用。');
    }

    /**
     * QR Code 只編碼設備識別碼指向的固定入口網址（devices.entry），
     * 不寫死教室/類別等會變動的資料，掃碼當下即時查詢最新狀態。
     */
    public function qrcode(Device $device)
    {
        $url = route('devices.entry', $device);

        return response(QrCode::format('svg')->size(300)->generate($url))
            ->header('Content-Type', 'image/svg+xml');
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
