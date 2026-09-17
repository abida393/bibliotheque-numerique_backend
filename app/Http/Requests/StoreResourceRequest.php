<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // déjà filtré par le middleware auth:sanctum sur la route
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'authors' => 'nullable|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'language' => 'nullable|string|max:50',
            'publication_date' => 'nullable|date',
            'description' => 'nullable|string',
            'file' => 'required|file|mimes:pdf,doc,docx,ppt,pptx|max:102400', // 100 Mo en Ko
        ];
    }

    public function messages(): array
    {
        return [
            'file.max' => 'Le fichier ne doit pas dépasser 100 Mo.',
            'file.mimes' => 'Seuls les fichiers PDF, Word et PowerPoint sont acceptés.',
        ];
    }
}
