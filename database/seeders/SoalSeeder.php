<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Enums\TipeSoal;
use App\Models\OpsiSoal;
use App\Models\Soal;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Bank soal untuk UAS Genap 2025/2026 — SMK Negeri 1 Cibinong, Kelas X-TKJ-1.
 *
 * Distribusi 50 soal (sesuai SPEC §9 — Versi A baseline):
 *   PG=20 · Benar-Salah=10 · Labeling=10 · Menjodohkan=10 = 50 soal
 *
 *   [TIK]  Teknologi Informasi & Komunikasi — 25 soal
 *          10 PG (2 poin) + 5 BS (1 poin) + 5 Labeling (3 poin) + 5 Menjodohkan (3 poin)
 *
 *   [MTK]  Matematika Dasar — 14 soal
 *          5 PG (2 poin) + 3 BS (1 poin) + 3 Labeling (3 poin) + 3 Menjodohkan (3 poin)
 *
 *   [BIND] Bahasa Indonesia — 11 soal
 *          5 PG (2 poin) + 2 BS (1 poin) + 2 Labeling (3 poin) + 2 Menjodohkan (3 poin)
 *
 * ID soal disimpan di static arrays agar JadwalUjianSeeder dapat membacanya
 * tanpa query ulang ke database.
 */
class SoalSeeder extends Seeder
{
    /** @var int[] ID soal TIK (25 soal) */
    public static array $tikIds = [];

    /** @var int[] ID soal Matematika (14 soal) */
    public static array $mtkIds = [];

    /** @var int[] ID soal Bahasa Indonesia (11 soal) */
    public static array $bindIds = [];

    /**
     * Untuk menjodohkan: peta kiri→kanan (opsi_id kiri → opsi_id kanan yang benar).
     * Dipakai SimulasiSesiSeeder untuk mengisi jawaban benar.
     *
     * @var array<int,int>
     */
    public static array $menjodohkanCorrectMap = [];

    // ──────────────────────────────────────────────────────────────────────
    public function run(): void
    {
        // Reset static arrays — penting saat seeder dipanggil berkali-kali dalam
        // satu proses (mis. RefreshDatabase di test suite) agar tidak terakumulasi.
        self::$tikIds = [];
        self::$mtkIds = [];
        self::$bindIds = [];
        self::$menjodohkanCorrectMap = [];

        $adminId = User::whereHas('role', fn ($q) => $q->where('nama_role', RoleName::Admin))->value('id');

        $this->seedTIK($adminId);
        $this->seedMatematika($adminId);
        $this->seedBahasaIndonesia($adminId);
    }

    // ══════════════════════════════════════════════════════════════════════
    // TIK — 10 PG + 5 BS + 5 Labeling + 5 Menjodohkan = 25 soal
    // ══════════════════════════════════════════════════════════════════════

