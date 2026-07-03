<?php

namespace App\Enums;

enum RoleName: string
{
    case Admin = 'admin';
    case Pengawas = 'pengawas';
    case Peserta = 'peserta';
}
