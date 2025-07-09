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
    *   Agents can use these shortcuts in Telegram to send common replies. (WP Admin usage can be enhanced later).
*   **Chat History**:
    *   Admins and chat agents can view all chat sessions and message transcripts in the WordPress dashboard.
    *   Search and sort chat sessions by various criteria (token, name, email, IP, status, rating).
    *   Detailed view shows all collected visitor data, messages (including page URL sent from), and ratings.
*   **User Satisfaction Rating**:
    *   Optional feature for visitors to rate their chat experience (1-5 stars) and leave comments after ending a chat.
    *   Average rating visible in basic analytics.
*   **Basic Analytics**: Dashboard showing total chats, total messages, and average visitor rating.
*   **Developer Friendly**:
    *   JavaScript API (`window.TLC_Chat_API`) to control the frontend widget.
    *   Outgoing webhooks for chat events (chat start, new visitor message, new agent message) with optional HMAC-SHA256 signature.
    *   Read-only REST API (`/tlc/v1/`) to fetch chat sessions and messages, respecting user capabilities.
*   **Display Control**:
    *   Option for floating widget or manual placement via `[telegram_live_chat_widget]` shortcode.
    *   Meta box on posts/pages to disable the floating widget on specific items.
*   **GDPR & Privacy**:
    *   Integration with WordPress Data Export tool to export chat data associated with an email.
    *   Integration with WordPress Data Erasure tool to delete chat data associated with an email.
    *   Admin settings for a basic LocalStorage-based consent helper before the widget initializes.
    *   Privacy policy content suggestions provided in admin settings.
*   **Internationalization**: Fully translatable with English and basic Persian translations provided. RTL support for the frontend widget.

### Installation

1.  **Download**: Download the plugin `.zip` file from [Plugin Source/Your Website Here].
2.  **Upload**: In your WordPress admin panel, go to `Plugins` > `Add New` > `Upload Plugin`. Choose the downloaded zip file and click `Install Now`.
3.  **Activate**: Once installed, click `Activate Plugin`.
4.  **Configure**:
    *   Navigate to `Telegram Chat` > `Settings` in your WordPress admin menu to configure the core plugin settings (Telegram Bot, Widget Customization, etc.).
    *   Users assigned the "Chat Agent" role (or Administrators) can access the `Live Chat` dashboard to respond to chats from within WordPress.

### Configuration

All settings are managed within the WordPress admin panel.

**Main Settings (`Telegram Chat` > `Settings`):**

1.  **Telegram Bot Settings**:
    *   **Bot Token**: **Required**. Your Telegram Bot API token from BotFather.
    *   **Agent Telegram User IDs**: Comma-separated numeric Telegram User IDs of agents who will receive messages via Telegram.
    *   **Group Chat ID for Notifications (Optional)**: A Telegram group/channel ID (e.g., `-100...` or `@channelname`) where all new chat notifications and files will also be sent. The bot must be an admin in this group/channel.

2.  **Widget Customization**:
    *   **Colors**: Customize backgrounds and text for header, chat button, visitor/agent messages.
    *   **Texts**: Set widget header title, welcome message (for online hours), and offline message.
    *   **Display Options**: Position (bottom-right/left for floating), icon shape (circle/square), hide on desktop/mobile.
    *   **Enable Pre-chat Form**: If checked, prompts for visitor name (required) and email (optional) before chat starts.
    *   **Enable Satisfaction Rating**: If checked, shows an "End Chat" button for visitors to rate the session.
    *   **Widget Display Mode**: Choose "Floating (Default)" or "Manual via Shortcode `[telegram_live_chat_widget]`".
    *   **Custom CSS**: Add your own CSS rules.

3.  **Automated Messages**:
    *   Configure one automated message with text, trigger (Time on Page, Scroll Depth), trigger value, and page targeting (All Pages, Specific URLs).

4.  **Work Hours & Offline Mode**:
    *   Define daily work hours (Open/Closed, From/To Times) based on your WordPress site's timezone.
    *   **Offline Behavior**: "Show Offline Message" or "Hide Chat Widget Completely" outside work hours.

5.  **File Upload Settings**:
    *   Enable/disable visitor file uploads. Set allowed file types (e.g., `jpg,pdf`) and max file size (MB).

6.  **Spam Protection**:
    *   Enable/disable message rate limiting. Configure message threshold and time period (seconds).

