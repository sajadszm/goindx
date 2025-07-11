# Deployment Guide for Persian Couples Telegram Bot

This guide provides instructions for deploying the Persian Couples Telegram Bot on a typical cPanel-based web hosting environment or any server with a LAMP stack.

## 1\. Server Requirements

  * **PHP Version:** PHP 8.0 or higher is recommended for full compatibility. The code has been adapted to work with older versions (like 7.4) by removing modern syntax like `match` and union types.
  * **PHP Extensions:** Ensure the following PHP extensions are enabled:
      * `pdo`
      * `pdo_mysql` (or the driver for your chosen database)
      * `curl` (for Telegram API communication)
      * `openssl` (for encryption)
      * `json` (for handling JSON data)
      * `mbstring` (for multi-byte string operations, important for Persian text)
  * **Database:** MySQL 5.7+ or MariaDB 10.2+.
  * **Web Server:** Apache or Nginx.
  * **HTTPS:** A valid SSL/TLS certificate for your domain is **required** by Telegram for webhooks.

## 2\. Code Deployment

1.  **Upload Files:** Upload all the bot's source code files to your web host, typically into a subdirectory within `public_html` (e.g., `public_html/hamgam_bot/`).
    The structure should look like:
    ```
    your_domain_root/
    ├── hamgam_bot/ (or your chosen directory)
    │   ├── config/
    │   ├── cron/
    │   ├── public/
    │   │   └── index.php  <-- Webhook entry point
    │   ├── src/
    │   └── ... (other root files)
    ```
2.  **Permissions:** Ensure your web server has read access to all files and write access to any log directories if you implement file-based logging. Currently, errors are logged via `error_log` which usually goes to the server's main PHP error log.

## 3\. Database Setup

1.  **Create Database:** Using cPanel's "MySQL Databases" (or similar tool):

      * Create a new database (e.g., `yourcpaneluser_hamgam`).
      * Create a new database user (e.g., `yourcpaneluser_botuser`).
      * Add the user to the database and grant **ALL PRIVILEGES**. Note these credentials.

