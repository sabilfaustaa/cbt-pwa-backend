<?php

namespace App\Http\Resources;

use App\Enums\TipeSoal;
use App\Models\OpsiSoal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property TipeSoal $tipe
 * @property string $pertanyaan
 * @property string|null $media_url
 * @property int $poin
 */
class SoalPublicResource extends JsonResource
{
    public int $nomorUrut = 0;

    /** @var Collection<int, OpsiSoal>|null */
    public ?Collection $shuffledOpsi = null;

    /** @var Collection<int, object>|null */
    public ?Collection $shuffledPasangan = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $tipe = $this->tipe->value;

        $data = [
            'id' => $this->id,
            'tipe' => $tipe,
            'pertanyaan' => $this->pertanyaan,
            'media_url' => $this->media_url,
            'poin' => (int) $this->poin,
            'nomor_urut' => $this->nomorUrut,
        ];

        if (in_array($tipe, [TipeSoal::Pg->value, TipeSoal::Labeling->value], true)) {
            /** @var OpsiSoal[] $opsiArr */
            $opsiArr = $this->shuffledOpsi?->all() ?? $this->resource->opsi?->all();
            if ($opsiArr) {
                $data['opsi'] = array_map(fn ($o) => [
                    'id' => $o->id,
                    'teks' => $o->teks,
                ], $opsiArr);
            }
        }

        if ($tipe === TipeSoal::Menjodohkan->value) {
            /** @var OpsiSoal[] $opsiArr */
            $opsiArr = $this->shuffledOpsi?->all() ?? $this->resource->opsi?->all();

            if ($opsiArr) {
                if ($this->shuffledPasangan) {
                    $pasanganValues = $this->shuffledPasangan->pluck('teks')->values()->all();
                    $data['opsi'] = [];
                    foreach ($opsiArr as $index => $o) {
                        $data['opsi'][] = [
                            'id' => $o->id,
                            'teks' => $o->teks,
                            'pasangan' => $pasanganValues[$index] ?? $o->pasangan,
                        ];
                    }
                } else {
                    $data['opsi'] = array_map(fn ($o): array => [
                        'id' => $o->id,
                        'teks' => $o->teks,
                        'pasangan' => $o->pasangan,
                    ], $opsiArr);
                }
            }
        }

        return $data;
    }
}
