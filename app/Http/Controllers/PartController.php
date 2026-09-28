<?php

namespace App\Http\Controllers;

use App\Models\Part;
use Illuminate\Http\Request;

class PartController extends Controller
{

    public function index(Request $request)
    {
        $query = Part::query();

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $parts = $query->get();

        return view('parts.index', compact('parts'));
    }

    public function create()
    {
        return view('parts.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required', //必填
            'specification' => 'nullable', //可空
            'unit_price' => 'required|integer|min:0', //必填.整數.大於0
            'current_stock' => 'required|integer|min:0',
            'safety_stock' => 'required|integer|min:0',
         ]);

        Part::create($validated);

        return redirect('/parts');
    }

    public function edit(Part $part)
    {
        return view('parts.edit', compact('part'));
    }

    public function update(Request $request, Part $part)
    {
        $validated = $request->validate([
            'name' => 'required',
            'specification' => 'nullable',
            'unit_price' => 'required|integer|min:0',
            'current_stock' => 'required|integer|min:0',
            'safety_stock' => 'required|integer|min:0',
        ]);

        $part->update($validated);

        return redirect('/parts');
    }   

    public function destroy(Part $part)
    {
        $part->delete();

        return redirect('/parts');
    }
}