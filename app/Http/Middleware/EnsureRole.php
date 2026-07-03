<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware: pastikan user punya role yang diizinkan.
 * Usage: `middleware('role:admin,pengawas')` atau `middleware('role:admin')`.
 */
class EnsureRole
{
    /** @param  list<string>  ...$roles */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Tidak terautentikasi.');
        }

        $userRole = $user->namaRole->value ?? null;

        if (! $userRole || ! in_array($userRole, $roles, true)) {
            abort(403, 'Anda tidak punya akses untuk aksi ini.');
        }

        return $next($request);
    }
}
