<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class ImportDataController extends Controller
{
    public function index()
    {
        return view('admin.import-data.index');
    }
}