7.  **Predefined Responses**:
    *   Set up shortcuts (e.g., `/faq_refund`) and corresponding full messages for agents to use in Telegram. Max 10.

8.  **Webhook Settings**:
    *   Set URLs for POST notifications on: Chat Start, New Visitor Message, New Agent Message.
    *   **Webhook Secret**: Optional key for HMAC-SHA256 signature (`X-TLC-Signature` header).

9.  **Privacy & Consent (GDPR)**:
    *   **Require Consent for Chat**: If enabled, chat functionality depends on detected consent.
    *   **Consent LocalStorage Key & Value**: Specify the key/value your site's consent mechanism uses in LocalStorage to indicate consent. The widget will look for this. Alternatively, use the `TLC_Chat_API.grantConsentAndShow()` JavaScript function.
    *   **Privacy Policy Suggestions**: Sample text is provided to help you update your site's privacy policy regarding data collected by this plugin.

10. **General Settings**:
    *   **Data Cleanup on Uninstall**: If checked, all plugin data is removed on uninstallation.

**Encryption Key (Important for Conceptual Message Encryption)**:
This plugin includes functions for AES-256 message encryption, but **message content is NOT encrypted in the database by default in this version**. To enable the *potential* for future encryption features or for developers to use these functions, define `TLC_ENCRYPTION_KEY` in your `wp-config.php`:
`define('TLC_ENCRYPTION_KEY', 'your-random-32-byte-secure-key-here');`
The key must be a cryptographically secure random string of 32 bytes.

### Basic Usage

**For Visitors**:
1.  Click the chat widget button (usually floating at the bottom of the page).
2.  If the pre-chat form is enabled, provide your name (and optionally email) and click "Start Chat".
3.  Type your message and press Enter or click "Send".
4.  To upload a file (if enabled), click the paperclip icon.
5.  To end the chat and rate the session (if enabled), click the "End Chat" (X-like symbol) button in the widget header.

**For Agents**:

*   **Via Telegram**:
    1.  You'll receive new messages (and files) from visitors in your Telegram client (or the configured group chat).
    2.  The notification includes visitor details, session ID, and the page URL.
    3.  **To reply, simply reply directly to the bot's message in Telegram.** Your response goes to the correct visitor.
    4.  Use predefined shortcuts (e.g., `/greeting`) as your entire message to send canned responses.
*   **Via WordPress Admin (`Live Chat` Dashboard)**:
    1.  Navigate to the "Live Chat" menu in your WordPress admin panel.
    2.  The dashboard lists active and pending_agent sessions. Click a session to open it.
    3.  View the conversation history. New visitor messages will appear automatically (polling).
    4.  Type your reply in the input box and click "Send Reply" or press Enter.

### Shortcode Usage

If "Widget Display Mode" is set to "Manual via Shortcode", the floating widget is disabled. Use the shortcode `[telegram_live_chat_widget]` in your posts, pages, or widgets to embed the chat interface directly. The embedded widget starts open.

### Developer Features

*   **JavaScript API**: `window.TLC_Chat_API` offers methods like `.show()`, `.hide()`, `.toggle()`, `.isOpen()`, `.isWidgetVisible()`, `.sendMessage(text)`, `.setVisitorInfo({name, email})`, `.triggerAutoMessage(text)`, and `.grantConsentAndShow()`.
*   **Outgoing Webhooks**: POST JSON payloads to specified URLs for events: `chat_start`, `new_visitor_message`, `new_agent_message`. Supports HMAC-SHA256 signature via a shared secret.
*   **REST API (Read-Only)**: Namespace `tlc/v1`. Requires `manage_options` or `read_tlc_chat_sessions` capability.
    *   `GET /sessions`: List sessions (supports `page`, `per_page`, `status`, `orderby`, `order`).
    *   `GET /sessions/<session_id>`: Get a specific session.
    *   `GET /sessions/<session_id>/messages`: List messages for a session (supports `page`, `per_page`, `since_message_id`).
    *   `POST /sessions/<session_id>/reply`: (Write endpoint) Allows users with `reply_tlc_chat_sessions` capability to send replies from WP Admin.

---

## فارسی (Persian)

### ویژگی‌ها