    private function seedTIK(int $adminId): void
    {
        // ── PG TIK (10 soal × 2 poin) ─────────────────────────────────────
        $pgTik = [
            [
                'pertanyaan' => 'CPU adalah singkatan dari...',
                'opsi' => ['Central Processing Unit', 'Computer Processing Unit', 'Control Program Unit', 'Central Program Utility'],
                'kunci' => 0,
                'pembahasan' => 'CPU (Central Processing Unit) adalah komponen utama komputer yang menjalankan instruksi program.',
            ],
            [
                'pertanyaan' => 'Perangkat berikut yang menghasilkan cetakan fisik (hardcopy) adalah...',
                'opsi' => ['Monitor', 'Scanner', 'Printer', 'Speaker'],
                'kunci' => 2,
                'pembahasan' => 'Printer menghasilkan output fisik berupa cetakan di atas kertas atau media lainnya.',
            ],
            [
                'pertanyaan' => 'Port default yang digunakan protokol HTTP adalah...',
                'opsi' => ['21', '25', '80', '443'],
                'kunci' => 2,
                'pembahasan' => 'HTTP menggunakan port 80 secara default, sedangkan HTTPS menggunakan port 443.',
            ],
            [
                'pertanyaan' => '1 byte terdiri atas berapa bit?',
                'opsi' => ['4', '8', '16', '32'],
                'kunci' => 1,
                'pembahasan' => '1 byte = 8 bit. Satuan ini menjadi dasar pengukuran kapasitas penyimpanan digital.',
            ],
            [
                'pertanyaan' => 'Di antara pilihan berikut, manakah yang merupakan sistem operasi (OS)?',
                'opsi' => ['Microsoft Word', 'Google Chrome', 'Linux Ubuntu', 'Adobe Photoshop'],
                'kunci' => 2,
                'pembahasan' => 'Linux Ubuntu adalah sistem operasi. Word, Chrome, dan Photoshop adalah aplikasi yang berjalan di atas OS.',
            ],
            [
                'pertanyaan' => 'Perangkat penyimpanan yang bersifat non-volatile (data tidak hilang saat komputer dimatikan) adalah...',
                'opsi' => ['RAM', 'Cache', 'Register CPU', 'Hard Disk Drive (HDD)'],
                'kunci' => 3,
                'pembahasan' => 'HDD bersifat non-volatile sehingga data tetap ada meskipun komputer dimatikan. RAM bersifat volatile.',
            ],
            [
                'pertanyaan' => 'RAM adalah singkatan dari...',
                'opsi' => ['Read-only Access Memory', 'Random Access Memory', 'Rapid Application Module', 'Random Application Memory'],
                'kunci' => 1,
                'pembahasan' => 'RAM (Random Access Memory) adalah memori utama yang digunakan untuk menyimpan data sementara saat komputer aktif.',
            ],
            [
                'pertanyaan' => 'Fungsi utama DNS (Domain Name System) dalam jaringan adalah...',
                'opsi' => ['Mengatur kecepatan koneksi internet', 'Mengamankan data yang ditransmisikan', 'Mengubah nama domain menjadi alamat IP', 'Membagi bandwidth antar perangkat'],
                'kunci' => 2,
                'pembahasan' => 'DNS menerjemahkan nama domain yang mudah diingat (mis. google.com) menjadi alamat IP yang dipahami komputer.',
            ],
            [
                'pertanyaan' => 'Pada topologi jaringan Star, semua perangkat terhubung ke...',
                'opsi' => ['Kabel backbone tunggal', 'Satu perangkat pusat (switch/hub)', 'Perangkat berikutnya membentuk lingkaran', 'Setiap perangkat lainnya secara langsung'],
                'kunci' => 1,
                'pembahasan' => 'Topologi Star menggunakan switch atau hub sebagai pusat (node pusat) yang menghubungkan semua perangkat.',
            ],
            [
                'pertanyaan' => 'HTML adalah singkatan dari...',
                'opsi' => ['High-level Text Modeling Language', 'HyperText Markup Language', 'HyperText Managing Language', 'Hyper Transfer Markup Language'],
                'kunci' => 1,
                'pembahasan' => 'HTML (HyperText Markup Language) adalah bahasa markup standar untuk membuat halaman web.',
            ],
        ];

        foreach ($pgTik as $data) {
            $soal = Soal::create([
                'tipe' => TipeSoal::Pg,
                'pertanyaan' => $data['pertanyaan'],
                'poin' => 2,
                'pembahasan' => $data['pembahasan'],
                'created_by' => $adminId,
            ]);
            foreach ($data['opsi'] as $i => $teks) {
                OpsiSoal::create([
                    'soal_id' => $soal->id,
                    'teks' => $teks,
                    'is_kunci' => $i === $data['kunci'],
                ]);
            }
            self::$tikIds[] = $soal->id;
        }

        // ── Benar-Salah TIK (5 soal × 1 poin) ────────────────────────────
        $bsTik = [
            [
                'pertanyaan' => 'IPv6 menggunakan 128 bit untuk merepresentasikan alamat IP.',
                'jawaban_benar_bool' => true,
                'pembahasan' => 'IPv6 menggunakan 128 bit (vs IPv4 yang hanya 32 bit), sehingga dapat menampung jauh lebih banyak alamat.',
            ],
            [
                'pertanyaan' => 'Hard Disk Drive (HDD) termasuk dalam kategori memori volatile.',
                'jawaban_benar_bool' => false,
                'pembahasan' => 'HDD bersifat non-volatile. Memori volatile adalah memori yang kehilangan data saat listrik dimatikan, contohnya RAM.',
            ],
            [
                'pertanyaan' => 'Browser (peramban web) merupakan contoh perangkat lunak aplikasi.',
                'jawaban_benar_bool' => true,
                'pembahasan' => 'Browser seperti Chrome dan Firefox adalah aplikasi yang berjalan di atas sistem operasi untuk mengakses web.',
            ],
            [
                'pertanyaan' => 'Modem berfungsi mengubah sinyal digital menjadi sinyal analog dan sebaliknya.',
                'jawaban_benar_bool' => true,
                'pembahasan' => 'Modem (Modulator-Demodulator) mengubah sinyal digital dari komputer ke analog untuk transmisi via jalur telepon dan sebaliknya.',
            ],
            [
                'pertanyaan' => 'Dalam sistem biner komputer, 1 Kilobyte (KB) sama dengan 1.000 Byte.',
                'jawaban_benar_bool' => false,
                'pembahasan' => 'Dalam sistem biner, 1 KB = 1.024 Byte (2^10). Standar SI (desimal) mendefinisikan 1 KB = 1.000 Byte, tetapi dalam konteks komputer umumnya dipakai 1.024 Byte.',
            ],
        ];

        foreach ($bsTik as $data) {
            $soal = Soal::create([
                'tipe' => TipeSoal::BenarSalah,
                'pertanyaan' => $data['pertanyaan'],
                'jawaban_benar_bool' => $data['jawaban_benar_bool'],
                'poin' => 1,
                'pembahasan' => $data['pembahasan'],
                'created_by' => $adminId,
            ]);
            self::$tikIds[] = $soal->id;
        }

        // ── Labeling TIK (5 soal × 3 poin) ───────────────────────────────
        // nomor_urut pada opsi_soal = posisi benar. Jawaban: nomor_jawaban === nomor_urut.
        $labelingTik = [
            [
                'pertanyaan' => 'Perhatikan diagram komponen sistem komputer berikut. Beri label yang tepat pada setiap bagian (1=kiri, 2=tengah, 3=kanan)!',
                'pembahasan' => 'Sistem komputer terdiri dari Unit Masukan, Unit Pemroses (CPU), dan Unit Keluaran yang bekerja secara berurutan.',
                'label' => [
                    ['teks' => 'Unit Masukan (Input Device)',   'nomor_urut' => 1],
                    ['teks' => 'Unit Pemroses (CPU)',           'nomor_urut' => 2],
                    ['teks' => 'Unit Keluaran (Output Device)', 'nomor_urut' => 3],
                ],
            ],
            [
                'pertanyaan' => 'Perhatikan diagram model referensi OSI berikut. Identifikasi lapisan (layer) yang ditunjukkan (1=bawah, 2=tengah, 3=atas) dari kelompok lapisan yang disajikan!',
                'pembahasan' => 'Model OSI: Physical Layer di paling bawah (layer 1), Network Layer di tengah (layer 3), Application Layer di paling atas (layer 7).',
                'label' => [
                    ['teks' => 'Physical Layer (Lapisan Fisik)',       'nomor_urut' => 1],
                    ['teks' => 'Network Layer (Lapisan Jaringan)',     'nomor_urut' => 2],
                    ['teks' => 'Application Layer (Lapisan Aplikasi)', 'nomor_urut' => 3],
                ],
            ],
            [
                'pertanyaan' => 'Perhatikan diagram topologi jaringan berikut. Beri label pada setiap perangkat jaringan yang ditandai (posisi 1, 2, dan 3)!',
                'pembahasan' => 'Router menghubungkan antar jaringan berbeda, Switch menghubungkan perangkat dalam satu LAN, Access Point menyediakan koneksi nirkabel (Wi-Fi).',
                'label' => [
                    ['teks' => 'Router',       'nomor_urut' => 1],
                    ['teks' => 'Switch',        'nomor_urut' => 2],
                    ['teks' => 'Access Point',  'nomor_urut' => 3],
                ],
            ],
            [
                'pertanyaan' => 'Perhatikan diagram lapisan model TCP/IP berikut. Identifikasi nama lapisan dari bawah ke atas (1=paling bawah, 2=tengah, 3=paling atas)!',
                'pembahasan' => 'TCP/IP terdiri dari 4 lapisan. Network Access di bawah (akses fisik), Internet di tengah (routing/IP), Application di atas (protokol aplikasi seperti HTTP/FTP).',
                'label' => [
                    ['teks' => 'Network Access Layer (Lapisan Akses Jaringan)', 'nomor_urut' => 1],
                    ['teks' => 'Internet Layer (Lapisan Internet)',              'nomor_urut' => 2],
                    ['teks' => 'Application Layer (Lapisan Aplikasi)',           'nomor_urut' => 3],
                ],
            ],
            [
                'pertanyaan' => 'Perhatikan daftar perangkat komputer berikut. Klasifikasikan setiap perangkat ke dalam kategori yang tepat (1=perangkat masukan, 2=perangkat keluaran, 3=perangkat penyimpanan)!',
                'pembahasan' => 'Keyboard adalah perangkat masukan (input). Monitor adalah perangkat keluaran (output). Hard Disk Drive adalah perangkat penyimpanan (storage).',
                'label' => [
                    ['teks' => 'Keyboard',         'nomor_urut' => 1],
                    ['teks' => 'Monitor',           'nomor_urut' => 2],
                    ['teks' => 'Hard Disk Drive',   'nomor_urut' => 3],
                ],
            ],
        ];

        foreach ($labelingTik as $i => $data) {
            $soal = Soal::create([
                'tipe' => TipeSoal::Labeling,
                'pertanyaan' => $data['pertanyaan'],
                'poin' => 3,
                'pembahasan' => $data['pembahasan'],
                'media_url' => "https://storage.cbt-pwa.test/soal/labeling-tik-" . ($i + 1) . ".webp",
                'created_by' => $adminId,
            ]);
            foreach ($data['label'] as $lb) {
                OpsiSoal::create([
                    'soal_id' => $soal->id,
                    'teks' => $lb['teks'],
                    'nomor_urut' => $lb['nomor_urut'],
                    'is_kunci' => false,
                ]);
            }
            self::$tikIds[] = $soal->id;
        }

        // ── Menjodohkan TIK (5 soal × 3 poin, 3 pasangan per soal) ───────
        // Kiri (nomor_urut 1–3): item pertanyaan. Kanan (nomor_urut 4–6): pilihan jawaban.
        $menjodohkanTik = [
            [
                'pertanyaan' => 'Jodohkan singkatan perangkat keras komputer berikut dengan kepanjangannya yang tepat!',
                'pembahasan' => 'CPU = Central Processing Unit (otak komputer), RAM = Random Access Memory (memori kerja sementara), HDD = Hard Disk Drive (penyimpanan permanen).',
                'kiri' => [
                    ['teks' => 'CPU', 'pasangan' => 'Central Processing Unit'],
                    ['teks' => 'RAM', 'pasangan' => 'Random Access Memory'],
                    ['teks' => 'HDD', 'pasangan' => 'Hard Disk Drive'],
                ],
                'kanan' => ['Central Processing Unit', 'Random Access Memory', 'Hard Disk Drive'],
            ],
            [
                'pertanyaan' => 'Jodohkan protokol jaringan berikut dengan fungsi utamanya!',
                'pembahasan' => 'HTTP mentransfer halaman web, FTP digunakan untuk transfer file antar host, dan SMTP adalah protokol standar pengiriman email.',
                'kiri' => [
                    ['teks' => 'HTTP', 'pasangan' => 'Mentransfer halaman web (HyperText)'],
                    ['teks' => 'FTP',  'pasangan' => 'Mentransfer file antar komputer'],
                    ['teks' => 'SMTP', 'pasangan' => 'Mengirim email ke server tujuan'],
                ],
                'kanan' => ['Mentransfer halaman web (HyperText)', 'Mentransfer file antar komputer', 'Mengirim email ke server tujuan'],
            ],
            [
                'pertanyaan' => 'Jodohkan jenis perangkat lunak (software) berikut dengan deskripsi dan contohnya yang tepat!',
                'pembahasan' => 'Sistem Operasi mengelola hardware (contoh: Windows, Linux), Perangkat Lunak Aplikasi untuk tugas tertentu (contoh: Word, Photoshop), Utility Software untuk pemeliharaan sistem (contoh: antivirus).',
                'kiri' => [
                    ['teks' => 'Sistem Operasi',             'pasangan' => 'Mengelola sumber daya hardware (contoh: Windows, Linux)'],
                    ['teks' => 'Perangkat Lunak Aplikasi',   'pasangan' => 'Program untuk tugas tertentu pengguna (contoh: Word, Photoshop)'],
                    ['teks' => 'Utility Software',           'pasangan' => 'Pemeliharaan & optimasi sistem (contoh: antivirus, disk cleaner)'],
                ],
                'kanan' => [
                    'Mengelola sumber daya hardware (contoh: Windows, Linux)',
                    'Program untuk tugas tertentu pengguna (contoh: Word, Photoshop)',
                    'Pemeliharaan & optimasi sistem (contoh: antivirus, disk cleaner)',
                ],
            ],
            [
                'pertanyaan' => 'Jodohkan satuan penyimpanan data berikut dengan nilai konversinya dalam Byte!',
                'pembahasan' => '1 KB = 1.024 Byte (2^10), 1 MB = 1.048.576 Byte (1.024 KB), 1 GB = 1.073.741.824 Byte (1.024 MB). Konversi biner dipakai dalam sistem komputer.',
                'kiri' => [
                    ['teks' => '1 Kilobyte (KB)', 'pasangan' => '1.024 Byte'],
                    ['teks' => '1 Megabyte (MB)', 'pasangan' => '1.048.576 Byte (1.024 KB)'],
                    ['teks' => '1 Gigabyte (GB)', 'pasangan' => '1.073.741.824 Byte (1.024 MB)'],
                ],
                'kanan' => ['1.024 Byte', '1.048.576 Byte (1.024 KB)', '1.073.741.824 Byte (1.024 MB)'],
            ],
            [
                'pertanyaan' => 'Jodohkan jenis jaringan komputer berikut dengan cakupan areanya yang tepat!',
                'pembahasan' => 'LAN mencakup area kecil (gedung/ruangan), MAN mencakup area kota/metropolitan, WAN mencakup area luas seperti negara atau benua (contoh: internet).',
                'kiri' => [
                    ['teks' => 'LAN (Local Area Network)',        'pasangan' => 'Area kecil seperti gedung atau satu ruangan'],
                    ['teks' => 'MAN (Metropolitan Area Network)', 'pasangan' => 'Area kota atau kawasan metropolitan'],
                    ['teks' => 'WAN (Wide Area Network)',         'pasangan' => 'Area luas seperti negara atau benua'],
                ],
                'kanan' => [
                    'Area kecil seperti gedung atau satu ruangan',
                    'Area kota atau kawasan metropolitan',
                    'Area luas seperti negara atau benua',
                ],
            ],
        ];

        foreach ($menjodohkanTik as $data) {
            $soal = Soal::create([
                'tipe' => TipeSoal::Menjodohkan,
                'pertanyaan' => $data['pertanyaan'],
                'poin' => 3,
                'pembahasan' => $data['pembahasan'],
                'created_by' => $adminId,
            ]);

            $opsiKiri = [];
            foreach ($data['kiri'] as $i => $item) {
                $opsiKiri[] = OpsiSoal::create([
                    'soal_id' => $soal->id,
                    'teks' => $item['teks'],
                    'pasangan' => $item['pasangan'],
                    'nomor_urut' => $i + 1,
                    'is_kunci' => false,
                ]);
            }

            $opsiKanan = [];
            foreach ($data['kanan'] as $i => $teks) {
                $opsiKanan[] = OpsiSoal::create([
                    'soal_id' => $soal->id,
                    'teks' => $teks,
                    'pasangan' => null,
                    'nomor_urut' => count($data['kiri']) + $i + 1,
                    'is_kunci' => false,
                ]);
            }

            foreach ($opsiKiri as $i => $kiri) {
                self::$menjodohkanCorrectMap[$kiri->id] = $opsiKanan[$i]->id;
            }

            self::$tikIds[] = $soal->id;
        }
    }

