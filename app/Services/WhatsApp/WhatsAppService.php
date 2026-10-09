<?php

namespace App\Services\WhatsApp;

use App\Models\MaterialVersion;
use App\Models\Review;
use App\Models\Subject;
use App\Models\User;
use App\Services\WhatsApp\Contracts\WhatsAppGatewayInterface;
use App\Services\WhatsApp\Drivers\FonnteDriver;
use App\Services\WhatsApp\Drivers\GenericDriver;
use App\Services\WhatsApp\Drivers\LogDriver;
use App\Services\WhatsApp\Drivers\WahaDriver;
use Closure;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class WhatsAppService
{
    /**
     * Instantiated driver instances.
     *
     * @var array<string, WhatsAppGatewayInterface>
     */
    protected array $drivers = [];

    /**
     * Custom driver resolvers registered at runtime.
     *
     * @var array<string, Closure>
     */
    protected array $customCreators = [];

    /**
     * Get or create a driver instance by name.
     */
    public function driver(?string $name = null): WhatsAppGatewayInterface
    {
        $name = $name ?: config('whatsapp.default', 'log');

        if (isset($this->drivers[$name])) {
            return $this->drivers[$name];
        }

        return $this->drivers[$name] = $this->resolveDriver($name);
    }

    /**
     * Register a custom driver creator callback.
     */
    public function extend(string $driver, Closure $callback): self
    {
        $this->customCreators[$driver] = $callback;
        unset($this->drivers[$driver]);
        return $this;
    }

    /**
     * Resolve a driver instance based on configuration.
     */
    protected function resolveDriver(string $name): WhatsAppGatewayInterface
    {
        if (isset($this->customCreators[$name])) {
            return ($this->customCreators[$name])(config("whatsapp.drivers.{$name}", []));
        }

        $config = config("whatsapp.drivers.{$name}", []);

        return match ($name) {
            'log' => new LogDriver($config),
            'fonnte' => new FonnteDriver($config),
            'waha' => new WahaDriver($config),
            'generic' => new GenericDriver($config),
            default => throw new InvalidArgumentException("Driver WhatsApp [{$name}] tidak didukung."),
        };
    }

    /**
     * Normalize an Indonesian / international phone number.
     * Examples:
     * - "0812-3456-7890" => "6281234567890"
     * - "+62 812 3456" => "628123456"
     * - "6281234567890" => "6281234567890"
     */
    public function normalizePhone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        // Keep only digits
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        if (empty($cleaned)) {
            return null;
        }

        // Replace leading 0 with 62 (Indonesian country code)
        if (str_starts_with($cleaned, '0')) {
            $cleaned = '62' . substr($cleaned, 1);
        } elseif (str_starts_with($cleaned, '8')) {
            $cleaned = '62' . $cleaned;
        }

        return $cleaned;
    }

    /**
     * Send or queue a WhatsApp message to a user or direct phone number.
     */
    public function send(User|string $recipient, string $message, ?string $driverName = null): bool
    {
        if (!config('whatsapp.enabled', true)) {
            Log::info('[WhatsAppService] WhatsApp notifications disabled via config.');
            return false;
        }

        $phone = null;
        $recipientName = null;

        if ($recipient instanceof User) {
            $recipientName = $recipient->name;
            $phone = $this->normalizePhone($recipient->phone);

            if (!$phone) {
                Log::info("[WhatsAppService] User {$recipientName} (ID: {$recipient->id}) tidak memiliki nomor WhatsApp.");
                return false;
            }
        } else {
            $phone = $this->normalizePhone($recipient);
            $recipientName = $phone;
        }

        if (!$phone || strlen($phone) < 8) {
            Log::warning("[WhatsAppService] Nomor telepon tidak valid: {$recipientName}");
            return false;
        }

        if (config('whatsapp.queue', false)) {
            \App\Jobs\SendWhatsAppMessageJob::dispatch($phone, $message, $driverName);
            return true;
        }

        return $this->sendSync($phone, $message, $driverName);
    }

    /**
     * Send a WhatsApp message synchronously.
     */
    public function sendSync(string $phone, string $message, ?string $driverName = null): bool
    {
        $normalizedPhone = $this->normalizePhone($phone);
        if (!$normalizedPhone) {
            return false;
        }

        try {
            $driver = $this->driver($driverName);
            return $driver->sendMessage($normalizedPhone, $message);
        } catch (\Throwable $e) {
            Log::error("[WhatsAppService] Gagal mengirim pesan ke {$normalizedPhone}: " . $e->getMessage(), [
                'exception' => $e,
            ]);
            return false;
        }
    }

    /**
     * Send notification when user is assigned to a Subject.
     */
    public function notifySubjectAssignment(User $user, Subject $subject, string $role): bool
    {
        $roleTitle = $role === 'pengembang' ? 'Pengembang Materi' : 'Reviewer';
        $appName = config('app.name', 'LM Review');
        $trainingTitle = $subject->training?->title ?? 'Pelatihan';

        if ($role === 'pengembang') {
            $url = route('developer.subjects.show', $subject->id);
            $message = "Halo *{$user->name}*,\n\n"
                . "Anda telah ditugaskan sebagai *{$roleTitle}* pada mata pelatihan:\n"
                . "📚 *{$subject->title}*\n"
                . "🏛️ *{$trainingTitle}*\n\n"
                . "Silakan akses sistem {$appName} untuk mengelola dan mengunggah materi pembelajaran:\n"
                . "🔗 {$url}\n\n"
                . "Terima kasih.";
        } else {
            $url = route('reviewer.reviews.index');
            $message = "Halo *{$user->name}*,\n\n"
                . "Anda telah ditugaskan sebagai *{$roleTitle}* pada mata pelatihan:\n"
                . "📚 *{$subject->title}*\n"
                . "🏛️ *{$trainingTitle}*\n\n"
                . "Silakan akses daftar review Anda di sistem {$appName}:\n"
                . "🔗 {$url}\n\n"
                . "Terima kasih.";
        }

        return $this->send($user, $message);
    }

    /**
     * Send notification when reviewer is assigned to a specific material version.
     */
    public function notifyReviewAssignment(User $user, MaterialVersion $version, ?Review $review = null): bool
    {
        $material = $version->material;
        $subject = $material?->subject;
        $appName = config('app.name', 'LM Review');

        $url = $review 
            ? route('reviewer.workspace.show', $review->id)
            : route('reviewer.reviews.index');

        $message = "Halo *{$user->name}*,\n\n"
            . "Anda telah ditugaskan untuk mereviu materi:\n"
            . "📖 *{$material?->title}* (Versi {$version->version_number})\n"
            . "📚 Mata Pelatihan: *{$subject?->title}*\n\n"
            . "Silakan buka workspace reviu di sistem {$appName} untuk memberikan catatan dan evaluasi:\n"
            . "🔗 {$url}\n\n"
            . "Terima kasih.";

        return $this->send($user, $message);
    }

    /**
     * Send notification when a new version of material is uploaded and ready for review.
     */
    public function notifyNewVersionUploaded(User $user, MaterialVersion $version, ?Review $review = null): bool
    {
        $material = $version->material;
        $subject = $material?->subject;
        $appName = config('app.name', 'LM Review');

        $url = $review 
            ? route('reviewer.workspace.show', $review->id)
            : route('reviewer.reviews.index');

        $message = "Halo *{$user->name}*,\n\n"
            . "Pemberitahuan Versi Materi Baru:\n"
            . "📖 Materi: *{$material?->title}*\n"
            . "🔖 Versi: *{$version->version_number}*\n"
            . "📚 Mata Pelatihan: *{$subject?->title}*\n\n"
            . "Versi terbaru materi telah diunggah dan siap untuk direviu kembali. Silakan akses workspace reviu:\n"
            . "🔗 {$url}\n\n"
            . "Terima kasih.";

        return $this->send($user, $message);
    }
}
