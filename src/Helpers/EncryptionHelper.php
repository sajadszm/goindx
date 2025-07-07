<?php

namespace Helpers;

class EncryptionHelper {
    private static $key;
    private static $cipher;

    public static function init() {
        if (!defined('ENCRYPTION_KEY') || !defined('ENCRYPTION_CIPHER')) {
            throw new \Exception("Encryption key or cipher is not defined in config.");
        }
        self::$key = base64_decode(ENCRYPTION_KEY);
        self::$cipher = ENCRYPTION_CIPHER;

        if (self::$cipher === 'aes-256-gcm' && strlen(self::$key) !== 32) {
            // This check is also in config.php, but good to have it here too.
            error_log("Critical: ENCRYPTION_KEY is not 32 bytes long for aes-256-gcm. Data encryption/decryption will fail.");
            throw new \Exception("Invalid encryption key length for AES-256-GCM. Must be 32 bytes (after base64_decode). Current key: " . ENCRYPTION_KEY);
        }
    }

    public static function encrypt($data) {
        self::init(); // Ensure key and cipher are loaded

        if ($data === null) return null;

        $ivlen = openssl_cipher_iv_length(self::$cipher);
        if ($ivlen === false) {
            throw new \Exception("Could not get IV length for cipher " . self::$cipher);
        }
        $iv = openssl_random_pseudo_bytes($ivlen);
        $ciphertext_raw = openssl_encrypt((string)$data, self::$cipher, self::$key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($ciphertext_raw === false) {
            throw new \Exception("Encryption failed: " . openssl_error_string());
        }

        // For GCM, the tag is appended to the ciphertext by some libraries, or returned separately.
        // openssl_encrypt with GCM returns the tag in the $tag parameter. We need to store it.
        // Prepend IV, then tag, then ciphertext.
        return base64_encode($iv . $tag . $ciphertext_raw);
    }

    public static function decrypt($data) {
        self::init(); // Ensure key and cipher are loaded

        if ($data === null) return null;

        $c = base64_decode($data);
        $ivlen = openssl_cipher_iv_length(self::$cipher);
        if ($ivlen === false) {
            throw new \Exception("Could not get IV length for cipher " . self::$cipher);
        }
        $iv = substr($c, 0, $ivlen);

        // For GCM, the tag length is typically 16 bytes.
        $tagLength = 16; // Common for AES-GCM tag
        $tag = substr($c, $ivlen, $tagLength);
        $ciphertext_raw = substr($c, $ivlen + $tagLength);

        if ($iv === false || $tag === false || $ciphertext_raw === false) {
            throw new \Exception("Decryption failed: Invalid data format (IV, tag, or ciphertext missing).");
        }

        $original_plaintext = openssl_decrypt($ciphertext_raw, self::$cipher, self::$key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($original_plaintext === false) {
            // Log detailed error for admin, but don't expose details to user
            error_log("Decryption failed. OpenSSL error: " . openssl_error_string() . ". Data was: " . $data);
            // Potentially throw a more generic error or return a specific value like false/null
            // to indicate failure, allowing the calling code to handle it.
            // For sensitive operations, failing hard might be better.
            throw new \Exception("Decryption failed. Data may be corrupted or key may be incorrect.");
        }
        return $original_plaintext;
    }

    /**
     * Hashes an identifier using a strong hashing algorithm.
     * telegram_id is PII and should not be stored in plain text.
     * @param string $identifier
     * @return string
     */
    public static function hashIdentifier(string $identifier): string {
        // Using SHA256 is a common practice. For added security, especially if the input space is small,
        // consider using a keyed hash (HMAC) with a secret key or a password hashing function like Argon2/bcrypt
        // if there's a concern about rainbow table attacks on the IDs themselves.
        // However, Telegram IDs are large numbers, making rainbow tables less of an immediate concern than for passwords.
        // For this purpose, a simple SHA256 is generally acceptable to prevent direct lookup of IDs.
        // If ENCRYPTION_KEY is available and secure, it could be used as a salt or pepper for HMAC.
        // For now, a simple hash.
        return hash('sha256', $identifier);
    }
}

// Initialize static members if necessary (though accessing them via self::init() is better)
// EncryptionHelper::init(); // Not strictly needed here as methods call init()

?>
