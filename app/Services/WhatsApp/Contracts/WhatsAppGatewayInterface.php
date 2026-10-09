<?php

namespace App\Services\WhatsApp\Contracts;

interface WhatsAppGatewayInterface
{
    /**
     * Send a WhatsApp message to the specified recipient phone number.
     *
     * @param string $phone Target phone number in normalized international format (e.g. 6281234567890)
     * @param string $message Text content to be sent
     * @return bool True if message dispatch was successful or accepted
     */
    public function sendMessage(string $phone, string $message): bool;

    /**
     * Determine if this driver has the required credentials/settings configured.
     */
    public function isConfigured(): bool;

    /**
     * Get the human-readable display name of this driver.
     */
    public function getName(): string;
}
