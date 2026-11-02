<?php

namespace App\Models;

use Database\Factories\DefectEvidenceFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Hidden(['disk', 'path'])]
class DefectEvidence extends Model
{
    /** @use HasFactory<DefectEvidenceFactory> */
    use HasFactory;

    protected $table = 'defect_evidences';

    public function defect(): BelongsTo
    {
        return $this->belongsTo(Defect::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
