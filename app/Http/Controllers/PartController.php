<?php

namespace App\Http\Controllers;

use App\Models\Part;
use Illuminate\Http\Request;

class PartController extends Controller
{

    public function index(Request $request) //顯示零件列表.搜尋
    {
        $query = Part::query();

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $parts = $query->get();

        return view('parts.index', compact('parts'));
    }

    public function create() //顯示"新增"列表
    {
        return view('parts.create');
    }

    public function store(Request $request) //把新增資料存入資料庫
    {
        $validated = $request->validate([
            'name' => 'required', //必填
            'specification' => 'nullable', //可空
            'unit_price' => 'required|integer|min:0', //必填.整數.大於0
            'current_stock' => 'required|integer|min:0',
            'safety_stock' => 'required|integer|min:0',
         ]);

        Part::create($validated);

        return redirect()->route('parts.index')
            ->with('success', '零件新增成功！');
    }

    public function edit(Part $part) //顯示"修改"列表
    {
        return view('parts.edit', compact('part'));
    }

    public function update(Request $request, Part $part) //更新零件資料
    {
        $validated = $request->validate([
            'name' => 'required',
            'specification' => 'nullable',
            'unit_price' => 'required|integer|min:0',
            'current_stock' => 'required|integer|min:0',
            'safety_stock' => 'required|integer|min:0',
        ]);

        $part->update($validated);

        return redirect()->route('parts.index')
            ->with('success', '零件修改成功！');
    }   

    public function destroy(Part $part) //刪除零件
    {
        $part->delete();

        return redirect()->route('parts.index')
            ->with('success', '零件刪除成功！');
    }
}