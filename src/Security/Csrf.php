<?php

declare(strict_types=1);

namespace LiteAdmin\Security;

/**
 * High-performance, stateless HMAC-SHA256 CSRF protection.
 * Immune to timing attacks and does not require active PHP session locking.
 */
final class Csrf
{
    private string $secret;

    public function __construct(?string $secret = null)
    {
        $this->secret = $secret ?? (string)(getenv('APP_KEY') ?: 'lite-admin-default-secure-key-32chars');
    }

    /**
     * Generate a cryptographically secure, time-limited, signed CSRF token.
     *
     * @param int $ttl Time to live in seconds (default: 2 hours)
     */
    public function generateToken(int $ttl = 7200): string
    {
        $expiry = time() + $ttl;
        $nonce = bin2hex(random_bytes(16));
        $signature = hash_hmac('sha256', "{$expiry}.{$nonce}", $this->secret);

        return rtrim(strtr(base64_encode("{$expiry}.{$nonce}.{$signature}"), '+/', '-_'), '=');
    }

    /**
     * Validate an incoming CSRF token in constant time.
     */
    public function validateToken(?string $token): bool
    {
        if (empty($token)) {
            return false;
        }

        $normalized = strtr($token, '-_', '+/');
        $decoded = base64_decode($normalized, true);
        if ($decoded === false) {
            return false;
        }

        $parts = explode('.', $decoded);
        if (count($parts) !== 3) {
            return false;
        }

        [$expiryStr, $nonce, $signature] = $parts;
        $expiry = (int)$expiryStr;

        // 1. Check expiration
        if ($expiry < time()) {
            return false;
        }

        // 2. Timing-safe signature check
        $expectedSignature = hash_hmac('sha256', "{$expiry}.{$nonce}", $this->secret);

        return hash_equals($expectedSignature, $signature);
    }
}