*   **ویجت گفتگوی زنده**: ویجت گفتگوی شناور یا جاسازی شده قابل تنظیم.
*   **پلتفرم دوگانه برای اپراتورها**:
    *   **ادغام با تلگرام**: اپراتورها چت‌ها را مستقیماً از حساب تلگرام خود مدیریت می‌کنند.
    *   **داشبورد چت در مدیریت وردپرس**: یک رابط کاربری اختصاصی در مدیریت وردپرس برای اپراتورها جهت مشاهده چت‌های فعال و پاسخ به بازدیدکنندگان.
*   **نقش‌های کاربری**: نقش کاربری سفارشی "اپراتور چت" (`tlc_chat_agent`) با قابلیت‌های خاص برای مدیریت چت‌ها از مدیریت وردپرس بدون نیاز به دسترسی کامل ادمین.
*   **ارتباط لحظه‌ای**: تحویل پیام نزدیک به لحظه با استفاده از AJAX polling هم برای ویجت بازدیدکننده و هم برای داشبورد مدیریت.
*   **سفارشی‌سازی**: کنترل کامل بر رنگ‌ها، متن‌ها، موقعیت ویجت، شکل آیکون، پنهان‌سازی در دسکتاپ/موبایل، و CSS سفارشی.
*   **ویژگی‌های هوشمند**: پیام‌های خودکار بر اساس زمان در صفحه یا عمق اسکرول، با هدف‌گذاری صفحه و محدودیت نمایش در هر جلسه.
*   **ساعات کاری و حالت آفلاین**: تعریف ساعات کاری و رفتار ویجت (نمایش پیام آفلاین یا پنهان‌سازی) خارج از این ساعات.
*   **اطلاعات بازدیدکننده**: فرم اختیاری پیش از چت (نام و ایمیل)، جمع‌آوری خودکار IP، عامل کاربر، URLهای صفحات، ارجاع‌دهنده، و پارامترهای UTM.
*   **بارگذاری فایل**: امکان بارگذاری فایل توسط بازدیدکنندگان (قابل تنظیم).
*   **محافظت از هرزنامه**: محدودیت نرخ پایه برای پیام‌های بازدیدکنندگان.
*   **پاسخ‌های از پیش تعریف‌شده**: تنظیم میانبرها توسط ادمین برای استفاده اپراتورها در تلگرام.
*   **تاریخچه چت**: مشاهده، جستجو و مرتب‌سازی تمام جلسات و پیام‌ها در مدیریت وردپرس.
*   **امتیاز رضایت کاربر**: ویژگی اختیاری امتیازدهی (1-5 ستاره) و نظرات توسط بازدیدکنندگان.
*   **تجزیه و تحلیل اولیه**: نمایش کل چت‌ها، پیام‌ها و میانگین امتیاز.
*   **امکانات توسعه‌دهندگان**: JavaScript API، وبهوک‌های خروجی، REST API (فقط خواندنی برای داده‌ها، قابل نوشتن برای پاسخ ادمین).
*   **کنترل نمایش**: انتخاب بین ویجت شناور یا کد کوتاه `[telegram_live_chat_widget]`. امکان غیرفعال کردن ویجت شناور در هر برگه/نوشته.
*   **GDPR و حریم خصوصی**: ادغام با ابزارهای برون‌بری/پاکسازی داده وردپرس، راهنمای رضایت کوکی/LocalStorage، پیشنهادات متن سیاست حفظ حریم خصوصی.
*   **بین‌المللی‌سازی**: کاملاً قابل ترجمه با فایل‌های نمونه انگلیسی و فارسی. پشتیبانی از RTL برای ویجت.

### نصب

1.  **دانلود**: فایل `.zip` افزونه را از [منبع افزونه/وب‌سایت شما] دانلود کنید.
2.  **بارگذاری**: در پنل مدیریت وردپرس، به `افزونه‌ها` > `افزودن` > `بارگذاری افزونه` بروید. فایل zip را انتخاب و `نصب` کنید.
3.  **فعال‌سازی**: پس از نصب، روی `فعال کردن افزونه` کلیک کنید.
4.  **پیکربندی**:
    *   به `چت تلگرام` > `تنظیمات` برای پیکربندی هسته افزونه بروید.
    *   کاربران با نقش "اپراتور چت" (یا مدیران کل) می‌توانند به داشبورد `گفتگوی زنده` برای پاسخ به چت‌ها از داخل وردپرس دسترسی پیدا کنند.

### پیکربندی

تنظیمات در بخش `چت تلگرام` > `تنظیمات` مدیریت می‌شوند.

