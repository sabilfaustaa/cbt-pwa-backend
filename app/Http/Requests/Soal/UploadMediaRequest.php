<?php

namespace App\Http\Requests\Soal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class UploadMediaRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                File::types(['jpg', 'jpeg', 'png', 'webp'])
                    ->max(5 * 1024), // 5 MB
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.required' => 'File wajib diunggah.',
            'file.max' => 'Ukuran file maksimal 5 MB.',
        ];
    }
}
