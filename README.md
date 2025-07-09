# Telegram Live Chat for WordPress

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/telegram-live-chat.svg?style=flat-square)](https://wordpress.org/plugins/telegram-live-chat/)
[![License](https://img.shields.io/badge/license-GPLv2%2B-blue.svg?style=flat-square)](https://www.gnu.org/licenses/gpl-2.0.html)
*(Note: Replace badges with actual links/data if/when plugin is on wp.org or has a dedicated site)*

Connect with your website visitors in real-time via Telegram. This plugin adds a customizable live chat widget to your WordPress site, allowing seamless communication between visitors and your support agents on Telegram, with an option for agents to also reply from the WordPress admin panel.

---

## English

### Features

*   **Live Chat Widget**: Customizable floating or embedded chat widget.
*   **Dual Agent Platforms**:
    *   **Telegram Integration**: Agents manage chats directly from their Telegram accounts.
    *   **WordPress Admin Chat Dashboard**: A dedicated interface within WP Admin for agents to view active chats and reply to visitors.
*   **User Roles**: Custom "Chat Agent" role (`tlc_chat_agent`) with specific capabilities to manage chats from WP Admin without full admin rights.
*   **Real-time Communication**: Near real-time message delivery using AJAX polling for both visitor widget and admin dashboard.
*   **WooCommerce Integration**:
    *   Optionally display recent order history for known customers to agents in Telegram notifications.
    *   Optionally display recent order history in the WP Admin Live Chat Dashboard and Chat History views.
*   **Customization**:
    *   Full control over widget colors (header, buttons, messages).
    *   Customizable texts (header title, welcome message, offline message, pre-chat form labels).
    *   Widget position (bottom-right, bottom-left for floating mode).
    *   Chat button icon shape (circle, square).
    *   Option to hide floating widget on desktop or mobile.
    *   Custom CSS support.
*   **Smart Features**:
    *   Automated messages based on time on page or scroll depth.
    *   Page targeting for automated messages.
    *   Session-based throttling for automated messages.
*   **Work Hours & Offline Mode**:
    *   Define business hours for chat availability.
    *   Option to show an offline message or hide the widget outside work hours.
*   **Visitor Information**:
    *   Optional pre-chat form to collect visitor name (required) and email (optional).
    *   Automatic collection of IP, user agent, initial page, referer, and UTM parameters (source, medium, campaign).
    *   Page URL logged with each message.
*   **File Uploads**: Visitors can upload files through the chat widget, which are then sent to agents on Telegram and accessible in chat history. (Configurable: enable/disable, allowed types, max size).
*   **Spam Protection**: Basic rate limiting to prevent message flooding from visitors.
*   **Predefined (Canned) Responses**:
    *   Admin can set up shortcuts and corresponding full messages.
    *   Agents can use these shortcuts in Telegram to send common replies.
*   **Chat History**:
    *   Admins and chat agents can view all chat sessions and message transcripts in the WordPress dashboard.
    *   Search and sort chat sessions by various criteria.
    *   Detailed view shows all collected visitor data, messages, ratings, and WooCommerce order summary if applicable.
*   **User Satisfaction Rating**:
    *   Optional feature for visitors to rate their chat experience (1-5 stars) and leave comments.
    *   Average rating visible in basic analytics.
*   **Basic Analytics**: Dashboard showing total chats, total messages, and average visitor rating.
*   **Developer Friendly**:
    *   JavaScript API (`window.TLC_Chat_API`) for frontend widget control.
    *   Outgoing webhooks for chat events with optional HMAC-SHA256 signature.
    *   REST API (`/tlc/v1/`) for chat data (read-only for sessions/messages, write for admin replies).
*   **Display Control**:
    *   Option for floating widget or manual placement via `[telegram_live_chat_widget]` shortcode.
    *   Meta box on posts/pages to disable the floating widget on specific items.
*   **GDPR & Privacy**:
    *   Integration with WordPress Data Export/Erasure tools.
    *   Admin settings for LocalStorage-based consent helper.
    *   Privacy policy content suggestions.
*   **Internationalization**: Fully translatable with English and basic Persian translations. RTL support for the widget.

### Installation

1.  **Download**: Download the plugin `.zip` file.
2.  **Upload**: In WordPress admin, go to `Plugins` > `Add New` > `Upload Plugin`. Select the zip file and click `Install Now`.
3.  **Activate**: Click `Activate Plugin`.
4.  **Configure**:
    *   Go to `Telegram Chat` > `Settings` for core settings.
    *   Users with "Chat Agent" role (or Admins) can use the `Live Chat` dashboard.

### Configuration

Settings are under `Telegram Chat` > `Settings`.

1.  **Telegram Bot Settings**:
    *   **Bot Token**: **Required**. From BotFather.
    *   **Agent Telegram User IDs**: Comma-separated numeric IDs.
    *   **Group Chat ID (Optional)**: Group/channel ID for notifications. Bot must be admin.

2.  **Integrations** (New Section)
    *   **WooCommerce**:
        *   **Enable WooCommerce Integration**: Check to enable. (Only visible if WooCommerce is active).
        *   **Orders in Telegram Notification**: Show recent order summary in initial Telegram notifications.
        *   **Number of Orders (Telegram)**: How many recent orders to show (1-3).
        *   **Orders in WP Admin Chat Dashboard**: Show recent order summary in the WP Admin Live Chat dashboard.

3.  **Widget Customization**: Colors, texts, display options, pre-chat form, satisfaction rating, display mode, custom CSS.

4.  **Automated Messages**: Configure one automated message (text, trigger, value, page targeting).

5.  **Work Hours & Offline Mode**: Define daily work hours and offline behavior.

6.  **File Upload Settings**: Enable/disable, allowed types, max size.

7.  **Spam Protection**: Enable/disable rate limiting, set threshold/period.

8.  **Predefined Responses**: Set up shortcuts and messages for agents (max 10).

9.  **Webhook Settings**: URLs for chat events, optional webhook secret.

10. **Privacy & Consent (GDPR)**: Require consent, LocalStorage key/value for consent, privacy policy text suggestions.

11. **General Settings**: Data cleanup on uninstall.

**Encryption Key Note**: Message content is NOT encrypted by default. For future potential, define `TLC_ENCRYPTION_KEY` in `wp-config.php`.

### Basic Usage
*(Content largely unchanged, but ensure it's accurate)*

### Shortcode Usage
*(Content unchanged)*

### Developer Features
*(Content unchanged, ensure REST API endpoint for admin replies is noted as writeable)*

---

## فارسی (Persian)

### ویژگی‌ها
*(Add WooCommerce integration to the list)*
*   **ادغام با ووکامرس**:
    *   نمایش اختیاری تاریخچه سفارشات اخیر مشتریان به اپراتورها در اعلان‌های تلگرام.
    *   نمایش اختیاری تاریخچه سفارشات اخیر در داشبورد گفتگوی زنده مدیریت وردپرس و نمای تاریخچه چت.
*(Other features as before)*

### نصب
*(Content unchanged)*

### پیکربندی
*(Add new "Integrations" section and its WooCommerce settings)*
1.  **تنظیمات ربات تلگرام**: ...
2.  **ادغام‌ها** (بخش جدید)
    *   **ووکامرس**:
        *   **فعال‌سازی ادغام ووکامرس**: برای فعال‌سازی علامت بزنید (فقط در صورت فعال بودن ووکامرس نمایش داده می‌شود).
        *   **سفارشات در اعلان تلگرام**: نمایش خلاصه سفارشات اخیر در اعلان‌های اولیه تلگرام به اپراتورها.
        *   **تعداد سفارشات (تلگرام)**: تعداد سفارشات اخیر برای نمایش (1-3).
        *   **سفارشات در داشبورد چت مدیریت وردپرس**: نمایش خلاصه سفارشات اخیر در داشبورد گفتگوی زنده مدیریت وردپرس.
3.  **سفارشی‌سازی ویجت**: ...
*(Continue with other configuration sections, ensuring numbering is correct)*

### راهنمای استفاده پایه
*(Content unchanged)*

### استفاده از کد کوتاه
*(Content unchanged)*

### امکانات توسعه‌دهندگان
*(Content unchanged)*

---

## Changelog

### 0.11.0 (Current Development - Corresponds to end of Phase 11)
*   Added WooCommerce Integration:
    *   Admin settings to enable/configure WooCommerce features.
    *   Linked WooCommerce customer ID to chat sessions for logged-in users.
    *   Display recent order summary in Telegram notifications to agents.
    *   Display recent order summary in the WP Admin Live Chat Dashboard.
    *   Display recent order summary in the single session view of Chat History.
*   Updated README.md with WooCommerce integration details.

*(Previous changelog entries as before)*
### 0.10.0 ...
### 0.9.0 ...
### 0.7.0 ...
### 0.6.0 ...
### 0.5.0 ...
### 0.4.0 ...
### 0.2.0 ...
### 0.1.0 ...

---

## License

This plugin is licensed under the GPLv2 or later.
© 2023 Your Name
