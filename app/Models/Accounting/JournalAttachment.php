<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class JournalAttachment extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'pgsql';

    protected $table = 'accounting.journal_attachments';

    protected $fillable = [
        'journal_id',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'created_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'journal_id');
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }
}
