<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Dilempar saat jadwal ujian tidak dalam status aktif yang dipersyaratkan.
 */
class JadwalTidakAktifException extends Exception
{
    public function __construct(string $message = 'Jadwal ujian tidak aktif.', int $code = 422)
    {
        parent::__construct($message, $code);
    }
}
