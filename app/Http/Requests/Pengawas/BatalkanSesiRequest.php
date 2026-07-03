<?php

namespace App\Http\Requests\Pengawas;

use Illuminate\Foundation\Http\FormRequest;

class BatalkanSesiRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'alasan' => ['required', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'alasan.required' => 'Alasan pembatalan wajib diisi.',
        ];
    }
}
