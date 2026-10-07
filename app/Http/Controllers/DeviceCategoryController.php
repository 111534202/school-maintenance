<?php

namespace App\Http\Controllers;

use App\Models\DeviceCategory;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class DeviceCategoryController extends Controller
{
    public function index(Request $request)
    {
        $categories = DeviceCategory::withCount('devices')
            ->when($request->filled('keyword'), fn ($query) => $query->where('name', 'like', '%' . $request->string('keyword') . '%'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('device-categories.index', compact('categories'));
    }

    public function create()
    {
        return view('device-categories.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:device_categories,name'],
        ], [
            'name.required' => '請填寫類別名稱。',
            'name.unique' => '這個類別名稱已經存在了，請換一個。',
            'name.max' => '類別名稱長度超過上限（255 字）。',
        ]);

        $category = DeviceCategory::create($data);

        AuditLogger::log('created', $category, $data);

        return redirect()->route('device-categories.index')->with('success', '設備類別已新增。');
    }

    public function edit(DeviceCategory $deviceCategory)
    {
        return view('device-categories.edit', ['category' => $deviceCategory]);
    }

    public function update(Request $request, DeviceCategory $deviceCategory)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:device_categories,name,' . $deviceCategory->id],
        ], [
            'name.required' => '請填寫類別名稱。',
            'name.unique' => '這個類別名稱已經存在了，請換一個。',
            'name.max' => '類別名稱長度超過上限（255 字）。',
        ]);

        $deviceCategory->update($data);

        AuditLogger::log('updated', $deviceCategory, $data);

        return redirect()->route('device-categories.index')->with('success', '設備類別已更新。');
    }

    public function destroy(DeviceCategory $deviceCategory)
    {
        if ($deviceCategory->devices()->exists()) {
            return redirect()->route('device-categories.index')->with('error', '此類別仍有設備使用中，無法刪除。');
        }

        $deviceCategory->delete();

        AuditLogger::log('deleted', $deviceCategory);

        return redirect()->route('device-categories.index')->with('success', '設備類別已刪除。');
    }
}
