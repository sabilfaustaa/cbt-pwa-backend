<?php

namespace App\Http\Requests\Jadwal;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreJadwalRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'kode_jadwal' => ['required', 'string', 'max:64', 'regex:/^[A-Z0-9-]+$/', 'unique:jadwal_ujian,kode_jadwal'],
            'nama_ujian' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'waktu_mulai' => ['required', 'date'],
            'waktu_selesai' => ['required', 'date', 'after:waktu_mulai'],
            'durasi_menit' => ['required', 'integer', 'min:1'],
            'acak_soal' => ['boolean'],
            'acak_opsi' => ['boolean'],
            'tampilkan_hasil' => ['boolean'],
            'passing_grade' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'kode_jadwal.regex' => 'Kode jadwal hanya boleh berisi huruf kapital, angka, dan strip.',
            'waktu_selesai.after' => 'Waktu selesai harus setelah waktu mulai.',
            'durasi_menit.min' => 'Durasi minimal 1 menit.',
            'passing_grade.min' => 'Passing grade minimal 0.',
            'passing_grade.max' => 'Passing grade maksimal 100.',
        ];
    }

    /**
     * After base validation: durasi_menit ≤ selisih waktu_mulai → waktu_selesai.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->hasAny(['waktu_mulai', 'waktu_selesai', 'durasi_menit'])) {
                return;
            }

            $mulai = strtotime((string) $this->input('waktu_mulai'));
            $selesai = strtotime((string) $this->input('waktu_selesai'));
            $durasi = (int) $this->input('durasi_menit');

            if ($mulai === false || $selesai === false) {
                return;
            }

            $selisihMenit = (int) (($selesai - $mulai) / 60);

            if ($durasi > $selisihMenit) {
                $validator->errors()->add(
                    'durasi_menit',
                    "Durasi ujian ({$durasi} menit) tidak boleh melebihi selisih waktu mulai-selesai ({$selisihMenit} menit)."
                );
            }
        });
    }

    /** @return array<string, mixed> */
    public function jadwalData(): array
    {
        return array_merge($this->safe()->only([
            'kode_jadwal', 'nama_ujian', 'deskripsi',
            'waktu_mulai', 'waktu_selesai', 'durasi_menit',
            'passing_grade',
        ]), [
            'acak_soal' => $this->boolean('acak_soal', true),
            'acak_opsi' => $this->boolean('acak_opsi', true),
            'tampilkan_hasil' => $this->boolean('tampilkan_hasil', true),
            'status' => 'draft',
            'created_by' => $this->user()?->id,
        ]);
    }
}
