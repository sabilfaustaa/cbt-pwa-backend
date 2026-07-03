<?php

namespace App\Http\Requests\Sesi;

use App\Enums\TipeSoal;
use App\Models\JadwalSoal;
use App\Models\SesiUjian;
use App\Models\Soal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Symfony\Component\HttpKernel\Exception\HttpException;

class JawabanRequest extends FormRequest
{
    private ?SesiUjian $sesi = null;

    private ?Soal $soal = null;

    private ?TipeSoal $tipeType = null;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'soal_id' => ['required', 'integer', 'exists:soal,id'],
            'opsi_id' => ['nullable', 'integer', 'exists:opsi_soal,id'],
            'jawaban_bool' => ['nullable', 'boolean'],
            'nomor_jawaban' => ['nullable', 'integer', 'min:1'],
            'pasangan_opsi_id' => ['nullable', 'integer', 'exists:opsi_soal,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'soal_id.required' => 'ID soal wajib diisi.',
            'soal_id.integer' => 'ID soal harus berupa angka.',
            'soal_id.exists' => 'Soal tidak ditemukan.',
            'jawaban_bool.boolean' => 'jawaban_bool harus berupa true/false.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $sesiId = $this->route('id');
            $this->sesi = SesiUjian::with('jadwalUjian')->findOrFail((int) $sesiId);

            if ($this->sesi->user_id !== $this->user()->id) {
                throw new HttpException(403, 'Akses ditolak.');
            }

            if ($this->sesi->status->value !== 'sedang_berlangsung') {
                throw new HttpException(409, 'Sesi tidak sedang berlangsung.');
            }

            if ($this->sesi->waktu_batas && now()->gt($this->sesi->waktu_batas)) {
                throw new HttpException(409, 'Waktu ujian sudah habis.');
            }

            $soalId = (int) $this->input('soal_id');

            $jadwalSoal = JadwalSoal::where('jadwal_ujian_id', $this->sesi->jadwal_ujian_id)
                ->where('soal_id', $soalId)
                ->first();

            if (! $jadwalSoal) {
                $validator->errors()->add('soal_id', 'Soal ini bukan bagian dari jadwal ujian.');

                return;
            }

            $this->soal = Soal::findOrFail($soalId);
            $this->tipeType = $this->soal->tipe;

            switch ($this->tipeType->value) {
                case 'pg':
                    if (! $this->input('opsi_id')) {
                        $validator->errors()->add('opsi_id', 'opsi_id wajib diisi untuk soal PG.');
                    }
                    if ($this->input('jawaban_bool') !== null) {
                        $validator->errors()->add('jawaban_bool', 'jawaban_bool tidak digunakan untuk soal PG.');
                    }
                    break;

                case 'benar_salah':
                    if ($this->input('jawaban_bool') === null) {
                        $validator->errors()->add('jawaban_bool', 'jawaban_bool wajib diisi untuk soal Benar-Salah.');
                    }
                    if ($this->input('opsi_id')) {
                        $validator->errors()->add('opsi_id', 'opsi_id tidak digunakan untuk soal Benar-Salah.');
                    }
                    break;

                case 'labeling':
                    if (! $this->input('opsi_id')) {
                        $validator->errors()->add('opsi_id', 'opsi_id wajib diisi untuk soal Labeling.');
                    }
                    if ($this->input('nomor_jawaban') === null) {
                        $validator->errors()->add('nomor_jawaban', 'nomor_jawaban wajib diisi untuk soal Labeling.');
                    }
                    if ($this->input('opsi_id')) {
                        $opsiMilik = $this->soal->opsi()->where('id', $this->input('opsi_id'))->exists();
                        if (! $opsiMilik) {
                            $validator->errors()->add('opsi_id', 'Opsi ini bukan bagian dari soal.');
                        }
                    }
                    break;

                case 'menjodohkan':
                    if (! $this->input('opsi_id')) {
                        $validator->errors()->add('opsi_id', 'opsi_id wajib diisi untuk soal Menjodohkan.');
                    }
                    if (! $this->input('pasangan_opsi_id')) {
                        $validator->errors()->add('pasangan_opsi_id', 'pasangan_opsi_id wajib diisi untuk soal Menjodohkan.');
                    }
                    if ($this->input('opsi_id')) {
                        $opsiMilik = $this->soal->opsi()->where('id', $this->input('opsi_id'))->exists();
                        if (! $opsiMilik) {
                            $validator->errors()->add('opsi_id', 'Opsi ini bukan bagian dari soal.');
                        }
                    }
                    if ($this->input('pasangan_opsi_id')) {
                        $pasanganMilik = $this->soal->opsi()->where('id', $this->input('pasangan_opsi_id'))->exists();
                        if (! $pasanganMilik) {
                            $validator->errors()->add('pasangan_opsi_id', 'Opsi pasangan ini bukan bagian dari soal.');
                        }
                    }
                    break;
            }
        });
    }

    public function getSesi(): SesiUjian
    {
        return $this->sesi;
    }

    public function getSoal(): Soal
    {
        return $this->soal;
    }

    public function getTipe(): TipeSoal
    {
        return $this->tipeType;
    }
}
