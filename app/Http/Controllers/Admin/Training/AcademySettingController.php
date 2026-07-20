<?php

namespace App\Http\Controllers\Admin\Training;

use App\Http\Controllers\Controller;
use App\Models\Training\AcademySetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AcademySettingController extends Controller
{
    public function edit(): View
    {
        $setting = AcademySetting::current();

        return view('admin.training.settings.edit', compact('setting'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'show_progress_percentage' => 'required|boolean',
        ]);

        $setting = AcademySetting::current();
        $setting->update([
            'show_progress_percentage' => $validated['show_progress_percentage'],
            'updated_by' => Auth::id(),
        ]);

        return redirect()
            ->route('training.settings.edit')
            ->with('success', 'Pengaturan berhasil disimpan.');
    }
}
