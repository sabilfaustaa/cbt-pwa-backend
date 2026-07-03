<?php

test('health endpoint returns ok', function () {
    $response = $this->getJson('/api/v1/health');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'status' => 'ok',
            ],
        ])
        ->assertJsonStructure([
            'success',
            'data' => [
                'status',
                'time',
            ],
        ]);
});
