<?php

define('TELEGRAM_BOT_TOKEN', getenv('TELEGRAM_BOT_TOKEN') ?: 'YOUR_TELEGRAM_BOT_TOKEN');
define('TELEGRAM_API_URL', 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/');
define('ADMIN_TELEGRAM_ID', getenv('ADMIN_TELEGRAM_ID') ?: 'YOUR_ADMIN_TELEGRAM_ID'); // Replace with your actual Telegram ID

// Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'telegram_flo_bot');
define('DB_USER', getenv('DB_USER') ?: 'db_user');
define('DB_PASS', getenv('DB_PASS') ?: 'db_password');
define('DB_CHARSET', 'utf8mb4');

// Encryption Configuration
// IMPORTANT: Generate a strong, random key and store it securely.
// You can generate one using: base64_encode(random_bytes(32))
define('ENCRYPTION_KEY', getenv('ENCRYPTION_KEY') ?: 'YOUR_STRONG_ENCRYPTION_KEY_32_BYTES'); // Replace with a real 32-byte key
define('ENCRYPTION_CIPHER', 'aes-256-gcm'); // Recommended cipher

// Zarinpal Configuration (for later use)
define('ZARINPAL_MERCHANT_ID', getenv('ZARINPAL_MERCHANT_ID') ?: '');
define('ZARINPAL_API_URL', 'https://api.zarinpal.com/pg/v4/payment/'); // Or sandbox URL for testing
define('ZARINPAL_CALLBACK_URL', getenv('ZARINPAL_CALLBACK_URL') ?: 'YOUR_BOT_URL/public/callback_zarinpal.php'); // Replace with your actual callback URL

// Bot settings
define('FREE_TRIAL_DAYS', 30);

// Ensure the encryption key is of the correct length for AES-256-GCM
if (ENCRYPTION_CIPHER === 'aes-256-gcm' && ENCRYPTION_KEY !== 'YOUR_STRONG_ENCRYPTION_KEY_32_BYTES' && strlen(base64_decode(ENCRYPTION_KEY)) !== 32) {
    error_log("Warning: ENCRYPTION_KEY is not 32 bytes long for aes-256-gcm. Please generate a new key.");
    // Potentially die or throw an exception in a production environment
    // For now, we'll just log an error.
}

// Timezone
date_default_timezone_set('Asia/Tehran');

// Error reporting (for development)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Base path
define('BASE_PATH', dirname(__DIR__));

// Autoloader
require_once BASE_PATH . '/src/Helpers/autoloader.php';

?>
