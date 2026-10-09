<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Console\Command;

class SendWhatsAppTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:test 
                            {phone : Nomor telepon tujuan (contoh: 081234567890)} 
                            {message? : Pesan teks uji coba} 
                            {--driver= : Driver yang digunakan (log, fonnte, waha, generic)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kirim pesan uji coba notifikasi WhatsApp menggunakan driver modular';

    /**
     * Execute the console command.
     */
    public function handle(WhatsAppService $whatsAppService): int
    {
        $rawPhone = $this->argument('phone');
        $message = $this->argument('message') ?? 'Halo! Ini adalah pesan uji coba dari sistem notifikasi LM Review.';
        $driverName = $this->option('driver') ?? config('whatsapp.default', 'log');

        $this->info("=== Uji Coba WhatsApp Gateway ===");
        $this->line("Driver Aktif    : <comment>{$driverName}</comment>");
        $this->line("Nomor Input     : <comment>{$rawPhone}</comment>");

        $normalized = $whatsAppService->normalizePhone($rawPhone);
        $this->line("Nomor Normalisasi: <comment>{$normalized}</comment>");
        $this->line("Pesan           : <info>{$message}</info>");
        $this->newLine();

        $driver = $whatsAppService->driver($driverName);
        $this->line("Nama Driver     : " . $driver->getName());
        $this->line("Status Driver   : " . ($driver->isConfigured() ? '<info>Terkonfigurasi</info>' : '<error>Belum Terkonfigurasi</error>'));
        $this->newLine();

        $this->info("Mengirim pesan...");
        $success = $whatsAppService->send($rawPhone, $message, $driverName);

        if ($success) {
            $this->info("✓ Berhasil! Pesan telah berhasil diproses oleh driver [{$driverName}].");
            if ($driverName === 'log') {
                $this->comment("Catatan: Driver 'log' mencatat pesan ke storage/logs/laravel.log tanpa mengirim ke jaringan luar.");
            }
            return self::SUCCESS;
        }

        $this->error("✗ Gagal mengirim pesan. Silakan periksa log aplikasi atau konfigurasi .env.");
        return self::FAILURE;
    }
}
