# Deployment Guide for Persian Couples Telegram Bot

This guide provides instructions for deploying the Persian Couples Telegram Bot on a typical cPanel-based web hosting environment or any server with a LAMP stack.

## 1. Server Requirements

*   **PHP Version:** PHP 8.0 or higher.
*   **PHP Extensions:** Ensure the following PHP extensions are enabled:
    *   `pdo`
    *   `pdo_mysql` (or the driver for your chosen database)
    *   `curl` (for Telegram API communication)
    *   `openssl` (for encryption)
    *   `json` (for handling JSON data)
    *   `mbstring` (for multi-byte string operations, important for Persian text)
    *   `date` (typically enabled by default, for timezone and date functions)
*   **Database:** MySQL 5.7+ or MariaDB 10.2+.
*   **Web Server:** Apache or Nginx (or any server that can serve PHP files).
*   **HTTPS:** A valid SSL/TLS certificate for your domain is **required** by Telegram for webhooks.

## 2. Code Deployment

1.  **Upload Files:** Upload all the bot's source code files to your web host, typically into a subdirectory within `public_html` (e.g., `public_html/flo_bot/`).
    The structure should look like:
    ```
    your_domain_root/
    ├── flo_bot/ (or your chosen directory)
    │   ├── config/
    │   ├── cron/
    │   ├── public/
    │   │   └── index.php  <-- Webhook entry point
    │   ├── src/
    │   ├── vendor/ (if using Composer, though current setup is PSR-4 via custom autoloader)
    │   ├── AGENTS.md
    │   ├── DEPLOYMENT.md (this file)
    │   └── ... (other root files if any)
    ```
