<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\StatusSesi;
use App\Models\AuditLog;
use App\Models\SesiUjian;
use App\Services\ScoringService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Scan sesi yang melewati waktu_batas dan auto-submit dengan scoring.
 * Dijadwalkan everyMinute() di routes/console.php.
 * NFR-R3: gagal scoring satu sesi tidak menghentikan batch — sesi di-retry pada run berikutnya.
 */
class AutoSubmitSesi extends Command
{
    protected $signature = 'sesi:auto-submit';

    protected $description = 'Auto-submit sesi sedang_berlangsung yang melewati waktu batas (kadaluarsa).';

    public function handle(ScoringService $scoringService): int
    {
        // Cari sesi yang lewat batas — gunakan index idx_status_batas.
        // Klausa kedua = jalur repair (F-01): sesi kadaluarsa yang tersangkut
        // TANPA skor (ditutup jalur lama yang tidak men-score) dinilai ulang.
        $sesiList = SesiUjian::where(function ($q) {
            $q->where('status', StatusSesi::SedangBerlangsung)
                ->where('waktu_batas', '<', now());
        })
            ->orWhere(function ($q) {
                $q->where('status', StatusSesi::Kadaluarsa)
                    ->whereNull('skor_total');
            })
            ->get();

        if ($sesiList->isEmpty()) {
            $this->line('AutoSubmitSesi: tidak ada sesi kadaluarsa.');

            return self::SUCCESS;
        }

        $processed = 0;
        $failed = 0;

        foreach ($sesiList as $sesi) {
            try {
                $result = $scoringService->finalisasi($sesi, StatusSesi::Kadaluarsa);

                AuditLog::create([
                    'user_id' => $sesi->user_id,
                    'action' => 'sesi.kadaluarsa',
                    'entity_type' => 'sesi_ujian',
                    'entity_id' => $sesi->id,
                    'metadata' => [
                        'skor_total' => $result['skor_total'],
                        'is_lulus' => $result['is_lulus'],
                        'auto_submit_at' => now()->toIso8601String(),
                    ],
                ]);

                $processed++;
                $this->line("  ✓ Sesi #{$sesi->id} (user {$sesi->user_id}) → kadaluarsa.");
            } catch (Throwable $e) {
                // Tahan error — sesi akan di-retry pada run berikutnya
                $failed++;
                Log::error("AutoSubmitSesi: gagal memproses sesi #{$sesi->id}", [
                    'user_id' => $sesi->user_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $this->warn("  ✗ Sesi #{$sesi->id} gagal: {$e->getMessage()}");
            }
        }

        $this->info("AutoSubmitSesi selesai: {$processed} diproses, {$failed} gagal.");

        return self::SUCCESS;
    }
}
