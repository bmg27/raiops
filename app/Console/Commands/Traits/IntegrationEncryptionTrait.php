<?php

namespace App\Console\Commands\Traits;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;

trait IntegrationEncryptionTrait
{
    /**
     * Parse encryption key (handles base64: prefix)
     */
    protected function parseKey(string $key): string
    {
        if (str_starts_with($key, 'base64:')) {
            return base64_decode(substr($key, 7));
        }
        return $key;
    }

    /**
     * Decrypt settings from database using RAI's encryption key
     */
    protected function decryptWithRaiKey(?string $encryptedSettings): array
    {
        if (empty($encryptedSettings)) {
            return [];
        }

        try {
            // Try with RAI's encryption key first
            $raiKey = config('raiops.rai_encryption_key') ?: env('RAI_APP_KEY');
            
            if ($raiKey) {
                try {
                    // Use Laravel's Encrypter with RAI's key
                    $key = $this->parseKey($raiKey);
                    $encrypter = new Encrypter($key, 'AES-256-CBC');
                    $decrypted = $encrypter->decryptString($encryptedSettings);
                    $decoded = json_decode($decrypted, true);
                    if (is_array($decoded)) {
                        return $decoded;
                    }
                } catch (\Exception $e) {
                    // Fall through to RAIOPS key
                }
            }
            
            // Fallback to RAIOPS's key (in case they're the same)
            $decrypted = Crypt::decryptString($encryptedSettings);
            $decoded = json_decode($decrypted, true);
            return is_array($decoded) ? $decoded : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Decrypt access token using RAI's encryption key
     */
    protected function decryptTokenWithRaiKey(?string $encryptedToken): ?string
    {
        if (empty($encryptedToken)) {
            return null;
        }

        try {
            $raiKey = config('raiops.rai_encryption_key') ?: env('RAI_APP_KEY');
            
            if ($raiKey) {
                try {
                    $key = $this->parseKey($raiKey);
                    $encrypter = new Encrypter($key, 'AES-256-CBC');
                    return $encrypter->decryptString($encryptedToken);
                } catch (\Exception $e) {
                    // Fall through to RAIOPS key
                }
            }
            
            return Crypt::decryptString($encryptedToken);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Encrypt settings for database using RAI's encryption key
     */
    protected function encryptWithRaiKey(array $settings): string
    {
        $raiKey = config('raiops.rai_encryption_key') ?: env('RAI_APP_KEY');
        if ($raiKey) {
            try {
                // Use Laravel's Encrypter with RAI's key
                $key = $this->parseKey($raiKey);
                $encrypter = new Encrypter($key, 'AES-256-CBC');
                return $encrypter->encryptString(json_encode($settings));
            } catch (\Exception $e) {
                // Fall through to RAIOPS key
            }
        }
        
        // Fallback to RAIOPS's key
        return Crypt::encryptString(json_encode($settings));
    }

    /**
     * Encrypt access token using RAI's encryption key
     */
    protected function encryptTokenWithRaiKey(string $token): string
    {
        $raiKey = config('raiops.rai_encryption_key') ?: env('RAI_APP_KEY');
        if ($raiKey) {
            try {
                $key = $this->parseKey($raiKey);
                $encrypter = new Encrypter($key, 'AES-256-CBC');
                return $encrypter->encryptString($token);
            } catch (\Exception $e) {
                // Fall through to RAIOPS key
            }
        }
        
        return Crypt::encryptString($token);
    }
}