2.  **Permissions:** Ensure your web server has read access to all files and write access to any log directories if you implement file-based logging (currently, errors are logged via `error_log` which usually goes to the server's PHP error log).

## 3. Database Setup

1.  **Create Database:** Using cPanel's "MySQL Databases" (or similar tool):
    *   Create a new database (e.g., `yourcpaneluser_flobot`).
    *   Create a new database user (e.g., `yourcpaneluser_flo_usr`).
    *   Add the user to the database and grant **ALL PRIVILEGES**. Note these credentials.
2.  **Import Table Schemas:** Use phpMyAdmin (accessible from cPanel) or a MySQL client to import the SQL schemas for the necessary tables. Execute the following SQL commands:

    ```sql
    -- users table
    CREATE TABLE `users` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `telegram_id_hash` VARCHAR(64) NOT NULL UNIQUE COMMENT 'SHA-256 hash of the user''s Telegram ID',
      `encrypted_chat_id` TEXT NULL COMMENT 'Encrypted Telegram chat ID for sending messages',
      `user_state` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Stores temporary state, e.g., awaiting_support_message',
      `encrypted_first_name` TEXT NULL COMMENT 'Encrypted first name of the user',
      `encrypted_username` TEXT NULL COMMENT 'Encrypted Telegram username (if available)',
      `encrypted_role` TEXT NULL COMMENT 'Encrypted user role (e.g., menstruating, partner, prefer_not_to_say)',
      `encrypted_cycle_info` TEXT NULL COMMENT 'Encrypted JSON string or serialized data for cycle tracking',
      `partner_telegram_id_hash` VARCHAR(64) NULL COMMENT 'SHA-256 hash of the linked partner''s Telegram ID',
      `invitation_token` VARCHAR(64) NULL UNIQUE COMMENT 'Unique token for inviting a partner',
      `subscription_status` ENUM('free_trial', 'active', 'expired', 'none') NOT NULL DEFAULT 'none' COMMENT 'User''s current subscription status',
      `trial_ends_at` TIMESTAMP NULL COMMENT 'Timestamp when the free trial ends',
      `subscription_ends_at` TIMESTAMP NULL COMMENT 'Timestamp when the current paid subscription ends',
      `preferred_notification_time` TIME NULL COMMENT 'User''s preferred time for daily notifications (e.g., 10:00:00)',
      `last_symptom_log_date` DATE NULL DEFAULT NULL COMMENT 'Date of the last symptom log entry',
      `referral_code` VARCHAR(32) NULL UNIQUE COMMENT 'Unique referral code for this user',
      `referred_by_user_id` INT NULL COMMENT 'ID of the user who referred this user',
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX `idx_telegram_id_hash` (`telegram_id_hash`),
      INDEX `idx_partner_telegram_id_hash` (`partner_telegram_id_hash`),
      INDEX `idx_referral_code` (`referral_code`),
      INDEX `idx_subscription_status` (`subscription_status`),
      CONSTRAINT `fk_referred_by_user` FOREIGN KEY (`referred_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- logged_symptoms table
    CREATE TABLE `logged_symptoms` (
      `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
      `user_id` INT NOT NULL,
      `symptom_date` DATE NOT NULL,
      `encrypted_symptom_category` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Encrypted category name',
      `encrypted_symptom_name` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Encrypted symptom name',
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX `idx_user_date` (`user_id`, `symptom_date`),
      UNIQUE KEY `unique_symptom_log` (`user_id`, `symptom_date`, `encrypted_symptom_category`(64), `encrypted_symptom_name`(64)),
      FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- educational_content table
    CREATE TABLE `educational_content` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `target_role` ENUM('menstruating', 'partner', 'both') NOT NULL,
      `category` VARCHAR(100) NOT NULL COMMENT 'e.g., emotional_support, physical_care, communication, nutrition, cycle_info',
      `title` VARCHAR(255) NULL COMMENT 'Optional: Encrypted title',
      `content_type` ENUM('text', 'text_with_image') NOT NULL DEFAULT 'text',
      `content_data` TEXT NOT NULL COMMENT 'Encrypted main text content',
      `image_url` VARCHAR(512) NULL COMMENT 'Optional URL for an image',
      `read_more_link` VARCHAR(512) NULL COMMENT 'Optional external link',
      `cycle_phase_association` ENUM('menstruation', 'follicular', 'ovulation', 'luteal', 'any') NULL COMMENT 'For phase-specific tips, any means not phase specific',
      `symptom_association_keys` TEXT NULL COMMENT 'Encrypted JSON array of symptom keys like ["mood_irritable", "aches_cramps"]',
      `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX `idx_target_role_category` (`target_role`, `category`),
      INDEX `idx_cycle_phase` (`cycle_phase_association`),
      INDEX `idx_is_active` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ```

## 4. Configuration

Edit the `config/config.php` file:

*   **`TELEGRAM_BOT_TOKEN`**: Set your actual Telegram Bot Token obtained from BotFather.
    ```php
    define('TELEGRAM_BOT_TOKEN', 'YOUR_TELEGRAM_BOT_TOKEN');
    ```
*   **`ADMIN_TELEGRAM_ID`**: Set your numerical Telegram User ID to receive admin notifications (like support messages).
    ```php
    define('ADMIN_TELEGRAM_ID', 'YOUR_ADMIN_TELEGRAM_ID');
    ```
*   **Database Credentials**: Update `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` with the values from Step 3.1.
    ```php
    define('DB_HOST', 'localhost'); // Usually localhost for cPanel
    define('DB_NAME', 'yourcpaneluser_flobot');
    define('DB_USER', 'yourcpaneluser_flo_usr');
    define('DB_PASS', 'your_db_password');
    ```
*   **`ENCRYPTION_KEY`**: **CRITICAL!** Generate a strong, random 32-byte key, then base64 encode it.
    You can generate one using PHP CLI: `php -r "echo base64_encode(random_bytes(32)) . \"\n\";"`
    Store this key securely. **Do not lose it, as it's required to decrypt user data.**
    ```php
    define('ENCRYPTION_KEY', 'YOUR_BASE64_ENCODED_32_BYTE_RANDOM_KEY');
    ```
*   **Zarinpal (Optional, for later)**: `ZARINPAL_MERCHANT_ID` and `ZARINPAL_CALLBACK_URL` will be needed when payment integration is complete. The callback URL should be the public URL to your bot's Zarinpal callback handler (e.g., `https://yourdomain.com/flo_bot/public/callback_zarinpal.php`).

## 5. Set Telegram Bot Webhook

The Telegram Bot API needs to know where to send updates (messages from users).

1.  **Determine Webhook URL:** This will be the public URL to your `public/index.php` file.
    Example: `https://yourdomain.com/flo_bot/public/index.php`
2.  **Set the Webhook:** You can set this by visiting a specially crafted URL in your browser or using a tool like `curl`. Replace `YOUR_BOT_TOKEN` and `YOUR_WEBHOOK_URL` accordingly:
    ```
    https://api.telegram.org/botYOUR_BOT_TOKEN/setWebhook?url=YOUR_WEBHOOK_URL
    ```
    For example:
    `https://api.telegram.org/bot123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11/setWebhook?url=https://yourdomain.com/flo_bot/public/index.php`

    You should see a JSON response like `{"ok":true,"result":true,"description":"Webhook was set"}`.
3.  **Verify Webhook (Optional):** You can check your webhook status:
    `https://api.telegram.org/botYOUR_BOT_TOKEN/getWebhookInfo`

## 6. Set Up Cron Job for Notifications

Notifications (daily tips, reminders, etc.) are sent by the `cron/send_notifications.php` script. This needs to be run periodically.

1.  **Access Cron Jobs in cPanel:** Look for an icon or section named "Cron Jobs".
2.  **Add New Cron Job:**
    *   **Common Settings (or select from dropdowns):** Choose how often you want it to run. For notifications based on `preferred_notification_time` (hourly), you should run it **once per hour, at the beginning of the hour**.
        Example: "Once per hour" (Minute: `0`, Hour: `*`, Day: `*`, Month: `*`, Weekday: `*`)
    *   **Command:** You need to specify the command to execute the PHP script. The exact command can vary based on your host's PHP CLI setup. Common forms:
        *   `/usr/local/bin/php /home/yourcpaneluser/public_html/flo_bot/cron/send_notifications.php`
        *   `php /home/yourcpaneluser/public_html/flo_bot/cron/send_notifications.php`
        (Replace `/home/yourcpaneluser/public_html/flo_bot/` with the actual server path to your script).
        You can find the correct path to PHP and your script in cPanel's file manager or by asking your host.
    *   **Output (Optional):** You can choose to have cron job output emailed to you or saved to a file to monitor for errors, or discard it (`>/dev/null 2>&1` at the end of the command). It's good to monitor initially.

    Example command if your cPanel username is `myuser` and bot is in `public_html/flo_bot`:
    ` /usr/local/bin/php /home/myuser/public_html/flo_bot/cron/send_notifications.php >/dev/null 2>&1 `
    (The `>/dev/null 2>&1` part suppresses output; remove it for debugging).

## 7. Testing

*   Open your bot in Telegram and send `/start`.
*   Test the registration flow, role selection, and other features.
*   Monitor your server's PHP error logs and the cron job output (if not suppressed) for any issues.

---

This guide should provide a solid starting point for deploying the bot. Specific paths and commands might vary slightly depending on the hosting provider's cPanel configuration.
