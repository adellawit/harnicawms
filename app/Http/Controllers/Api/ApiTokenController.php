<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ApiTokenController extends Controller
{
    public function generateToken(Request $request)
    {
        return response()->json(['message' => 'Generate token endpoint']);
    }
}
