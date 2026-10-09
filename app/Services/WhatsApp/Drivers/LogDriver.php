<?php

namespace App\Services\WhatsApp\Drivers;

use App\Services\WhatsApp\Contracts\WhatsAppGatewayInterface;
use Illuminate\Support\Facades\Log;

class LogDriver implements WhatsAppGatewayInterface
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function sendMessage(string $phone, string $message): bool
    {
        $channel = $this->config['channel'] ?? 'single';

        Log::channel($channel)->info('[WhatsApp:LogDriver] Message Dispatched', [
            'to' => $phone,
            'message' => $message,
            'timestamp' => now()->toIso8601String(),
        ]);

        return true;
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function getName(): string
    {
        return 'Log (Development / Free Simulation)';
    }
}
