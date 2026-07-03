<?php

namespace App\Http\Requests\Pengumuman;

use Illuminate\Foundation\Http\FormRequest;

class StorePengumumanRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'judul' => ['required', 'string', 'max:255'],
            'isi' => ['required', 'string'],
            'is_penting' => ['sometimes', 'boolean'],
            'jadwal_id' => ['nullable', 'integer', 'exists:jadwal_ujian,id'],
            'published_at' => ['nullable', 'date'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'judul.required' => 'Judul wajib diisi.',
            'isi.required' => 'Isi pengumuman wajib diisi.',
            'jadwal_id.exists' => 'Jadwal ujian tidak ditemukan.',
            'published_at.date' => 'Format tanggal publikasi tidak valid.',
        ];
    }
}
