<?php

namespace App\Models\Configuration;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AppThemeSetting extends Model
{
    use HasUuids;

    public const MODE_LOGO_EXTRACT = 'logo_extract';
    public const MODE_CUSTOM = 'custom';

    public const FONT_SOURCE_PRESET = 'preset';
    public const FONT_SOURCE_UPLOAD = 'upload';

    protected $connection = 'pgsql';

    protected $table = 'configuration.app_theme_settings';

    protected $fillable = [
        'primary_color',
        'secondary_color',
        'navbar_color',
        'sidebar_color',
        'background_color',
        'tokens_light',
        'tokens_dark',
        'preview_mode',
        'font_source',
        'font_preset',
        'font_path',
        'logo_path',
        'favicon_path',
        'color_mode',
        'glass_enabled',
        'motion_enabled',
        'updated_by',
    ];

    protected $casts = [
        'tokens_light' => 'array',
        'tokens_dark' => 'array',
        'glass_enabled' => 'boolean',
        'motion_enabled' => 'boolean',
    ];
}
