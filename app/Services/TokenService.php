<?php

namespace App\Services;

class TokenService
{
    /**
     * Generate kriptografis 32-byte random hex string (64 karakter).
     */
    public function generate(): string
    {
        return bin2hex(random_bytes(32));
    }
}
