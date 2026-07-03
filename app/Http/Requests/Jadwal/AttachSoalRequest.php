<?php

namespace App\Http\Requests\Jadwal;

use Illuminate\Foundation\Http\FormRequest;

class AttachSoalRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'soal_ids' => ['required', 'array', 'min:1'],
            'soal_ids.*' => ['required', 'integer', 'exists:soal,id'],
        ];
    }

    /** @return int[] */
    public function soalIds(): array
    {
        return $this->input('soal_ids', []);
    }
}
