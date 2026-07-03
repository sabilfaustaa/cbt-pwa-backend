<?php

namespace App\Services;

class ShuffleService
{
    public function makeSeed(int $userId, int $sesiId): int
    {
        return crc32("{$userId}:{$sesiId}");
    }

    /**
     * @param  array<mixed>  $items
     * @return array<mixed>
     */
    public function shuffle(array $items, int $seed): array
    {
        if (count($items) <= 1) {
            return $items;
        }

        mt_srand($seed);

        $count = count($items);
        for ($i = $count - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }

        mt_srand((int) (microtime(true) * 1_000_000) ^ random_int(0, PHP_INT_MAX));

        return $items;
    }
}
