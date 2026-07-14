<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\AssetRequest;
use App\Models\Marketing\Asset;
use App\Models\Marketing\Category;
use App\Models\Training\Course;
use App\Models\Training\CourseMaterial;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AssetController extends Controller
{
    public function index()
    {
        $assets = Asset::with('category')
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.marketing.assets.index', compact('assets'));
    }

    public function create()
    {
        return view('admin.marketing.assets.create', [
            'categories' => Category::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(AssetRequest $request)
    {
        $data = $this->payload($request);
        $data['created_by'] = Auth::id();

        if (in_array($data['type'], ['image', 'pdf'], true) && $request->hasFile('file')) {
            $data['file_path'] = $request->file('file')->store('marketing/assets', 'public');
        }

        Asset::create($data);

        return redirect()->route('marketing.assets.index')->with('success', 'Aset ditambahkan.');
    }

    public function edit(string $id)
    {
        $asset = Asset::findOrFail($id);

        return view('admin.marketing.assets.edit', [
            'asset' => $asset,
            'categories' => Category::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function update(AssetRequest $request, string $id)
    {
        $asset = Asset::findOrFail($id);
        $data = $this->payload($request);
        $data['updated_by'] = Auth::id();

        if (in_array($data['type'], ['image', 'pdf'], true) && $request->hasFile('file')) {
            if ($asset->file_path) {
                Storage::disk('public')->delete($asset->file_path);
            }
            $data['file_path'] = $request->file('file')->store('marketing/assets', 'public');
        }

        // If the type changed away from file-based, drop the stored file.
        if (! in_array($data['type'], ['image', 'pdf'], true) && $asset->file_path) {
            Storage::disk('public')->delete($asset->file_path);
            $data['file_path'] = null;
        }

        $asset->update($data);

        return redirect()->route('marketing.assets.index')->with('success', 'Aset diperbarui.');
    }

    public function destroy(string $id)
    {
        $asset = Asset::findOrFail($id);

        $usedByMaterials = CourseMaterial::where('marketing_asset_id', $asset->id)->count();
        $usedByThumbnails = Course::where('thumbnail_asset_id', $asset->id)->count();
        if ($usedByMaterials + $usedByThumbnails > 0) {
            return redirect()->route('marketing.assets.index')
                ->with('error', "Aset dipakai oleh {$usedByMaterials} materi & {$usedByThumbnails} thumbnail course, tidak bisa dihapus.");
        }

        if ($asset->file_path) {
            Storage::disk('public')->delete($asset->file_path);
        }
        $asset->deleted_by = Auth::id();
        $asset->save();
        $asset->delete();

        return redirect()->route('marketing.assets.index')->with('success', 'Aset dihapus.');
    }

    /** Build the shared attribute array, nulling fields that don't belong to the chosen type. */
    private function payload(AssetRequest $request): array
    {
        $type = $request->input('type');

        return [
            'title' => $request->string('title'),
            'category_id' => $request->input('category_id'),
            'description' => $request->input('description'),
            'type' => $type,
            'link_url' => $type === 'video' ? $request->input('link_url') : null,
            'body_text' => $type === 'text' ? $request->input('body_text') : null,
            'usable_in_marketing' => $request->boolean('usable_in_marketing'),
            'usable_in_training' => $type === 'text' ? false : $request->boolean('usable_in_training'),
            'can_be_thumbnail' => $type === 'image' ? $request->boolean('can_be_thumbnail') : false,
            'status' => $request->input('status'),
            'sort_order' => (int) $request->input('sort_order', 0),
        ];
    }
}
