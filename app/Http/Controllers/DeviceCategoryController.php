<?php

namespace App\Http\Controllers;

use App\Models\DeviceCategory;
use Illuminate\Http\Request;

class DeviceCategoryController extends Controller
{
    public function index()
    {
        $categories = DeviceCategory::withCount('devices')->orderBy('name')->paginate(15);

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
        ]);

        DeviceCategory::create($data);

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
        ]);

        $deviceCategory->update($data);

        return redirect()->route('device-categories.index')->with('success', '設備類別已更新。');
    }

    public function destroy(DeviceCategory $deviceCategory)
    {
        if ($deviceCategory->devices()->exists()) {
            return redirect()->route('device-categories.index')->with('error', '此類別仍有設備使用中，無法刪除。');
        }

        $deviceCategory->delete();

        return redirect()->route('device-categories.index')->with('success', '設備類別已刪除。');
    }
}
