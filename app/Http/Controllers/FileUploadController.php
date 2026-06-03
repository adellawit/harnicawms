<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileUploadController extends Controller
{
    public function upload(Request $request)
    {
        if ($request->hasFile('file')) {
            $file = $request->file('file');

            // Generate unique file name using inputId and datetime
            $inputId = $request->input('inputId', 'unknown');
            $timestamp = now()->format('YmdHis');
            $extension = $file->getClientOriginalExtension();
            $filename = "{$inputId}_{$timestamp}.{$extension}";

            // Save to storage/app/public/uploads
            $path = $file->storeAs('uploads', $filename, 'public');

            // Use Storage::url() to get the correct URL
            $url = Storage::disk('public')->url($path);

            return response()->json([
                'success' => true,
                'file_name' => $filename,
                'file_path' => $url
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => 'No file uploaded'
        ]);
    }
}
