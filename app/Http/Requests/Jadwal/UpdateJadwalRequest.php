<?php

namespace App\Http\Requests\Jadwal;

use Illuminate\Foundation\Http\FormRequest;

class UpdateJadwalRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $jadwalId = $this->route('id');

        return [
            'kode_jadwal' => ['sometimes', 'string', 'max:64', 'regex:/^[A-Z0-9-]+$/', "unique:jadwal_ujian,kode_jadwal,{$jadwalId}"],
            'nama_ujian' => ['sometimes', 'string', 'max:255'],
            'deskripsi' => ['sometimes', 'nullable', 'string'],
            'waktu_mulai' => ['sometimes', 'date'],
            'waktu_selesai' => ['sometimes', 'date'],
            'durasi_menit' => ['sometimes', 'integer', 'min:1'],
            'acak_soal' => ['sometimes', 'boolean'],
            'acak_opsi' => ['sometimes', 'boolean'],
            'tampilkan_hasil' => ['sometimes', 'boolean'],
            'passing_grade' => ['sometimes', 'integer', 'min:0', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'kode_jadwal.regex' => 'Kode jadwal hanya boleh berisi huruf kapital, angka, dan strip.',
            'durasi_menit.min' => 'Durasi minimal 1 menit.',
            'passing_grade.min' => 'Passing grade minimal 0.',
            'passing_grade.max' => 'Passing grade maksimal 100.',
        ];
    }

    /** @return array<string, mixed> */
    public function jadwalData(): array
    {
        return $this->safe()->only([
            'kode_jadwal', 'nama_ujian', 'deskripsi',
            'waktu_mulai', 'waktu_selesai', 'durasi_menit',
            'acak_soal', 'acak_opsi', 'tampilkan_hasil', 'passing_grade',
        ]);
    }
}
