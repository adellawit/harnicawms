<?php

namespace App\Models\Partner;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerApplicationDocument extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $table = 'partner.partner_application_documents';

    protected $fillable = [
        'application_id',
        'document_type',
        'file_path',
        'original_name',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(PartnerApplication::class, 'application_id', 'id');
    }
}