1.  **تنظیمات ربات تلگرام**:
    *   **توکن ربات**: **الزامی**. توکن API ربات تلگرام شما از BotFather.
    *   **شناسه‌های کاربری تلگرام اپراتورها**: لیست شناسه‌های عددی تلگرام اپراتورها، جدا شده با کاما.
    *   **شناسه چت گروهی (اختیاری)**: شناسه گروه/کانال تلگرام (مثلاً `-100...` یا `@channelname`) برای ارسال اعلان‌ها. ربات باید مدیر گروه/کانال باشد.

2.  **سفارشی‌سازی ویجت**: تنظیم رنگ‌ها، متن‌ها (عنوان، خوشامدگویی، آفلاین)، موقعیت، شکل آیکون، پنهان‌سازی، CSS سفارشی، فرم پیش از چت، امتیاز رضایت، و حالت نمایش (شناور/کد کوتاه).

3.  **پیام‌های خودکار**: پیکربندی یک پیام خودکار (فعال/غیرفعال، متن، نوع تریگر، مقدار تریگر، هدف‌گذاری صفحه).

4.  **ساعات کاری و حالت آفلاین**: تعریف ساعات کاری روزانه و رفتار ویجت در ساعات غیرکاری.

5.  **تنظیمات بارگذاری فایل**: فعال/غیرفعال کردن، انواع مجاز، حداکثر اندازه.

6.  **محافظت از هرزنامه**: فعال/غیرفعال کردن محدودیت نرخ پیام، آستانه و دوره زمانی.

7.  **پاسخ‌های از پیش تعریف‌شده**: ایجاد میانبرها و پیام‌های کامل برای استفاده اپراتورها در تلگرام.

8.  **تنظیمات وبهوک**: تنظیم URL برای اعلان‌های رویداد چت و کلید مخفی اختیاری برای امضای HMAC.
9.  **حریم خصوصی و رضایت (GDPR)**: تنظیمات مربوط به نیاز به رضایت، کلید/مقدار LocalStorage برای تشخیص رضایت. شامل پیشنهادات متن برای سیاست حفظ حریم خصوصی شما.

10. **تنظیمات عمومی**: گزینه پاکسازی داده‌ها هنگام حذف نصب.

**کلید رمزگذاری (برای ویژگی مفهومی رمزگذاری پیام)**:
این افزونه شامل توابع رمزگذاری پیام AES-256 است، اما **محتوای پیام در این نسخه به طور پیش‌فرض در پایگاه داده رمزگذاری نمی‌شود**. برای فعال کردن *پتانسیل* ویژگی‌های رمزگذاری آینده یا برای توسعه‌دهندگان جهت استفاده از این توابع، `TLC_ENCRYPTION_KEY` را در فایل `wp-config.php` خود تعریف کنید:
`define('TLC_ENCRYPTION_KEY', 'your-random-32-byte-secure-key-here');`
کلید باید یک رشته تصادفی امن ۳۲ بایتی باشد.

### راهنمای استفاده پایه

**برای بازدیدکنندگان**:
1.  روی دکمه ویجت چت کلیک کنید.
2.  در صورت فعال بودن فرم پیش از چت، اطلاعات خود را وارد کنید.
3.  پیام خود را تایپ و ارسال کنید.
4.  در صورت فعال بودن، فایل بارگذاری کنید.
5.  در صورت فعال بودن، پس از کلیک روی دکمه "پایان چت" (X)، به جلسه امتیاز دهید.

**برای اپراتورها**:
*   **از طریق تلگرام**:
    1.  پیام‌های جدید بازدیدکنندگان را در تلگرام دریافت می‌کنید.
    2.  برای پاسخ، **مستقیماً به پیام ربات در تلگرام پاسخ دهید**.
    3.  از میانبرهای پاسخ‌های آماده (مثلاً `/greeting`) استفاده کنید.
*   **از طریق مدیریت وردپرس (داشبورد `گفتگوی زنده`)**:
    1.  به منوی "گفتگوی زنده" بروید.
    2.  جلسات فعال/در انتظار را مشاهده و یکی را برای پاسخگویی انتخاب کنید.
    3.  تاریخچه پیام‌ها را ببینید و پاسخ خود را تایپ و ارسال کنید.

