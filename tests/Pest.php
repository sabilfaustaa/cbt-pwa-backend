<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
 * Konfigurasi global Pest.
 *
 * Reset sequence PostgreSQL dilakukan di TestCase::setUp() agar berjalan
 * SETELAH RefreshDatabase memulai transaksi (setval bersifat non-transactional
 * sehingga efeknya permanen walau di dalam transaksi yang di-rollback).
 */
uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(TestCase::class, RefreshDatabase::class)->in('Unit');
