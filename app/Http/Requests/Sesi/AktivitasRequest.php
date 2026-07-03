<?php

namespace App\Http\Requests\Sesi;

use Illuminate\Foundation\Http\FormRequest;

class AktivitasRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'jenis' => ['required', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'jenis.required' => 'Jenis aktivitas wajib diisi.',
            'jenis.max' => 'Jenis aktivitas maksimal 50 karakter.',
        ];
    }
}
