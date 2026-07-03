<?php

namespace App\Policies;

use App\Models\SesiUjian;
use App\Models\User;

class SesiUjianPolicy
{
    public function view(User $user, SesiUjian $sesi): bool
    {
        return $user->id === $sesi->user_id;
    }
}
