<?php
/**
 * TLC Encryption Helper
 *
 * Handles encryption and decryption of messages.
 *
 * @since      0.4.0
 * @package    TLC
 * @subpackage TLC/includes
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class TLC_Encryption {

    private const CIPHER_METHOD = 'aes-256-cbc';
    private static $key = null;

    /**
     * Initialize and retrieve the encryption key.
     * Key must be defined in wp-config.php as TLC_ENCRYPTION_KEY.
     * It should be a cryptographically secure random string of appropriate length (e.g., 32 bytes for AES-256).
     */
    private static function get_key() {
        if (self::$key === null) {
            if (defined('TLC_ENCRYPTION_KEY') && !empty(TLC_ENCRYPTION_KEY)) {
                // Ensure the key is the correct length for the cipher.
                // For AES-256, the key should be 256 bits (32 bytes).
                // If the defined key is too short/long, it could be problematic or less secure.
                // Here, we'll use it as is, but in a production system, validation or derivation might be needed.
                self::$key = TLC_ENCRYPTION_KEY;
            } else {
                error_log(TLC_PLUGIN_PREFIX . 'Error: TLC_ENCRYPTION_KEY is not defined in wp-config.php. Encryption/decryption will fail.');
                self::$key = false; // Mark as unavailable
            }
        }
        return self::$key;
    }

    /**
     * Check if encryption is available and configured.
     * @return bool
     */
    public static function is_available() {
        if (!function_exists('openssl_encrypt')) {
            error_log(TLC_PLUGIN_PREFIX . 'OpenSSL extension is not available. Encryption/decryption disabled.');
            return false;
        }
        return self::get_key() !== false;
    }

    /**
     * Encrypts a string.
     *
     * @param string $plaintext The string to encrypt.
     * @return string|false The encrypted string (ciphertext prepended with IV and separator), or false on failure.
     */
    public static function encrypt( $plaintext ) {
        if (!self::is_available()) {
            return $plaintext; // Return plaintext if not available, or handle error differently
        }

        $key = self::get_key();
        $ivlen = openssl_cipher_iv_length(self::CIPHER_METHOD);
        if ($ivlen === false) {
            error_log(TLC_PLUGIN_PREFIX . 'Could not get IV length for cipher: ' . self::CIPHER_METHOD);
            return false;
        }
        $iv = openssl_random_pseudo_bytes($ivlen);
        if ($iv === false) {
             error_log(TLC_PLUGIN_PREFIX . 'Could not generate IV.');
            return false;
        }

        $ciphertext_raw = openssl_encrypt($plaintext, self::CIPHER_METHOD, $key, OPENSSL_RAW_DATA, $iv);
        if ($ciphertext_raw === false) {
            error_log(TLC_PLUGIN_PREFIX . 'openssl_encrypt failed: ' . openssl_error_string());
            return false;
        }

        // Prepend IV to ciphertext for storage, then base64 encode for safe storage/transfer.
        // Using a non-base64 character as a separator is an option, but can be tricky.
        // Simpler to just prepend raw IV then base64 the whole thing.
        return base64_encode($iv . $ciphertext_raw);
    }

    /**
     * Decrypts a string.
     *
     * @param string $ciphertext_base64 The base64 encoded string (IV + ciphertext) to decrypt.
     * @return string|false The decrypted string (plaintext), or false on failure.
     */
    public static function decrypt( $ciphertext_base64 ) {
        if (!self::is_available()) {
            return $ciphertext_base64; // Return as is if not available, or handle error
        }

        $key = self::get_key();
        $decoded_ciphertext = base64_decode($ciphertext_base64, true);
        if ($decoded_ciphertext === false) {
            error_log(TLC_PLUGIN_PREFIX . 'base64_decode failed on ciphertext.');
            return false;
        }

        $ivlen = openssl_cipher_iv_length(self::CIPHER_METHOD);
         if ($ivlen === false) {
            error_log(TLC_PLUGIN_PREFIX . 'Could not get IV length for cipher (decrypt): ' . self::CIPHER_METHOD);
            return false;
        }

        if (mb_strlen($decoded_ciphertext, '8bit') < $ivlen) {
            error_log(TLC_PLUGIN_PREFIX . 'Ciphertext is too short to contain IV.');
            return false;
        }

        $iv = mb_substr($decoded_ciphertext, 0, $ivlen, '8bit');
        $ciphertext_raw = mb_substr($decoded_ciphertext, $ivlen, null, '8bit');

        $plaintext = openssl_decrypt($ciphertext_raw, self::CIPHER_METHOD, $key, OPENSSL_RAW_DATA, $iv);

        if ($plaintext === false) {
            // Don't log the actual ciphertext for security, but log that decryption failed.
            error_log(TLC_PLUGIN_PREFIX . 'openssl_decrypt failed. Possible reasons: incorrect key, corrupted data, or different IV/cipher used for encryption. OpenSSL error: ' . openssl_error_string());
            return false;
        }

        return $plaintext;
    }
}