2.  **Import Table Schemas:** Use phpMyAdmin (accessible from cPanel) or a MySQL client to import the SQL schemas for the necessary tables. Execute the following SQL commands. This schema is the **final and corrected version**, incorporating all bug fixes.

    ```sql
    -- Final and Corrected Database Schema --

    -- users table
    CREATE TABLE `users` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `telegram_id_hash` varchar(64) NOT NULL,
      `encrypted_chat_id` text DEFAULT NULL,
      `user_state` text DEFAULT NULL,
      `encrypted_first_name` text DEFAULT NULL,
      `encrypted_username` text DEFAULT NULL,
      `encrypted_role` text DEFAULT NULL,
      `encrypted_cycle_info` text DEFAULT NULL,
      `partner_telegram_id_hash` varchar(64) DEFAULT NULL,
      `invitation_token` varchar(64) DEFAULT NULL,
      `subscription_status` enum('free_trial','active','expired','none') NOT NULL DEFAULT 'none',
      `trial_ends_at` timestamp NULL DEFAULT NULL,
      `subscription_ends_at` timestamp NULL DEFAULT NULL,
      `preferred_notification_time` time DEFAULT NULL,
      `last_symptom_log_date` datetime DEFAULT NULL,
      `referral_code` varchar(32) DEFAULT NULL,
      `referred_by_user_id` int(11) DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `telegram_id_hash` (`telegram_id_hash`),
      UNIQUE KEY `invitation_token` (`invitation_token`),
      UNIQUE KEY `referral_code` (`referral_code`),
      KEY `idx_partner_telegram_id_hash` (`partner_telegram_id_hash`),
      KEY `idx_subscription_status` (`subscription_status`),
      KEY `fk_referred_by_user` (`referred_by_user_id`),
      CONSTRAINT `users_ibfk_1` FOREIGN KEY (`referred_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- logged_symptoms table (Corrected without faulty unique key)
    CREATE TABLE `logged_symptoms` (
      `id` bigint(20) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL,
      `symptom_date` date NOT NULL,
      `encrypted_symptom_category` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
      `encrypted_symptom_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `idx_user_date` (`user_id`,`symptom_date`),
      CONSTRAINT `logged_symptoms_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Other tables for full functionality --

    CREATE TABLE `app_settings` (
      `setting_key` varchar(100) NOT NULL,
      `setting_value` text DEFAULT NULL,
      PRIMARY KEY (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE `daily_tips` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `tip_text` varchar(280) NOT NULL,
      `target_role` enum('menstruating','partner','both') NOT NULL DEFAULT 'both',
      `target_phase` enum('menstruation','follicular','ovulation','luteal','any') NOT NULL DEFAULT 'any',
      `is_active` tinyint(1) NOT NULL DEFAULT 1,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE `educational_content` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `parent_id` int(11) DEFAULT NULL,
      `sequence_order` int(11) NOT NULL DEFAULT 0,
      `target_role` enum('menstruating','partner','both') NOT NULL,
      `content_topic` varchar(100) NOT NULL,
      `title` varchar(255) DEFAULT NULL,
      `slug` varchar(255) DEFAULT NULL,
      `content_type` enum('text','text_with_image','video_link','external_article') NOT NULL DEFAULT 'text',
      `content_data` text NOT NULL,
      `image_url` varchar(512) DEFAULT NULL,
      `video_url` varchar(512) DEFAULT NULL,
      `source_url` varchar(512) DEFAULT NULL,
      `read_more_link` varchar(512) DEFAULT NULL,
      `cycle_phase_association` enum('menstruation','follicular','ovulation','luteal','any') DEFAULT NULL,
      `symptom_association_keys` text DEFAULT NULL,
      `tags` text DEFAULT NULL,
      `is_tutorial_topic` tinyint(1) NOT NULL DEFAULT 0,
      `is_active` tinyint(1) NOT NULL DEFAULT 1,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE `period_history` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL,
      `period_start_date` date NOT NULL,
      `period_end_date` date DEFAULT NULL,
      `period_length` int(11) DEFAULT NULL,
      `cycle_length` int(11) DEFAULT NULL,
      `logged_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `user_id` (`user_id`),
      CONSTRAINT `period_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE `sent_notifications` (
      `id` bigint(20) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL,
      `notification_type` varchar(50) NOT NULL,
      `cycle_ref_date` date NOT NULL,
      `tip_id` int(11) DEFAULT NULL,
      `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `unique_notification` (`user_id`,`notification_type`,`cycle_ref_date`),
      KEY `idx_tip_id` (`tip_id`),
      CONSTRAINT `sent_notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
      CONSTRAINT `sent_notifications_ibfk_2` FOREIGN KEY (`tip_id`) REFERENCES `daily_tips` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE `subscription_plans` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(255) NOT NULL,
      `description` text DEFAULT NULL,
      `price` decimal(10,2) NOT NULL,
      `duration_months` int(11) NOT NULL,
      `is_active` tinyint(1) NOT NULL DEFAULT 1,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE `support_tickets` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL,
      `subject` varchar(255) DEFAULT NULL,
      `status` enum('open','admin_reply','user_reply','closed') NOT NULL DEFAULT 'open',
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `last_message_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `user_id` (`user_id`),
      CONSTRAINT `support_tickets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE `support_messages` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `ticket_id` int(11) NOT NULL,
      `sender_telegram_id` varchar(255) NOT NULL,
      `sender_role` enum('user','admin') NOT NULL,
      `message_text` text NOT NULL,
      `telegram_message_id` varchar(255) DEFAULT NULL,
      `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `ticket_id` (`ticket_id`),
      CONSTRAINT `support_messages_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE `transactions` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL,
      `plan_id` int(11) NOT NULL,
      `amount` decimal(10,2) NOT NULL,
      `status` enum('pending','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
      `description` varchar(255) DEFAULT NULL,
      `user_email` varchar(255) DEFAULT NULL,
      `user_mobile` varchar(20) DEFAULT NULL,
      `zarinpal_authority` varchar(50) DEFAULT NULL,
      `zarinpal_ref_id` varchar(50) DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `user_id` (`user_id`),
      KEY `plan_id` (`plan_id`),
      CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
      CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE `user_preferences` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL,
      `notify_pre_pms` tinyint(1) NOT NULL DEFAULT 1,
      `notify_period_start` tinyint(1) NOT NULL DEFAULT 1,
      `notify_period_end` tinyint(1) NOT NULL DEFAULT 1,
      `notify_daily_educational_self` tinyint(1) NOT NULL DEFAULT 1,
      `notify_daily_educational_partner` tinyint(1) NOT NULL DEFAULT 1,
      `notifications_snooze_until` timestamp NULL DEFAULT NULL,
      `preferred_content_topics` text DEFAULT NULL,
      `display_fertile_window` tinyint(1) NOT NULL DEFAULT 1,
      `partner_share_cycle_details` enum('full','basic','none') NOT NULL DEFAULT 'full',
      `partner_share_symptoms` enum('full','basic','none') NOT NULL DEFAULT 'none',
      PRIMARY KEY (`id`),
      UNIQUE KEY `user_id` (`user_id`),
      CONSTRAINT `user_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    ```

## 4\. Configuration

Edit the `config/config.php` file:

  * **`TELEGRAM_BOT_TOKEN`**: Set your actual Telegram Bot Token obtained from BotFather.
    ```php
    define('TELEGRAM_BOT_TOKEN', 'YOUR_TELEGRAM_BOT_TOKEN');
    ```
  * **`ADMIN_TELEGRAM_ID`**: Set your numerical Telegram User ID to receive admin notifications.
    ```php
    define('ADMIN_TELEGRAM_ID', 'YOUR_ADMIN_TELEGRAM_ID');
    ```
  * **Database Credentials**: Update `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` with the values from Step 3.1.
    ```php
    define('DB_HOST', 'localhost'); // Usually localhost for cPanel
    define('DB_NAME', 'yourcpaneluser_hamgam');
    define('DB_USER', 'yourcpaneluser_botuser');
    define('DB_PASS', 'your_db_password');
    ```
  * **`ENCRYPTION_KEY`**: **CRITICAL\!** Generate a strong, random 32-byte key, then base64 encode it.
    You can generate one using PHP CLI: `php -r "echo base64_encode(random_bytes(32));"`
    Store this key securely. **Do not lose it, as it's required to decrypt user data.**
    ```php
    define('ENCRYPTION_KEY', 'YOUR_BASE64_ENCODED_32_BYTE_RANDOM_KEY');
    ```
  * **Zarinpal**: `ZARINPAL_MERCHANT_ID` and `ZARINPAL_CALLBACK_URL` will be needed for payment integration. The callback URL should be the public URL to `public/callback_zarinpal.php`.

## 5\. Set Telegram Bot Webhook

The Telegram Bot API needs to know where to send updates (messages from users).

1.  **Determine Webhook URL:** This will be the public URL to your `public/index.php` file.
    Example: `https://yourdomain.com/hamgam_bot/public/index.php`

2.  **Set the Webhook:** You can set this by visiting a specially crafted URL in your browser. Replace `YOUR_BOT_TOKEN` and `YOUR_WEBHOOK_URL` accordingly:

    ```
    https://api.telegram.org/botYOUR_BOT_TOKEN/setWebhook?url=YOUR_WEBHOOK_URL
    ```

    For example:
    `https://api.telegram.org/bot123456:ABC-DEF/setWebhook?url=https://yourdomain.com/hamgam_bot/public/index.php`

    You should see a JSON response like `{"ok":true,"result":true,"description":"Webhook was set"}`.

3.  **Verify Webhook (Optional):** You can check your webhook status:
    `https://api.telegram.org/botYOUR_BOT_TOKEN/getWebhookInfo`

## 6\. Set Up Cron Job for Notifications

Notifications are sent by the `cron/send_notifications.php` script. This needs to be run periodically.

1.  **Access Cron Jobs in cPanel:** Look for an icon or section named "Cron Jobs".
2.  **Add New Cron Job:**
      * **Common Settings:** For notifications based on `preferred_notification_time`, you should run the script **once per hour, at the beginning of the hour**.
        Example: "Once per hour" (Minute: `0`, Hour: `*`, Day: `*`, Month: `*`, Weekday: `*`)
      * **Command:** You need to specify the command to execute the PHP script. The exact command can vary based on your host's PHP CLI setup.
        (Replace `/home/yourcpaneluser/public_html/hamgam_bot/` with the actual server path to your script).
        ```bash
        /usr/local/bin/php /home/yourcpaneluser/public_html/hamgam_bot/cron/send_notifications.php >/dev/null 2>&1
        ```
        (The `>/dev/null 2>&1` part suppresses output; remove it for debugging).

## 7\. Testing

  * Open your bot in Telegram and send `/start`.
  * Test the registration flow, role selection, and other features.
  * Monitor your server's PHP error logs (`error_log` file) for any issues.
