<?php

namespace App\Http\Requests\Soal;

use App\Enums\TipeSoal;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSoalRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tipe' => ['sometimes', 'string', Rule::in(array_column(TipeSoal::cases(), 'value'))],
            'pertanyaan' => ['sometimes', 'string'],
            'media_url' => ['sometimes', 'nullable', 'string', 'max:512'],
            'poin' => ['sometimes', 'integer', 'min:1'],
            'pembahasan' => ['sometimes', 'nullable', 'string'],
            'jawaban_benar_bool' => ['sometimes', 'nullable', 'boolean'],
            'opsi' => ['sometimes', 'array'],
            'opsi.*.teks' => ['required_with:opsi', 'string'],
            'opsi.*.is_kunci' => ['nullable', 'boolean'],
            'opsi.*.pasangan' => ['nullable', 'string'],
            'opsi.*.nomor_urut' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->has('opsi')) {
                return; // tidak update opsi — skip validasi per tipe
            }

            $tipeRaw = $this->input('tipe');
            if (! $tipeRaw) {
                return;
            }

            $tipe = TipeSoal::from($tipeRaw);
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
    }

    /**
     * @param  Validator  $validator
     * @param  array<int, array{teks?: string, is_kunci?: bool|string, pasangan?: string|null, nomor_urut?: int|null}>  $opsi
     */
    protected function validateBenarSalah($validator, array $opsi): void
    {
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
        if (count($opsi) < 2) {
            $validator->errors()->add('opsi', 'Soal Labeling memerlukan minimal 2 label.');
        }

        $nomorList = [];
        foreach ($opsi as $i => $o) {
            $nomorUrut = $o['nomor_urut'] ?? null;
            if ($nomorUrut !== null) {
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

    /** @return array<string, mixed> */
    public function soalData(): array
    {
        return $this->safe()->except('opsi');
    }

    /** @return array<int, array{teks: string, is_kunci?: bool, pasangan?: string|null, nomor_urut?: int|null}> */
    public function opsiData(): array
    {
        return $this->input('opsi', []);
    }

    public function hasOpsi(): bool
    {
        return $this->has('opsi');
    }
}
