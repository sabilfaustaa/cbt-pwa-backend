<?php

namespace App\Http\Requests\Pengawas;

use Illuminate\Foundation\Http\FormRequest;

class TambahWaktuRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tambahan_menit' => ['required', 'integer', 'min:1'],
            'alasan' => ['required', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'tambahan_menit.required' => 'Tambahan menit wajib diisi.',
            'tambahan_menit.integer' => 'Tambahan menit harus berupa angka.',
            'tambahan_menit.min' => 'Tambahan menit minimal 1.',
            'alasan.required' => 'Alasan wajib diisi.',
        ];
    }
}
