<?php

namespace App\Enums;

enum StatusJadwal: string
{
    case Draft = 'draft';
    case Terbuka = 'terbuka';
    case Berlangsung = 'berlangsung';
    case Selesai = 'selesai';
    case Dibatalkan = 'dibatalkan';
}
