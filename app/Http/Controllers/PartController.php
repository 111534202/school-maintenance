<?php

namespace App\Http\Controllers;

use App\Models\Part;

class PartController extends Controller
{
    public function index()
    {
        $parts = Part::all();

        return view('parts.index', compact('parts'));
    }
}