    // ══════════════════════════════════════════════════════════════════════
    // MTK — 5 PG + 3 BS + 3 Labeling + 3 Menjodohkan = 14 soal
    // ══════════════════════════════════════════════════════════════════════

    private function seedMatematika(int $adminId): void
    {
        // ── PG MTK (5 soal × 2 poin) ──────────────────────────────────────
        $pgMtk = [
            [
                'pertanyaan' => 'Hasil dari 2⁸ adalah...',
                'opsi' => ['64', '128', '256', '512'],
                'kunci' => 2,
                'pembahasan' => '2⁸ = 2 × 2 × 2 × 2 × 2 × 2 × 2 × 2 = 256.',
            ],
            [
                'pertanyaan' => 'Faktor Persekutuan Terbesar (FPB) dari 24 dan 36 adalah...',
                'opsi' => ['6', '8', '12', '18'],
                'kunci' => 2,
                'pembahasan' => 'Faktorisasi: 24 = 2³×3 dan 36 = 2²×3². FPB = 2²×3 = 12.',
            ],
            [
                'pertanyaan' => 'Nilai x pada persamaan 2x + 3 = 15 adalah...',
                'opsi' => ['4', '5', '6', '7'],
                'kunci' => 2,
                'pembahasan' => '2x + 3 = 15 → 2x = 12 → x = 6.',
            ],
            [
                'pertanyaan' => 'Luas persegi panjang dengan panjang 8 cm dan lebar 5 cm adalah...',
                'opsi' => ['26 cm²', '40 cm²', '80 cm²', '13 cm²'],
                'kunci' => 1,
                'pembahasan' => 'Luas = panjang × lebar = 8 × 5 = 40 cm².',
            ],
            [
                'pertanyaan' => '√144 = ...',
                'opsi' => ['11', '12', '13', '14'],
                'kunci' => 1,
                'pembahasan' => '12 × 12 = 144, sehingga √144 = 12.',
            ],
        ];

        foreach ($pgMtk as $data) {
            $soal = Soal::create([
                'tipe' => TipeSoal::Pg,
                'pertanyaan' => $data['pertanyaan'],
                'poin' => 2,
                'pembahasan' => $data['pembahasan'],
                'created_by' => $adminId,
            ]);
            foreach ($data['opsi'] as $i => $teks) {
                OpsiSoal::create([
                    'soal_id' => $soal->id,
                    'teks' => $teks,
                    'is_kunci' => $i === $data['kunci'],
                ]);
            }
            self::$mtkIds[] = $soal->id;
        }

        // ── Benar-Salah MTK (3 soal × 1 poin) ────────────────────────────
        $bsMtk = [
            [
                'pertanyaan' => 'Bilangan prima terkecil adalah 2.',
                'jawaban_benar_bool' => true,
                'pembahasan' => 'Benar. Bilangan prima adalah bilangan yang hanya dapat dibagi 1 dan dirinya sendiri. Bilangan prima terkecil adalah 2 (bukan 1).',
            ],
            [
                'pertanyaan' => 'Luas segitiga dihitung dengan rumus: L = ½ × alas × tinggi.',
                'jawaban_benar_bool' => true,
                'pembahasan' => 'Benar. Rumus luas segitiga adalah L = ½ × a × t, di mana a adalah alas dan t adalah tinggi.',
            ],
            [
                'pertanyaan' => 'KPK (Kelipatan Persekutuan Terkecil) dari 4 dan 6 adalah 12.',
                'jawaban_benar_bool' => true,
                'pembahasan' => 'Benar. Kelipatan 4: 4, 8, 12, 16... Kelipatan 6: 6, 12, 18... KPK = 12.',
            ],
        ];

        foreach ($bsMtk as $data) {
            $soal = Soal::create([
                'tipe' => TipeSoal::BenarSalah,
                'pertanyaan' => $data['pertanyaan'],
                'jawaban_benar_bool' => $data['jawaban_benar_bool'],
                'poin' => 1,
                'pembahasan' => $data['pembahasan'],
                'created_by' => $adminId,
            ]);
            self::$mtkIds[] = $soal->id;
        }

        // ── Labeling MTK (3 soal × 3 poin) ────────────────────────────────
        $labelingMtk = [
            [
                'pertanyaan' => 'Perhatikan diagram sistem koordinat Kartesius berikut. Beri label yang tepat pada setiap bagian yang ditandai (1=horizontal, 2=vertikal, 3=titik perpotongan)!',
                'pembahasan' => 'Sumbu X (absis) adalah sumbu horizontal. Sumbu Y (ordinat) adalah sumbu vertikal. Titik Origin (0,0) adalah titik perpotongan keduanya.',
                'label' => [
                    ['teks' => 'Sumbu X (Absis — horizontal)',      'nomor_urut' => 1],
                    ['teks' => 'Sumbu Y (Ordinat — vertikal)',      'nomor_urut' => 2],
                    ['teks' => 'Titik Asal / Origin (0, 0)',         'nomor_urut' => 3],
                ],
            ],
            [
                'pertanyaan' => 'Perhatikan gambar segitiga siku-siku berikut. Beri label yang tepat pada setiap sisi (1=sisi tegak, 2=sisi mendatar, 3=sisi miring)!',
                'pembahasan' => 'Segitiga siku-siku memiliki: sisi tegak (tinggi), sisi mendatar (alas), dan sisi miring (hipotenusa) yang merupakan sisi terpanjang di depan sudut siku-siku.',
                'label' => [
                    ['teks' => 'Sisi Tegak (Tinggi)',               'nomor_urut' => 1],
                    ['teks' => 'Sisi Mendatar (Alas)',              'nomor_urut' => 2],
                    ['teks' => 'Sisi Miring (Hipotenusa)',          'nomor_urut' => 3],
                ],
            ],
            [
                'pertanyaan' => 'Perhatikan urutan prioritas operasi hitung berikut. Urutkan dari prioritas tertinggi (1) hingga terendah (3) dalam aturan PEMDAS!',
                'pembahasan' => 'Urutan PEMDAS: (1) Pangkat & Akar — prioritas tertinggi, (2) Perkalian & Pembagian — dari kiri ke kanan, (3) Penjumlahan & Pengurangan — prioritas terendah.',
                'label' => [
                    ['teks' => 'Pangkat & Akar',               'nomor_urut' => 1],
                    ['teks' => 'Perkalian & Pembagian',        'nomor_urut' => 2],
                    ['teks' => 'Penjumlahan & Pengurangan',    'nomor_urut' => 3],
                ],
            ],
        ];

        foreach ($labelingMtk as $i => $data) {
            $soal = Soal::create([
                'tipe' => TipeSoal::Labeling,
                'pertanyaan' => $data['pertanyaan'],
                'poin' => 3,
                'pembahasan' => $data['pembahasan'],
                'media_url' => "https://storage.cbt-pwa.test/soal/labeling-mtk-" . ($i + 1) . ".webp",
                'created_by' => $adminId,
            ]);
            foreach ($data['label'] as $lb) {
                OpsiSoal::create([
                    'soal_id' => $soal->id,
                    'teks' => $lb['teks'],
                    'nomor_urut' => $lb['nomor_urut'],
                    'is_kunci' => false,
                ]);
            }
            self::$mtkIds[] = $soal->id;
        }

        // ── Menjodohkan MTK (3 soal × 3 poin, 3 pasangan per soal) ────────
        $menjodohkanMtk = [
            [
                'pertanyaan' => 'Jodohkan rumus luas bangun datar berikut dengan nama bangunnya yang tepat!',
                'pembahasan' => 'Rumus luas: Persegi = s² (sisi kali sisi), Persegi Panjang = p × l, Segitiga = ½ × alas × tinggi.',
                'kiri' => [
                    ['teks' => 'L = s × s (s = sisi)',           'pasangan' => 'Persegi'],
                    ['teks' => 'L = p × l (p = panjang, l = lebar)', 'pasangan' => 'Persegi Panjang'],
                    ['teks' => 'L = ½ × a × t',                  'pasangan' => 'Segitiga'],
                ],
                'kanan' => ['Persegi', 'Persegi Panjang', 'Segitiga'],
            ],
            [
                'pertanyaan' => 'Jodohkan satuan ukuran berikut dengan nilai konversinya yang benar!',
                'pembahasan' => '1 meter = 100 sentimeter, 1 kilogram = 1.000 gram, 1 jam = 60 menit. Konversi ini penting dalam perhitungan sehari-hari.',
                'kiri' => [
                    ['teks' => '1 meter',     'pasangan' => '100 sentimeter'],
                    ['teks' => '1 kilogram',  'pasangan' => '1.000 gram'],
                    ['teks' => '1 jam',       'pasangan' => '60 menit'],
                ],
                'kanan' => ['100 sentimeter', '1.000 gram', '60 menit'],
            ],
            [
                'pertanyaan' => 'Jodohkan istilah statistika dasar berikut dengan definisinya yang tepat!',
                'pembahasan' => 'Mean = rata-rata (jumlah semua data ÷ banyak data), Median = nilai tengah setelah data diurutkan, Modus = nilai yang paling sering muncul.',
                'kiri' => [
                    ['teks' => 'Mean (Rata-rata)', 'pasangan' => 'Jumlah semua data dibagi banyaknya data'],
                    ['teks' => 'Median',           'pasangan' => 'Nilai tengah setelah data diurutkan'],
                    ['teks' => 'Modus',            'pasangan' => 'Nilai yang paling sering muncul dalam data'],
                ],
                'kanan' => [
                    'Jumlah semua data dibagi banyaknya data',
                    'Nilai tengah setelah data diurutkan',
                    'Nilai yang paling sering muncul dalam data',
                ],
            ],
        ];

        foreach ($menjodohkanMtk as $data) {
            $soal = Soal::create([
                'tipe' => TipeSoal::Menjodohkan,
                'pertanyaan' => $data['pertanyaan'],
                'poin' => 3,
                'pembahasan' => $data['pembahasan'],
                'created_by' => $adminId,
            ]);

            $opsiKiri = [];
            foreach ($data['kiri'] as $i => $item) {
                $opsiKiri[] = OpsiSoal::create([
                    'soal_id' => $soal->id,
                    'teks' => $item['teks'],
                    'pasangan' => $item['pasangan'],
                    'nomor_urut' => $i + 1,
                    'is_kunci' => false,
                ]);
            }

            $opsiKanan = [];
            foreach ($data['kanan'] as $i => $teks) {
                $opsiKanan[] = OpsiSoal::create([
                    'soal_id' => $soal->id,
                    'teks' => $teks,
                    'pasangan' => null,
                    'nomor_urut' => count($data['kiri']) + $i + 1,
                    'is_kunci' => false,
                ]);
            }

            foreach ($opsiKiri as $i => $kiri) {
                self::$menjodohkanCorrectMap[$kiri->id] = $opsiKanan[$i]->id;
            }

            self::$mtkIds[] = $soal->id;
        }
    }

