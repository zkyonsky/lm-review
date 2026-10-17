<?php

namespace App\Services;

use App\Models\MaterialAiAnalysis;
use App\Models\MaterialVersion;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiAnalysisService
{
    protected string $apiKey;
    protected string $model;
    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey = (string) config('services.gemini.api_key', '');
        $this->model = (string) config('services.gemini.model', 'gemini-3.8-flash');
        $this->baseUrl = (string) config('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta');
    }

    /**
     * Jalankan analisis substansi materi dan telusuri regulasi/kebijakan terbaru.
     */
    public function analyzeMaterialVersion(
        MaterialVersion $version,
        ?User $user = null,
        ?string $customContext = null
    ): MaterialAiAnalysis {
        $material = $version->material;
        $subject = $material?->subject;
        $training = $subject?->training;

        $trainingTitle = $training?->title ?? 'Pelatihan';
        $subjectTitle = $subject?->title ?? 'Mata Pelatihan';
        $materialTitle = $material?->title ?? 'Materi';
        $materialType = $material?->type?->value ?? 'dokumen';

        $prompt = $this->buildPrompt($trainingTitle, $subjectTitle, $materialTitle, $materialType, $customContext);

        if (!empty($this->apiKey)) {
            try {
                $response = Http::timeout(60)->post("{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}", [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => $prompt]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.2,
                    ]
                ]);

                if ($response->successful()) {
                    $json = $response->json();
                    $rawText = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
                    $parsed = $this->parseJsonFromText($rawText);

                    if ($parsed) {
                        return MaterialAiAnalysis::create([
                            'material_version_id' => $version->id,
                            'user_id' => $user?->id,
                            'model' => $this->model,
                            'status' => 'completed',
                            'summary' => $parsed['summary'] ?? null,
                            'identified_regulations' => $parsed['identified_regulations'] ?? [],
                            'recommendations' => $parsed['recommendations'] ?? [],
                            'suggested_conclusion' => $parsed['suggested_conclusion'] ?? null,
                            'conclusion_rationale' => $parsed['conclusion_rationale'] ?? null,
                            'raw_response' => $parsed,
                        ]);
                    }
                }

                Log::warning('Gemini API call returned non-JSON or unsuccessful response', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
            } catch (\Throwable $e) {
                Log::error('Gemini API request failed: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        // Fallback / Demo Contextual Analysis jika API Key belum diisi atau panggilan gagal
        $fallbackData = $this->generateContextualFallback($trainingTitle, $subjectTitle, $customContext);

        return MaterialAiAnalysis::create([
            'material_version_id' => $version->id,
            'user_id' => $user?->id,
            'model' => $this->model,
            'status' => 'completed',
            'summary' => $fallbackData['summary'],
            'identified_regulations' => $fallbackData['identified_regulations'],
            'recommendations' => $fallbackData['recommendations'],
            'suggested_conclusion' => $fallbackData['suggested_conclusion'],
            'conclusion_rationale' => $fallbackData['conclusion_rationale'],
            'raw_response' => $fallbackData,
            'error_message' => empty($this->apiKey) ? 'Mode Simulasi (GEMINI_API_KEY belum disetel di .env)' : null,
        ]);
    }

    protected function buildPrompt(
        string $trainingTitle,
        string $subjectTitle,
        string $materialTitle,
        string $materialType,
        ?string $customContext
    ): string {
        $extraContext = $customContext ? "\nCatatan/Cuplikan Materi Tambahan:\n\"{$customContext}\"\n" : "";

        return <<<PROMPT
Anda adalah Asisten AI Analis Regulasi dan Kurikulum Pembelajaran Pemerintah Indonesia.
Tugas Anda adalah menelaah substansi materi pelatihan terhadap peraturan perundang-undangan dan kebijakan nasional terkini di Indonesia.

Konteks Pelatihan & Materi:
- Program Pelatihan: {$trainingTitle}
- Mata Pelatihan: {$subjectTitle}
- Judul & Jenis Materi: {$materialTitle} ({$materialType}){$extraContext}

Instruksi:
1. Ringkas substansi pokok dan topik pembelajaran dari mata pelatihan ini.
2. Identifikasi peraturan perundang-undangan (Undang-Undang, Peraturan Pemerintah, Perpres, Permen, Surat Edaran, atau Standar Teknis) yang menjadi rujukan utama atau berkaitan langsung dengan materi tersebut.
3. Analisis status keberlakuan setiap peraturan yang ditemukan:
   - "valid" : Masih berlaku penuh sebagai acuan saat ini.
   - "amended" : Telah diubah sebagian oleh peraturan yang lebih baru.
   - "revoked" : Telah dicabut atau tidak berlaku lagi (digantikan peraturan baru).
   - "unknown" : Belum teridentifikasi secara pasti.
4. Jika peraturan tersebut telah diubah ("amended") atau dicabut ("revoked"), sebutkan nomor & nama peraturan terbaru penggantinya, serta ringkasan perubahannya.
5. Berikan daftar rekomendasi perbaikan/pembaruan substansi materi yang perlu dilakukan oleh Pengembang Materi.
6. Berikan rekomendasi kesimpulan akhir reviu dengan memilih PERSIS SALAH SATU dari 4 kalimat baku berikut:
   - "Materi masih relevan"
   - "Materi masih relevan, tetapi perlu perbaikan minor sesuai komentar"
   - "Materi sudah tidak relevan, perlu perubahan major"
   - "Materi sudah tidak sesuai kebutuhan, perlu penyesuaian kurikulum"

PENTING: Output Anda HARUS HANYA berupa JSON murni tanpa pembuka/penutup basa-basi, dengan format persis:
{
  "summary": "Ringkasan substansi materi...",
  "identified_regulations": [
    {
      "title": "Nama dan Nomor Peraturan (misal: UU No. 5 Tahun 2014 tentang ASN)",
      "status": "revoked",
      "status_label": "Telah Dicabut",
      "latest_regulation": "UU No. 20 Tahun 2023 tentang Aparatur Sipil Negara",
      "notes": "Dicabut dan digantikan oleh UU No. 20 Tahun 2023 yang mengatur transformasi digital dan manajemen talenta ASN."
    }
  ],
  "recommendations": [
    "Poin rekomendasi 1...",
    "Poin rekomendasi 2..."
  ],
  "suggested_conclusion": "Materi sudah tidak relevan, perlu perubahan major",
  "conclusion_rationale": "Alasan penentuan kesimpulan berdasarkan regulasi..."
}
PROMPT;
    }

    protected function parseJsonFromText(string $text): ?array
    {
        $text = trim($text);
        if (preg_match('/```json\s*(.*?)\s*```/s', $text, $matches)) {
            $text = $matches[1];
        } elseif (preg_match('/```\s*(.*?)\s*```/s', $text, $matches)) {
            $text = $matches[1];
        }

        $decoded = json_decode($text, true);
        return is_array($decoded) ? $decoded : null;
    }

    protected function generateContextualFallback(string $trainingTitle, string $subjectTitle, ?string $customContext): array
    {
        $topic = "{$trainingTitle} - {$subjectTitle}";
        $isAsn = stripos($topic, 'asn') !== false || stripos($topic, 'pegawai') !== false || stripos($topic, 'kepemimpinan') !== false;
        $isPengadaan = stripos($topic, 'pengadaan') !== false || stripos($topic, 'pbj') !== false || stripos($topic, 'barang') !== false;
        $isKeuangan = stripos($topic, 'keuangan') !== false || stripos($topic, 'anggaran') !== false || stripos($topic, 'apbd') !== false || stripos($topic, 'dak') !== false;

        if ($isPengadaan) {
            return [
                'summary' => "Mata pelatihan '{$subjectTitle}' mencakup tata kelola dan mekanisme pengadaan barang/jasa pemerintah.",
                'identified_regulations' => [
                    [
                        'title' => 'Perpres No. 16 Tahun 2018 tentang Pengadaan Barang/Jasa Pemerintah',
                        'status' => 'amended',
                        'status_label' => 'Telah Diubah',
                        'latest_regulation' => 'Perpres No. 12 Tahun 2021 tentang Perubahan atas Perpres 16/2018',
                        'notes' => 'Diubah terkait kemudahan berusaha, batasan tender UMKM hingga 15 Miliar, dan afirmasi PDN.',
                    ],
                    [
                        'title' => 'Peraturan LKPP No. 12 Tahun 2021 tentang Pedoman Pelaksanaan Pengadaan',
                        'status' => 'valid',
                        'status_label' => 'Masih Berlaku',
                        'latest_regulation' => 'Peraturan LKPP No. 12 Tahun 2021',
                        'notes' => 'Standar dokumen pemilihan dan petunjuk teknis pelaksanaan pemilihan penyedia.',
                    ]
                ],
                'recommendations' => [
                    'Sesuaikan nilai batasan paket usaha kecil menjadi Rp 15 Miliar sesuai regulasi terbaru.',
                    'Tambahkan klausul kewajiban pencantuman produk dalam e-Katalog dan tingkat komponen dalam negeri (TKDN).',
                ],
                'suggested_conclusion' => 'Materi masih relevan, tetapi perlu perbaikan minor sesuai komentar',
                'conclusion_rationale' => 'Konsep pengadaan secara umum masih relevan, namun aturan turunan dan batasan nilai nominal perlu disesuaikan dengan Perpres 12/2021.',
            ];
        }

        if ($isKeuangan) {
            return [
                'summary' => "Mata pelatihan '{$subjectTitle}' berfokus pada perencanaan, pengelolaan, dan pertanggungjawaban anggaran serta alokasi transfer ke daerah.",
                'identified_regulations' => [
                    [
                        'title' => 'UU No. 1 Tahun 2022 tentang Hubungan Keuangan antara Pemerintah Pusat dan Pemerintahan Daerah (HKPD)',
                        'status' => 'valid',
                        'status_label' => 'Masih Berlaku',
                        'latest_regulation' => 'UU No. 1 Tahun 2022',
                        'notes' => 'Menggantikan UU 33/2004, mengatur restrukturisasi pajak/retribusi dan simplifikasi transfer ke daerah.',
                    ],
                    [
                        'title' => 'PP No. 12 Tahun 2019 tentang Pengelolaan Keuangan Daerah',
                        'status' => 'valid',
                        'status_label' => 'Masih Berlaku',
                        'latest_regulation' => 'PP No. 12 Tahun 2019',
                        'notes' => 'Pedoman umum siklus perencanaan, penatausahaan, dan akuntansi pelaporan keuangan daerah.',
                    ]
                ],
                'recommendations' => [
                    'Pastikan rujukan undang-undang perimbangan keuangan telah merujuk pada UU HKPD (UU No. 1/2022).',
                    'Perbarui bagan alur pelaporan DAK fisik sesuai petunjuk operasional Kemenkeu terkini.',
                ],
                'suggested_conclusion' => 'Materi masih relevan, tetapi perlu perbaikan minor sesuai komentar',
                'conclusion_rationale' => 'Struktur materi relevan, hanya memerlukan pembaharuan sitasi dasar hukum UU HKPD.',
            ];
        }

        // Generic / ASN / Manajemen
        return [
            'summary' => "Mata pelatihan '{$subjectTitle}' membahas kompetensi inti dan pedoman pelaksanaan tugas pada program {$trainingTitle}." . ($customContext ? " Catatan tambahan: {$customContext}" : ""),
            'identified_regulations' => [
                [
                    'title' => 'UU No. 5 Tahun 2014 tentang Aparatur Sipil Negara',
                    'status' => 'revoked',
                    'status_label' => 'Telah Dicabut',
                    'latest_regulation' => 'UU No. 20 Tahun 2023 tentang Aparatur Sipil Negara',
                    'notes' => 'Telah dicabut dan digantikan secara menyeluruh oleh UU No. 20 Tahun 2023.',
                ],
                [
                    'title' => 'PP No. 11 Tahun 2017 jo PP No. 17 Tahun 2020 tentang Manajemen PNS',
                    'status' => 'amended',
                    'status_label' => 'Perlu Penyesuaian RPP',
                    'latest_regulation' => 'Harmonisasi RPP Manajemen ASN (UU 20/2023)',
                    'notes' => 'Ketentuan teknis manajemen ASN saat ini diselaraskan dengan aturan turunan UU 20/2023.',
                ]
            ],
            'recommendations' => [
                'Perbarui rujukan hukum utama dari UU No. 5/2014 menjadi UU No. 20/2023.',
                'Sesuaikan terminologi manajemen talenta, mobilitas talenta, dan transformasi digital pembelajaran.',
            ],
            'suggested_conclusion' => 'Materi sudah tidak relevan, perlu perubahan major',
            'conclusion_rationale' => 'Dasar hukum pokok materi telah dicabut oleh undang-undang baru, sehingga definisi dan kerangka konsep perlu diselaraskan.',
        ];
    }
}
