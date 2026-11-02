<?php

namespace App\Http\Requests\Diagnosis;

use Illuminate\Http\UploadedFile;

class StoreEvidenceRequest extends DiagnosisRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('diagnosis'));
    }

    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:5'],
            'files.*' => ['bail', 'required', 'file', 'image', 'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp', 'extensions:jpg,jpeg,png,webp', 'max:10240',
                function (string $attribute, mixed $file, \Closure $fail): void {
                    if (! $file instanceof UploadedFile || @getimagesize($file->getPathname()) === false) {
                        $fail('El archivo debe ser una imagen JPG, PNG o WEBP válida.');
                    }
                }],
            'uploaded_by' => ['missing'], 'disk' => ['missing'], 'path' => ['missing'], 'defect_id' => ['missing'],
        ];
    }

    public function messages(): array
    {
        return [
            'files.max' => 'Puedes cargar como máximo 5 imágenes por solicitud.',
            'files.*.max' => 'Cada imagen debe pesar como máximo 10 MB.',
            'files.*.image' => 'Solo se permiten imágenes JPG, PNG o WEBP.',
            'files.*.mimes' => 'Solo se permiten imágenes JPG, PNG o WEBP.',
            'files.*.mimetypes' => 'Solo se permiten imágenes JPG, PNG o WEBP.',
            'files.*.extensions' => 'Usa una extensión JPG, PNG o WEBP.',
        ];
    }
}
