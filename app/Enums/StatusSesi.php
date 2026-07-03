<?php

namespace App\Enums;

enum StatusSesi: string
{
    case BelumMulai = 'belum_mulai';
    case SedangBerlangsung = 'sedang_berlangsung';
    case Selesai = 'selesai';
    case Dibatalkan = 'dibatalkan';
    case Kadaluarsa = 'kadaluarsa';
}
