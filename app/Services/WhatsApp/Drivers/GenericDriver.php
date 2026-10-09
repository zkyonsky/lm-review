<?php

namespace App\Services\WhatsApp\Drivers;

use App\Services\WhatsApp\Contracts\WhatsAppGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GenericDriver implements WhatsAppGatewayInterface
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function sendMessage(string $phone, string $message): bool
    {
        if (!$this->isConfigured()) {
            Log::warning('[WhatsApp:GenericDriver] Endpoint is not configured.');
            return false;
        }

        try {
            $headers = ['Accept' => 'application/json'];
            if (!empty($this->config['api_key'])) {
                $headers['Authorization'] = 'Bearer ' . $this->config['api_key'];
            }

            $endpoint = $this->config['endpoint'];
            $method = strtoupper($this->config['method'] ?? 'POST');
            $phoneKey = $this->config['phone_key'] ?? 'phone';
            $messageKey = $this->config['message_key'] ?? 'message';

            $payload = [
                $phoneKey => $phone,
                $messageKey => $message,
            ];

            $request = Http::withHeaders($headers)->timeout(10);
            $response = $method === 'GET' 
                ? $request->get($endpoint, $payload) 
                : $request->post($endpoint, $payload);

            if ($response->successful()) {
                Log::info('[WhatsApp:GenericDriver] Message sent successfully', ['to' => $phone]);
                return true;
            }

            Log::error('[WhatsApp:GenericDriver] Message send failed', [
                'to' => $phone,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::error('[WhatsApp:GenericDriver] Connection error: ' . $e->getMessage(), [
                'to' => $phone,
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
        return 'Generic REST Webhook';
    }
}
