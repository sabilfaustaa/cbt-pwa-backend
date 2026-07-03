<?php

namespace App\Http\Requests\Jadwal;

use Illuminate\Foundation\Http\FormRequest;

class ReorderSoalRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.soal_id' => ['required', 'integer', 'exists:soal,id'],
            'items.*.nomor_urut' => ['required', 'integer', 'min:1'],
        ];
    }

    /** @return array<int, array{soal_id: int, nomor_urut: int}> */
    public function items(): array
    {
        return $this->input('items', []);
    }
}
