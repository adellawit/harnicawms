<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Theme\AppThemeService;
use Illuminate\View\View;

class DesignSystemController extends Controller
{
    public function __construct(
        protected AppThemeService $theme,
    ) {}

    public function indexView(): View
    {
        return view('admin.design-system.index', [
            'themeView' => $this->theme->viewData(),
        ]);
    }
}