    // ══════════════════════════════════════════════════════════════════════
    // BIND — 5 PG + 2 BS + 2 Labeling + 2 Menjodohkan = 11 soal
    // ══════════════════════════════════════════════════════════════════════

    private function seedBahasaIndonesia(int $adminId): void
    {
        // ── PG BIND (5 soal × 2 poin) ─────────────────────────────────────
        $pgBind = [
            [
                'pertanyaan' => 'Sinonim (persamaan kata) dari kata "cerdas" adalah...',
                'opsi' => ['Bodoh', 'Pandai', 'Lambat', 'Lelah'],
                'kunci' => 1,
                'pembahasan' => 'Sinonim "cerdas" adalah "pandai" atau "pintar" — keduanya memiliki makna yang hampir sama.',
            ],
            [
                'pertanyaan' => 'Antonim (lawan kata) dari kata "sempit" adalah...',
                'opsi' => ['Kecil', 'Rendah', 'Luas', 'Pendek'],
                'kunci' => 2,
                'pembahasan' => 'Antonim "sempit" adalah "luas" atau "lapang".',
            ],
            [
                'pertanyaan' => 'Penulisan kata baku yang benar di bawah ini adalah...',
                'opsi' => ['Apotik', 'Kwalitas', 'Apotek', 'Sistim'],
                'kunci' => 2,
                'pembahasan' => '"Apotek" adalah bentuk baku menurut KBBI. "Apotik", "kwalitas" (baku: kualitas), dan "sistim" (baku: sistem) adalah bentuk tidak baku.',
            ],
            [
                'pertanyaan' => 'Majas yang memberikan sifat atau perilaku manusia kepada benda mati disebut...',
                'opsi' => ['Metafora', 'Simile', 'Personifikasi', 'Hiperbola'],
                'kunci' => 2,
                'pembahasan' => 'Majas personifikasi menggambarkan benda mati seolah-olah memiliki sifat manusia. Contoh: "Angin berbisik di telingaku."',
            ],
            [
                'pertanyaan' => 'Tujuan utama penulisan teks prosedur adalah...',
                'opsi' => ['Menyampaikan pendapat penulis', 'Menceritakan pengalaman pribadi', 'Memberikan panduan cara melakukan sesuatu', 'Menggambarkan suatu objek secara detail'],
                'kunci' => 2,
                'pembahasan' => 'Teks prosedur bertujuan memberikan petunjuk atau panduan langkah-langkah untuk melakukan suatu kegiatan.',
            ],
        ];

        foreach ($pgBind as $data) {
            $soal = Soal::create([
                'tipe' => TipeSoal::Pg,
                'pertanyaan' => $data['pertanyaan'],
                'poin' => 2,
                'pembahasan' => $data['pembahasan'],
                'created_by' => $adminId,
            ]);
            foreach ($data['opsi'] as $i => $teks) {
                OpsiSoal::create([
                    'soal_id' => $soal->id,
                    'teks' => $teks,
                    'is_kunci' => $i === $data['kunci'],
                ]);
            }
            self::$bindIds[] = $soal->id;
        }

        // ── Benar-Salah BIND (2 soal × 1 poin) ───────────────────────────
        $bsBind = [
            [
                'pertanyaan' => 'Paragraf induktif memiliki gagasan utama yang terletak di akhir paragraf.',
                'jawaban_benar_bool' => true,
                'pembahasan' => 'Benar. Paragraf induktif bergerak dari kalimat-kalimat penjelas khusus menuju kesimpulan (gagasan utama) di bagian akhir.',
            ],
            [
                'pertanyaan' => 'EYD adalah singkatan dari "Ejaan Yang Disempurnakan".',
                'jawaban_benar_bool' => true,
                'pembahasan' => 'Benar. EYD (Ejaan Yang Disempurnakan) adalah pedoman ejaan bahasa Indonesia yang diberlakukan tahun 1972 dan telah diperbarui menjadi PUEBI pada 2016.',
            ],
        ];

        foreach ($bsBind as $data) {
            $soal = Soal::create([
                'tipe' => TipeSoal::BenarSalah,
                'pertanyaan' => $data['pertanyaan'],
                'jawaban_benar_bool' => $data['jawaban_benar_bool'],
                'poin' => 1,
                'pembahasan' => $data['pembahasan'],
                'created_by' => $adminId,
            ]);
            self::$bindIds[] = $soal->id;
        }

        // ── Labeling BIND (2 soal × 3 poin) ──────────────────────────────
        $labelingBind = [
            [
                'pertanyaan' => 'Perhatikan struktur teks prosedur berikut. Beri label yang tepat pada setiap bagian (1=bagian pertama, 2=bagian kedua, 3=bagian ketiga)!',
                'pembahasan' => 'Teks prosedur memiliki 3 bagian: (1) Tujuan — menjelaskan apa yang akan dilakukan, (2) Alat dan Bahan — daftar perlengkapan yang diperlukan, (3) Langkah-langkah — urutan cara melakukan.',
                'label' => [
                    ['teks' => 'Tujuan (menjelaskan apa yang akan dibuat/dilakukan)',  'nomor_urut' => 1],
                    ['teks' => 'Alat dan Bahan (daftar perlengkapan yang dibutuhkan)', 'nomor_urut' => 2],
                    ['teks' => 'Langkah-langkah (urutan cara melakukan kegiatan)',     'nomor_urut' => 3],
                ],
            ],
            [
                'pertanyaan' => 'Perhatikan jenis paragraf berdasarkan letak kalimat utamanya. Identifikasi posisi kalimat utama yang tepat (1=di awal, 2=di akhir, 3=di awal dan akhir)!',
                'pembahasan' => 'Paragraf Deduktif: kalimat utama di awal (umum→khusus). Paragraf Induktif: kalimat utama di akhir (khusus→umum). Paragraf Campuran: kalimat utama di awal dan dipertegas di akhir.',
                'label' => [
                    ['teks' => 'Paragraf Deduktif',  'nomor_urut' => 1],
                    ['teks' => 'Paragraf Induktif',  'nomor_urut' => 2],
                    ['teks' => 'Paragraf Campuran',  'nomor_urut' => 3],
                ],
            ],
        ];

        foreach ($labelingBind as $i => $data) {
            $soal = Soal::create([
                'tipe' => TipeSoal::Labeling,
                'pertanyaan' => $data['pertanyaan'],
                'poin' => 3,
                'pembahasan' => $data['pembahasan'],
                'media_url' => "https://storage.cbt-pwa.test/soal/labeling-bind-" . ($i + 1) . ".webp",
                'created_by' => $adminId,
            ]);
            foreach ($data['label'] as $lb) {
                OpsiSoal::create([
                    'soal_id' => $soal->id,
                    'teks' => $lb['teks'],
                    'nomor_urut' => $lb['nomor_urut'],
                    'is_kunci' => false,
                ]);
            }
            self::$bindIds[] = $soal->id;
        }

        // ── Menjodohkan BIND (2 soal × 3 poin, 3 pasangan per soal) ──────
        $menjodohkanBind = [
            [
                'pertanyaan' => 'Jodohkan jenis majas berikut dengan contoh kalimatnya yang tepat!',
                'pembahasan' => 'Personifikasi: benda berperilaku seperti manusia. Simile: perumpamaan dengan kata "seperti/bagai". Hiperbola: melebih-lebihkan fakta.',
                'kiri' => [
                    ['teks' => 'Personifikasi',          'pasangan' => '"Angin menari-nari di halaman rumah"'],
                    ['teks' => 'Simile (Perumpamaan)',   'pasangan' => '"Wajahnya bersih seputih kapas"'],
                    ['teks' => 'Hiperbola',              'pasangan' => '"Suaranya menggelegar membelah langit"'],
                ],
                'kanan' => [
                    '"Angin menari-nari di halaman rumah"',
                    '"Wajahnya bersih seputih kapas"',
                    '"Suaranya menggelegar membelah langit"',
                ],
            ],
            [
                'pertanyaan' => 'Jodohkan jenis kalimat berikut dengan ciri-ciri utamanya yang tepat!',
                'pembahasan' => 'Kalimat aktif: subjek melakukan tindakan (ber-/me-). Kalimat pasif: subjek dikenai tindakan (di-/ter-). Kalimat majemuk: dua klausa atau lebih dihubungkan konjungsi.',
                'kiri' => [
                    ['teks' => 'Kalimat Aktif',    'pasangan' => 'Subjek melakukan pekerjaan (predikat berimbuhan me-/ber-)'],
                    ['teks' => 'Kalimat Pasif',    'pasangan' => 'Subjek dikenai pekerjaan (predikat berimbuhan di-/ter-)'],
                    ['teks' => 'Kalimat Majemuk',  'pasangan' => 'Terdiri dari dua klausa atau lebih yang dihubungkan konjungsi'],
                ],
                'kanan' => [
                    'Subjek melakukan pekerjaan (predikat berimbuhan me-/ber-)',
                    'Subjek dikenai pekerjaan (predikat berimbuhan di-/ter-)',
                    'Terdiri dari dua klausa atau lebih yang dihubungkan konjungsi',
                ],
            ],
        ];

        foreach ($menjodohkanBind as $data) {
            $soal = Soal::create([
                'tipe' => TipeSoal::Menjodohkan,
                'pertanyaan' => $data['pertanyaan'],
                'poin' => 3,
                'pembahasan' => $data['pembahasan'],
                'created_by' => $adminId,
            ]);

            $opsiKiri = [];
            foreach ($data['kiri'] as $i => $item) {
                $opsiKiri[] = OpsiSoal::create([
                    'soal_id' => $soal->id,
                    'teks' => $item['teks'],
                    'pasangan' => $item['pasangan'],
                    'nomor_urut' => $i + 1,
                    'is_kunci' => false,
                ]);
            }

            $opsiKanan = [];
            foreach ($data['kanan'] as $i => $teks) {
                $opsiKanan[] = OpsiSoal::create([
                    'soal_id' => $soal->id,
                    'teks' => $teks,
                    'pasangan' => null,
                    'nomor_urut' => count($data['kiri']) + $i + 1,
                    'is_kunci' => false,
                ]);
            }

            foreach ($opsiKiri as $i => $kiri) {
                self::$menjodohkanCorrectMap[$kiri->id] = $opsiKanan[$i]->id;
            }

            self::$bindIds[] = $soal->id;
        }
    }
}