### استفاده از کد کوتاه
اگر "حالت نمایش ویجت" روی "دستی از طریق کد کوتاه" تنظیم شده باشد، از کد کوتاه `[telegram_live_chat_widget]` برای نمایش ویجت در محتوای خود استفاده کنید.

### امکانات توسعه‌دهندگان
*   **JavaScript API**: `window.TLC_Chat_API` با متدهایی مانند `.show()`, `.hide()`, `.sendMessage(text)` و غیره.
*   **وبهوک‌های خروجی**: اعلان‌های POST JSON برای رویدادهای چت.
*   **REST API (فقط خواندنی برای داده‌ها، قابل نوشتن برای پاسخ ادمین)**: فضای نام `tlc/v1` برای دسترسی به جلسات و پیام‌ها.

---

## Changelog

### 0.10.0 (Current Development - Corresponds to end of Phase 10)
*   Added GDPR Data Export/Erasure integration with WordPress privacy tools.
*   Implemented basic LocalStorage consent helper for widget initialization.
*   Provided Privacy Policy content suggestions in admin settings.
*   Conducted security-focused code review of AJAX/REST handlers.
*   Finalized README.md with GDPR info and a basic changelog structure.

### 0.9.0 (Corresponds to end of Phase 9)
*   Moved README.md to repository root.
*   Created 'tlc_chat_agent' user role with specific chat management capabilities.
*   Added a new top-level 'Live Chat' admin menu page and basic UI for an admin-based chat dashboard.
*   Enhanced REST API for fetching sessions/messages for the admin dashboard, respecting new capabilities.
*   Introduced 'pending_agent' status for new chat sessions.
*   Added `agent_wp_user_id` to messages table for replies from WP admin.
*   Implemented frontend JS for admin dashboard to list sessions, load messages, poll for new visitor messages, and send replies via REST API.

### 0.7.0 (Corresponds to end of Phase 7)
*   Implemented JavaScript API (`window.TLC_Chat_API`) for frontend widget control.
*   Added outgoing webhooks for chat events (chat start, new visitor/agent message) with optional HMAC signing.
*   Created a basic read-only REST API (`/tlc/v1/`) for sessions and messages.
*   Added admin option for manual widget placement via `[telegram_live_chat_widget]` shortcode.
*   Implemented per-page/post disabling of the floating chat widget via a meta box.

### 0.6.0 (Corresponds to end of Phase 6)
*   Generated `.pot` file and sample `en_US.po` and `fa_IR.po` (with partial Persian translation).
*   Implemented RTL CSS for the frontend chat widget.
*   Verified text domain loading.

### 0.5.0 (Corresponds to end of Phase 5)
*   Added collection of visitor data (Referer, UTMs, page URL per message).
*   Implemented optional pre-chat form for name/email.
*   Created a basic Chat Analytics dashboard (total chats, messages, average rating).
*   Added User Satisfaction Rating feature (1-5 stars & comment).
*   Enhanced Chat History admin view with new data, search, and sorting.

### 0.4.0 (Corresponds to end of Phase 4)
*   Added conceptual AES-256 encryption/decryption functions.
*   Implemented file upload support for visitors (admin settings, frontend UI, AJAX, backend handling, Telegram forwarding).
*   Added basic message rate limiting for spam protection.
*   Implemented admin setup for Predefined (Canned) Responses.
*   Enabled agent usage of canned responses via Telegram shortcuts.

### 0.2.0 (Corresponds to end of Phase 2)
*   Refined agent management settings (Agent User IDs, optional Group Chat ID).
*   Implemented WP Cron-based polling for fetching Telegram updates (agent replies).
*   Processed agent replies from Telegram, stored them, and made them available to the visitor widget via AJAX polling.
*   Created a basic Admin Panel for Chat History (list sessions, view messages).

### 0.1.0 (Corresponds to end of Phase 1)
*   Initial plugin scaffolding: directory structure, main file, activation/deactivation.
*   Basic Admin Settings page for Telegram Bot Token and Admin User IDs.
*   Database schema for chat sessions and messages.
*   Basic Telegram Bot API wrapper (`sendMessage`, `getUpdates`).
*   Basic frontend floating chat widget (HTML/CSS/JS) for visitors to send messages.
*   Backend AJAX logic to handle visitor messages, store them, and forward to Telegram.
*   Basic session management using `localStorage`.

---

## License

This plugin is licensed under the GPLv2 or later.
© 2023 Your Name
