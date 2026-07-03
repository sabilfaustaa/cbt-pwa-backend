<?php

use App\Helpers\ApiResponse;
use Illuminate\Pagination\LengthAwarePaginator;

test('success() returns success:true, data, 200', function () {
    $response = ApiResponse::success(['foo' => 'bar']);
    $body = $response->getData(true);

    expect($response->getStatusCode())->toBe(200);
    expect($body)->toMatchArray([
        'success' => true,
        'data' => ['foo' => 'bar'],
    ]);
    expect($body['meta'])->toBeArray();
});

test('success() with message includes message in meta', function () {
    $response = ApiResponse::success(null, 'Operasi berhasil');
    $body = $response->getData(true);

    expect($response->getStatusCode())->toBe(200);
    expect($body['success'])->toBeTrue();
    expect($body['meta']['message'])->toBe('Operasi berhasil');
});

test('created() returns success:true, data, 201', function () {
    $response = ApiResponse::created(['id' => 1]);
    $body = $response->getData(true);

    expect($response->getStatusCode())->toBe(201);
    expect($body['success'])->toBeTrue();
    expect($body['data'])->toMatchArray(['id' => 1]);
});

test('successPaginated() wraps items in data, pagination in meta', function () {
    $items = collect([['id' => 1], ['id' => 2], ['id' => 3]]);
    $paginator = new LengthAwarePaginator(
        $items,
        100,   // total
        10,    // per_page
        1      // current_page
    );

    $response = ApiResponse::successPaginated($paginator, 'Data halaman 1');
    $body = $response->getData(true);

    expect($response->getStatusCode())->toBe(200);
    expect($body['success'])->toBeTrue();
    expect($body['data'])->toHaveCount(3);
    expect($body['meta']['pagination'])->toMatchArray([
        'current_page' => 1,
        'last_page' => 10,
        'per_page' => 10,
        'total' => 100,
    ]);
    expect($body['meta']['message'])->toBe('Data halaman 1');
});

test('error() returns success:false, message, status code', function () {
    $response = ApiResponse::error('Bukan urusanmu.', 403);
    $body = $response->getData(true);

    expect($response->getStatusCode())->toBe(403);
    expect($body['success'])->toBeFalse();
    expect($body['data'])->toBeNull();
    expect($body['meta']['message'])->toBe('Bukan urusanmu.');
});

test('error() with validation errors includes errors in meta', function () {
    $errors = ['email' => ['Email wajib diisi.'], 'nik' => ['NIK sudah terpakai.']];

    $response = ApiResponse::error('Validasi gagal.', 422, $errors);
    $body = $response->getData(true);

    expect($response->getStatusCode())->toBe(422);
    expect($body['meta']['errors'])->toMatchArray($errors);
});

test('success() tanpa parameter tetap valid', function () {
    $response = ApiResponse::success();
    $body = $response->getData(true);

    expect($response->getStatusCode())->toBe(200);
    expect($body['success'])->toBeTrue();
    expect($body['data'])->toBeNull();
});
