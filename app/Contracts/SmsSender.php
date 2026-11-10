<?php

namespace App\Contracts;

/**
 * Sends a text message. The provider is a setting, not code (specification
 * section 12): each provider is one class implementing this, chosen in
 * AppServiceProvider.
 */
interface SmsSender
{
    /** @param string $to the full number with country code, digits only */
    public function send(string $to, string $message): void;
}
