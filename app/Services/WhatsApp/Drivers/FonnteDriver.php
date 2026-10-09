<?php

namespace App\Services\WhatsApp\Drivers;

use App\Services\WhatsApp\Contracts\WhatsAppGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FonnteDriver implements WhatsAppGatewayInterface
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function sendMessage(string $phone, string $message): bool
    {
        if (!$this->isConfigured()) {
            Log::warning('[WhatsApp:FonnteDriver] Token is not configured. Set FONNTE_TOKEN in .env');
            return false;
        }

        try {
            $endpoint = $this->config['endpoint'] ?? 'https://api.fonnte.com/send';

            $response = Http::withHeaders([
                'Authorization' => $this->config['token'],
            ])
            ->timeout(10)
            ->post($endpoint, [
                'target' => $phone,
                'message' => $message,
                'countryCode' => '62',
            ]);

            if ($response->successful()) {
                Log::info('[WhatsApp:FonnteDriver] Message successfully sent', [
                    'to' => $phone,
                    'response' => $response->json(),
                ]);
                return true;
            }

            Log::error('[WhatsApp:FonnteDriver] Failed to send message', [
                'to' => $phone,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::error('[WhatsApp:FonnteDriver] Connection error: ' . $e->getMessage(), [
                'to' => $phone,
            ]);
            return false;
        }
    }

    public function isConfigured(): bool
    {
        return !empty($this->config['token']);
    }

    public function getName(): string
    {
        return 'Fonnte Gateway';
    }
}
