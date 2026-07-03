<?php

namespace App\Http\Requests\Sesi;

use Illuminate\Foundation\Http\FormRequest;

class MulaiSesiRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'token_akses' => ['required', 'string', 'size:64'],
            'persetujuan' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'token_akses.required' => 'Token akses wajib diisi.',
            'token_akses.string' => 'Token akses harus berupa teks.',
            'token_akses.size' => 'Token akses tidak valid (panjang harus 64 karakter).',
        ];
    }
}
