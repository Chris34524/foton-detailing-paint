<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DefectEvidenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'original_name' => $this->original_name, 'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'uploaded_by' => $this->uploadedBy === null ? null : ['id' => $this->uploadedBy->id, 'name' => $this->uploadedBy->name],
            'view_url' => route('diagnoses.evidence.show', [
                'diagnosis' => $this->defect->diagnosis_id, 'defect' => $this->defect_id, 'evidence' => $this->id,
            ], false),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
