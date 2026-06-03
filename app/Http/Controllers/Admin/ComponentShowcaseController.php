<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ComponentShowcaseController extends Controller
{
    public function indexView()
    {
        return view('admin.component-showcase.index');
    }
}
