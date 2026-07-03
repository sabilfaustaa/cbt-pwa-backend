<?php

namespace App\Http\Requests\Soal;

use App\Enums\TipeSoal;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSoalRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tipe' => ['required', 'string', Rule::in(array_column(TipeSoal::cases(), 'value'))],
            'pertanyaan' => ['required', 'string'],
            'media_url' => ['nullable', 'string', 'max:512'],
            'poin' => ['required', 'integer', 'min:1'],
            'pembahasan' => ['nullable', 'string'],
            'jawaban_benar_bool' => ['nullable', 'boolean'],
            'opsi' => ['nullable', 'array'],
            'opsi.*.teks' => ['required_with:opsi', 'string'],
            'opsi.*.is_kunci' => ['nullable', 'boolean'],
            'opsi.*.pasangan' => ['nullable', 'string'],
            'opsi.*.nomor_urut' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'opsi.*.teks.required_with' => 'Setiap opsi wajib memiliki teks.',
            'opsi.*.nomor_urut.min' => 'Nomor urut harus ≥ 1.',
        ];
    }

    /**
     * Register after-validation hook untuk validasi per tipe.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->tipe) {
                return;
            }

            $tipe = TipeSoal::from($this->tipe);
            $opsi = $this->input('opsi', []);

            match ($tipe) {
                TipeSoal::Pg => $this->validatePg($validator, $opsi),
                TipeSoal::BenarSalah => $this->validateBenarSalah($validator, $opsi),
                TipeSoal::Labeling => $this->validateLabeling($validator, $opsi),
                TipeSoal::Menjodohkan => $this->validateMenjodohkan($validator, $opsi),
            };
        });
    }

    /**
     * @param  Validator  $validator
     * @param  array<int, array{teks?: string, is_kunci?: bool|string, pasangan?: string|null, nomor_urut?: int|null}>  $opsi
     */
    protected function validatePg($validator, array $opsi): void
    {
        if (count($opsi) < 2) {
            $validator->errors()->add('opsi', 'Soal PG memerlukan minimal 2 opsi.');
        }

        $kunciCount = count(array_filter($opsi, fn (array $o): bool => ! empty($o['is_kunci'])));
        if ($kunciCount !== 1) {
            $validator->errors()->add('opsi', 'Soal PG harus memiliki tepat 1 kunci jawaban.');
        }

        if ($this->input('jawaban_benar_bool') !== null) {
            $validator->errors()->add('jawaban_benar_bool', 'jawaban_benar_bool tidak boleh diisi untuk soal PG.');
        }
    }

    /**
     * @param  Validator  $validator
     * @param  array<int, array{teks?: string, is_kunci?: bool|string, pasangan?: string|null, nomor_urut?: int|null}>  $opsi
     */
    protected function validateBenarSalah($validator, array $opsi): void
    {
        if ($this->input('jawaban_benar_bool') === null) {
            $validator->errors()->add('jawaban_benar_bool', 'jawaban_benar_bool wajib diisi untuk soal Benar-Salah.');
        }

        if (! empty($opsi)) {
            $validator->errors()->add('opsi', 'Soal Benar-Salah tidak boleh memiliki opsi.');
        }
    }

    /**
     * @param  Validator  $validator
     * @param  array<int, array{teks?: string, is_kunci?: bool|string, pasangan?: string|null, nomor_urut?: int|null}>  $opsi
     */
    protected function validateLabeling($validator, array $opsi): void
    {
        if (empty($this->input('media_url'))) {
            $validator->errors()->add('media_url', 'media_url wajib diisi untuk soal Labeling.');
        }

        if (count($opsi) < 2) {
            $validator->errors()->add('opsi', 'Soal Labeling memerlukan minimal 2 label.');
        }

        $nomorList = [];
        foreach ($opsi as $i => $o) {
            $nomorUrut = $o['nomor_urut'] ?? null;
            if ($nomorUrut === null) {
                $validator->errors()->add("opsi.{$i}.nomor_urut", 'Setiap label wajib memiliki nomor_urut.');
            } else {
                $nomorList[] = $nomorUrut;
            }
        }

        if (count($nomorList) > 0 && count(array_unique($nomorList)) !== count($nomorList)) {
            $validator->errors()->add('opsi', 'Nomor urut pada soal Labeling tidak boleh duplikat.');
        }
    }

    /**
     * @param  Validator  $validator
     * @param  array<int, array{teks?: string, is_kunci?: bool|string, pasangan?: string|null, nomor_urut?: int|null}>  $opsi
     */
    protected function validateMenjodohkan($validator, array $opsi): void
    {
        if (count($opsi) < 2) {
            $validator->errors()->add('opsi', 'Soal Menjodohkan memerlukan minimal 2 pasangan.');
        }

        foreach ($opsi as $i => $o) {
            if (empty($o['teks'] ?? '') || empty($o['pasangan'] ?? '')) {
                $validator->errors()->add('opsi', 'Opsi ke-'.($i + 1).' harus memiliki teks dan pasangan.');
            }
        }
    }

    // ─── Data helpers untuk Controller ─────────────────────────

    /** @return array<string, mixed> */
    public function soalData(): array
    {
        $data = $this->safe()->except('opsi');

        if ($data['tipe'] !== TipeSoal::BenarSalah->value) {
            unset($data['jawaban_benar_bool']);
        }

        $data['created_by'] = $this->user()?->id;

        return $data;
    }

    /** @return array<int, array{teks: string, is_kunci?: bool, pasangan?: string|null, nomor_urut?: int|null}> */
    public function opsiData(): array
    {
        return $this->input('opsi', []);
    }
}
