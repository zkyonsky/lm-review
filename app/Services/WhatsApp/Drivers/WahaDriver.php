<?php

namespace App\Services\WhatsApp\Drivers;

use App\Services\WhatsApp\Contracts\WhatsAppGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WahaDriver implements WhatsAppGatewayInterface
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function sendMessage(string $phone, string $message): bool
    {
        if (!$this->isConfigured()) {
            Log::warning('[WhatsApp:WahaDriver] Endpoint is not configured. Set WAHA_ENDPOINT in .env');
            return false;
        }

        try {
            $chatId = str_ends_with($phone, '@c.us') ? $phone : "{$phone}@c.us";
            $headers = ['Content-Type' => 'application/json'];

            if (!empty($this->config['api_key'])) {
                $headers['X-Api-Key'] = $this->config['api_key'];
            }

            $endpoint = $this->config['endpoint'] ?? 'http://localhost:3000/api/sendText';

            $response = Http::withHeaders($headers)
                ->timeout(10)
                ->post($endpoint, [
                    'chatId' => $chatId,
                    'text' => $message,
                    'session' => $this->config['session'] ?? 'default',
                ]);

            if ($response->successful()) {
                Log::info('[WhatsApp:WahaDriver] Message sent successfully', [
                    'chatId' => $chatId,
                    'response' => $response->json(),
                ]);
                return true;
            }

            Log::error('[WhatsApp:WahaDriver] Failed to send message', [
                'chatId' => $chatId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::error('[WhatsApp:WahaDriver] Connection error: ' . $e->getMessage(), [
                'phone' => $phone,
            ]);
            return false;
        }
    }

    public function isConfigured(): bool
    {
        return !empty($this->config['endpoint']);
    }

    public function getName(): string
    {
        return 'WAHA (Self-Hosted WhatsApp HTTP API)';
    }
}
