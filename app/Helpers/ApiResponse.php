<?php

declare(strict_types=1);

namespace App\Helpers;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Response builder terpadu — semua endpoint kembali {success, data, meta}.
 * Konvensi tunggal: jangan double-unwrap di FE.
 */
final class ApiResponse
{
    /**
     * Response sukses (200).
     *
     * @param  mixed  $data
     */
    public static function success($data = null, ?string $message = null, int $code = 200): JsonResponse
    {
        return self::build(true, $data, self::meta($message), $code);
    }

    /**
     * Response created (201).
     *
     * @param  mixed  $data
     */
    public static function created($data = null, ?string $message = null): JsonResponse
    {
        return self::success($data, $message, 201);
    }

    /**
     * Response sukses dengan pagination.
     *
     * @param  LengthAwarePaginator<array-key, mixed>  $paginator
     */
    public static function successPaginated(LengthAwarePaginator $paginator, ?string $message = null): JsonResponse
    {
        $pagination = [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];

        return self::build(
            true,
            $paginator->items(),
            self::meta($message, ['pagination' => $pagination]),
            200
        );
    }

    /**
     * Response error.
     *
     * @param  mixed|null  $errors  Additional error details (validasi dll)
     */
    public static function error(string $message, int $code = 400, $errors = null): JsonResponse
    {
        return self::build(false, null, self::meta($message, $errors ? ['errors' => $errors] : []), $code);
    }

    // ─── internal ──────────────────────────────────────────

    /**
     * @param  mixed  $data
     * @param  array<string, mixed>  $meta
     */
    private static function build(bool $success, $data, array $meta, int $code): JsonResponse
    {
        return response()->json(
            [
                'success' => $success,
                'data' => $data,
                'meta' => $meta,
            ],
            $code
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private static function meta(?string $message, array $extra = []): array
    {
        $meta = $extra;

        if ($message !== null) {
            $meta['message'] = $message;
        }

        return $meta;
    }
}
