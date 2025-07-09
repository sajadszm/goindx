# Telegram Live Chat for WordPress

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/telegram-live-chat.svg?style=flat-square)](https://wordpress.org/plugins/telegram-live-chat/)
[![License](https://img.shields.io/badge/license-GPLv2%2B-blue.svg?style=flat-square)](https://www.gnu.org/licenses/gpl-2.0.html)
*(Note: Replace badges with actual links/data if/when plugin is on wp.org or has a dedicated site)*

Connect with your website visitors in real-time via Telegram. This plugin adds a customizable live chat widget to your WordPress site, allowing seamless communication between visitors and your support agents on Telegram.

---

## English

### Features

*   **Live Chat Widget**: Floating chat widget for easy visitor access.
*   **Telegram Integration**: Agents manage chats directly from their Telegram accounts.
*   **Real-time Communication**: Instant message delivery (AJAX polling based).
*   **Customization**:
    *   Full control over widget colors (header, buttons, messages).
    *   Customizable texts (header title, welcome message, offline message).
    *   Widget position (bottom-right, bottom-left).
    *   Chat button icon shape (circle, square).
    *   Option to hide widget on desktop or mobile.
    *   Custom CSS support.
*   **Smart Features**:
    *   Automated messages based on time on page or scroll depth.
    *   Page targeting for automated messages.
    *   Session-based throttling for automated messages.
*   **Work Hours & Offline Mode**:
    *   Define business hours for chat availability.
    *   Option to show an offline message or hide the widget outside work hours.
*   **Visitor Information**:
    *   Optional pre-chat form to collect visitor name and email.
    *   Automatic collection of IP, user agent, initial page, referer, and UTM parameters (source, medium, campaign).
    *   Page URL logged with each message.
*   **File Uploads**: Visitors can upload files (images, documents) through the chat widget, which are then sent to agents on Telegram. (Configurable: enable/disable, allowed types, max size).
*   **Spam Protection**: Basic rate limiting to prevent message flooding.
*   **Predefined (Canned) Responses**:
    *   Admin can set up shortcuts and corresponding full messages.
    *   Agents can use these shortcuts in Telegram to send common replies.
*   **Chat History**:
    *   Admins can view all chat sessions and message transcripts in the WordPress dashboard.
    *   Search and sort chat sessions.
    *   Detailed view shows all collected visitor data and messages.
*   **User Satisfaction Rating**:
    *   Optional feature to allow visitors to rate their chat experience (1-5 stars) and leave comments after ending a chat.
    *   Average rating visible in basic analytics.
*   **Basic Analytics**: Dashboard showing total chats, total messages, and average visitor rating.
*   **Developer Friendly**:
    *   JavaScript API to control the widget (show, hide, send messages, etc.).
    *   Outgoing webhooks for chat events (chat start, new visitor message, new agent message).
    *   Read-only REST API to fetch chat sessions and messages.
*   **Display Control**:
    *   Option to use a shortcode `[telegram_live_chat_widget]` for manual placement instead of the default floating widget.
    *   Meta box on posts/pages to disable the floating widget on specific items.
*   **Internationalization**: Fully translatable with English and basic Persian translations provided. RTL support for the widget.

### Installation

1.  **Download**: Download the plugin `.zip` file.
2.  **Upload**: In your WordPress admin panel, go to `Plugins` > `Add New` > `Upload Plugin`. Choose the downloaded zip file and click `Install Now`.
3.  **Activate**: Once installed, click `Activate Plugin`.
4.  **Configure**: Navigate to `Telegram Chat` > `Settings` in your WordPress admin menu to configure the plugin.

### Configuration

The main settings are found under `Telegram Chat` > `Settings`.

1.  **Telegram Bot Settings**:
    *   **Bot Token**: Enter the API token for your Telegram Bot. You get this from BotFather on Telegram. This is essential for the plugin to work.
    *   **Agent Telegram User IDs**: A comma-separated list of numeric Telegram User IDs for your support agents. Messages from website visitors will be forwarded to these users. You can get a User ID by sending `/start` to a bot like `@userinfobot` on Telegram.
    *   **Group Chat ID for Notifications (Optional)**: You can also specify a group chat ID (e.g., `-100123456789`) or a public channel username (e.g., `@yourchannel`) where new chat notifications (and files) will be sent in addition to individual agents. The bot must be a member (and preferably an admin) of this group/channel.

2.  **Widget Customization**:
    *   **Colors**: Set background and text colors for the widget header, chat button, and visitor/agent messages.
    *   **Texts**: Customize the widget header title, the initial welcome message shown to visitors, and the message shown when your support is offline.
    *   **Display**: Choose widget position (bottom-right/left), button icon shape, and options to hide the widget on desktop/mobile. You can also add custom CSS rules.
    *   **Enable Pre-chat Form**: If checked, visitors will be asked for their name (required) and email (optional) before starting a chat.
    *   **Enable Satisfaction Rating**: If checked, an "End Chat" button appears in the widget, allowing visitors to rate the chat and leave comments.
    *   **Widget Display Mode**: Choose between "Floating (Default)" or "Manual via Shortcode `[telegram_live_chat_widget]`".

3.  **Automated Messages**:
    *   Configure one automated message.
    *   **Enable**: Toggle the message on/off.
    *   **Message Text**: The content of the automated message.
    *   **Trigger Type**: `Time on Page` (seconds) or `Scroll Depth` (percentage).
    *   **Trigger Value**: The numeric value for the selected trigger.
    *   **Page Targeting**: `All Pages` or `Specific URL(s)` (provide a comma-separated or line-separated list of URLs).

4.  **Work Hours & Offline Mode**:
    *   Set your availability for each day of the week (Open/Closed, From Time, To Time). Times are based on your WordPress site's timezone (displayed on the settings page).
    *   **Offline Behavior**: Choose to either "Show Offline Message in Widget" or "Hide Chat Widget Completely" when outside of defined work hours.

5.  **File Upload Settings**:
    *   **Enable File Uploads**: Allow visitors to send files.
    *   **Allowed File Types**: Comma-separated list of extensions (e.g., `jpg,pdf,txt`). Leave empty to use WordPress defaults.
    *   **Max File Size (MB)**: Set the maximum upload size.

6.  **Spam Protection**:
    *   **Enable Message Rate Limiting**: Prevent users from sending messages too quickly.
    *   **Rate Limit: Messages**: Max messages allowed.
    *   **Rate Limit: Period (seconds)**: Time window for the message limit.

7.  **Predefined Responses**:
    *   Create shortcuts and corresponding full messages. Agents can type the shortcut in Telegram to send the full message. Max 10 responses.

8.  **Webhook Settings**:
    *   Configure URLs to send POST notifications for events: Chat Start, New Visitor Message, New Agent Message.
    *   **Webhook Secret**: An optional secret key to generate an `X-TLC-Signature` header (HMAC-SHA256 of the JSON payload) for verifying webhook authenticity.

9.  **General Settings**:
    *   **Data Cleanup on Uninstall**: If checked, all plugin settings, chat history, and custom tables will be removed when the plugin is uninstalled.

**Encryption Key (Important for Message Encryption - Conceptual Feature)**:
For the (currently conceptual) message encryption feature to work, you would need to define `TLC_ENCRYPTION_KEY` in your `wp-config.php` file:
`define('TLC_ENCRYPTION_KEY', 'your-random-32-byte-secure-key-here');`
The key should be a cryptographically secure random string, 32 bytes long for AES-256. *Note: Message content is NOT currently encrypted by default in the database with this version.*

### Basic Usage

**For Visitors**:
1.  Click on the chat widget button on the website.
2.  If the pre-chat form is enabled, fill in your name (and optionally email) and click "Start Chat".
3.  Type your message in the input field and press Enter or click "Send".
4.  If file uploads are enabled, click the paperclip icon to select and send a file.
5.  If satisfaction ratings are enabled, click the "End Chat" (X) button in the header to rate the session.

**For Agents (Replying via Telegram)**:
1.  You will receive new messages from visitors directly in your Telegram client (or the configured group chat).
2.  The message will include the visitor's message, session ID, page URL, and other collected visitor info.
3.  To reply, simply **reply directly to the bot's message** in Telegram. Your reply will be sent back to the correct visitor on the website.
4.  **Canned Responses**: If predefined responses are set up, type the exact shortcut (e.g., `/greeting`) as your reply in Telegram, and the system will replace it with the full canned message.

### Shortcode Usage

If you've set "Widget Display Mode" to "Manual via Shortcode", the floating widget will not appear automatically. Instead, you can place the chat widget anywhere in your content (posts, pages, widgets that process shortcodes) using:
`[telegram_live_chat_widget]`

The embedded widget will appear "open" by default and will not have the floating button or the header close (X) button.

### Developer Features

*   **JavaScript API**: Control the widget from your theme's or other plugin's JavaScript.
    *   `window.TLC_Chat_API.show()`
    *   `window.TLC_Chat_API.hide()`
    *   `window.TLC_Chat_API.toggle()`
    *   `window.TLC_Chat_API.isOpen()` - Checks if main chat area is open.
    *   `window.TLC_Chat_API.isWidgetVisible()` - Checks if any part of widget UI is active.
    *   `window.TLC_Chat_API.sendMessage(text)`
    *   `window.TLC_Chat_API.setVisitorInfo({name: 'John', email: 'john@example.com'})`
    *   `window.TLC_Chat_API.triggerAutoMessage("Hello from API!")`
*   **Outgoing Webhooks**: Receive real-time notifications about chat events to integrate with other systems (CRMs, analytics, etc.). Configure URLs in admin settings. Payloads are JSON.
*   **REST API (Read-Only)**: Access chat session and message data programmatically. Requires `manage_options` capability.
    *   `GET /wp-json/tlc/v1/sessions`
    *   `GET /wp-json/tlc/v1/sessions/<session_id>`
    *   `GET /wp-json/tlc/v1/sessions/<session_id>/messages`

---

## فارسی (Persian)

### ویژگی‌ها

*   **ویجت گفتگوی زنده**: ویجت شناور برای دسترسی آسان بازدیدکنندگان.
*   **ادغام با تلگرام**: اپراتورها چت‌ها را مستقیماً از حساب تلگرام خود مدیریت می‌کنند.
*   **ارتباط لحظه‌ای**: تحویل فوری پیام (بر اساس AJAX polling).
*   **سفارشی‌سازی**:
    *   کنترل کامل بر رنگ‌های ویجت (سربرگ، دکمه‌ها، پیام‌ها).
    *   متن‌های قابل تنظیم (عنوان سربرگ، پیام خوشامدگویی، پیام آفلاین).
    *   موقعیت ویجت (پایین-راست، پایین-چپ).
    *   شکل آیکون دکمه چت (دایره، مربع).
    *   گزینه پنهان کردن ویجت در دسکتاپ یا موبایل.
    *   پشتیبانی از CSS سفارشی.
*   **ویژگی‌های هوشمند**:
    *   پیام‌های خودکار بر اساس زمان حضور در صفحه یا عمق اسکرول.
    *   هدف‌گذاری صفحه برای پیام‌های خودکار.
    *   محدودیت نمایش پیام خودکار در هر جلسه.
*   **ساعات کاری و حالت آفلاین**:
    *   تعریف ساعات کاری برای در دسترس بودن چت.
    *   گزینه نمایش پیام آفلاین یا پنهان کردن ویجت خارج از ساعات کاری.
*   **اطلاعات بازدیدکننده**:
    *   فرم اختیاری پیش از چت برای جمع‌آوری نام و ایمیل بازدیدکننده.
    *   جمع‌آوری خودکار IP، عامل کاربر، صفحه اولیه، ارجاع‌دهنده و پارامترهای UTM (منبع، رسانه، کمپین).
    *   URL صفحه با هر پیام ثبت می‌شود.
*   **بارگذاری فایل**: بازدیدکنندگان می‌توانند فایل‌ها (تصاویر، اسناد) را از طریق ویجت چت بارگذاری کنند که سپس برای اپراتورها در تلگرام ارسال می‌شود. (قابل تنظیم: فعال/غیرفعال، انواع مجاز، حداکثر اندازه).
*   **محافظت از هرزنامه**: محدودیت نرخ پایه برای جلوگیری از ارسال بیش از حد پیام.
*   **پاسخ‌های از پیش تعریف‌شده (آماده)**:
    *   ادمین می‌تواند میانبرها و پیام‌های کامل مربوطه را تنظیم کند.
    *   اپراتورها می‌توانند از این میانبرها در تلگرام برای ارسال پاسخ‌های رایج استفاده کنند.
*   **تاریخچه چت**:
    *   ادمین‌ها می‌توانند تمام جلسات چت و رونوشت پیام‌ها را در داشبورد وردپرس مشاهده کنند.
    *   جستجو و مرتب‌سازی جلسات چت.
    *   نمای دقیق تمام داده‌های جمع‌آوری‌شده بازدیدکننده و پیام‌ها را نشان می‌دهد.
*   **امتیاز رضایت کاربر**:
    *   ویژگی اختیاری برای اجازه دادن به بازدیدکنندگان برای امتیاز دادن به تجربه چت خود (1-5 ستاره) و گذاشتن نظرات پس از پایان چت.
    *   میانگین امتیاز در تجزیه و تحلیل اولیه قابل مشاهده است.
*   **تجزیه و تحلیل اولیه**: داشبورد نمایش‌دهنده کل چت‌ها، کل پیام‌ها و میانگین امتیاز بازدیدکنندگان.
*   **دوستانه برای توسعه‌دهندگان**:
    *   JavaScript API برای کنترل ویجت (نمایش، پنهان کردن، ارسال پیام و غیره).
    *   وبهوک‌های خروجی برای رویدادهای چت (شروع چت، پیام جدید بازدیدکننده، پیام جدید اپراتور).
    *   REST API فقط خواندنی برای واکشی داده‌های چت و پیام‌ها.
*   **کنترل نمایش**:
    *   گزینه استفاده از کد کوتاه `[telegram_live_chat_widget]` برای قرار دادن دستی ویجت به جای ویجت شناور پیش‌فرض.
    *   متاباکس در نوشته‌ها/برگه‌ها برای غیرفعال کردن ویجت شناور در موارد خاص.
*   **بین‌المللی‌سازی**: کاملاً قابل ترجمه با ترجمه‌های انگلیسی و فارسی پایه ارائه شده است. پشتیبانی از RTL برای ویجت.

### نصب

1.  **دانلود**: فایل `.zip` افزونه را دانلود کنید.
2.  **بارگذاری**: در پنل مدیریت وردپرس خود، به `افزونه‌ها` > `افزودن` > `بارگذاری افزونه` بروید. فایل zip دانلود شده را انتخاب کرده و روی `نصب` کلیک کنید.
3.  **فعال‌سازی**: پس از نصب، روی `فعال کردن افزونه` کلیک کنید.
4.  **پیکربندی**: برای پیکربندی افزونه به `چت تلگرام` > `تنظیمات` در منوی مدیریت وردپرس خود بروید.

### پیکربندی

تنظیمات اصلی در بخش `چت تلگرام` > `تنظیمات` یافت می‌شوند.

1.  **تنظیمات ربات تلگرام**:
    *   **توکن ربات**: توکن API ربات تلگرام خود را وارد کنید. این را از BotFather در تلگرام دریافت می‌کنید. این برای کارکرد افزونه ضروری است.
    *   **شناسه‌های کاربری تلگرام اپراتورها**: لیستی از شناسه‌های کاربری عددی تلگرام برای اپراتورهای پشتیبانی شما، جدا شده با کاما. پیام‌های بازدیدکنندگان وب‌سایت به این کاربران ارسال می‌شود. می‌توانید شناسه کاربری را با ارسال `/start` به رباتی مانند `@userinfobot` در تلگرام دریافت کنید.
    *   **شناسه چت گروهی برای اعلان‌ها (اختیاری)**: همچنین می‌توانید شناسه چت گروهی (مثلاً `-100123456789`) یا نام کاربری کانال عمومی (مثلاً `@yourchannel`) را مشخص کنید که اعلان‌های چت جدید (و فایل‌ها) علاوه بر اپراتورهای فردی به آنجا نیز ارسال شود. ربات باید عضو (و ترجیحاً مدیر) این گروه/کانال باشد.

2.  **سفارشی‌سازی ویجت**:
    *   **رنگ‌ها**: رنگ‌های پس‌زمینه و متن را برای سربرگ ویجت، دکمه چت و پیام‌های بازدیدکننده/اپراتور تنظیم کنید.
    *   **متن‌ها**: عنوان سربرگ ویجت، پیام خوشامدگویی اولیه که به بازدیدکنندگان نشان داده می‌شود و پیامی که هنگام آفلاین بودن پشتیبانی شما نشان داده می‌شود را سفارشی کنید.
    *   **نمایش**: موقعیت ویجت (پایین-راست/چپ)، شکل آیکون دکمه، و گزینه‌هایی برای پنهان کردن ویجت در دسکتاپ/موبایل را انتخاب کنید. همچنین می‌توانید قوانین CSS سفارشی اضافه کنید.
    *   **فعال کردن فرم پیش از چت**: اگر علامت زده شود، از بازدیدکنندگان قبل از شروع چت نام (الزامی) و ایمیل (اختیاری) آنها پرسیده می‌شود.
    *   **فعال کردن امتیاز رضایت**: اگر علامت زده شود، دکمه "پایان چت" در ویجت ظاهر می‌شود که به کاربران امکان می‌دهد به چت امتیاز دهند و نظر بگذارند.
    *   **حالت نمایش ویجت**: بین "شناور (پیش‌فرض)" یا "دستی از طریق کد کوتاه `[telegram_live_chat_widget]`" انتخاب کنید.

3.  **پیام‌های خودکار**:
    *   یک پیام خودکار را پیکربندی کنید.
    *   **فعال کردن**: پیام را روشن/خاموش کنید.
    *   **متن پیام**: محتوای پیام خودکار.
    *   **نوع تریگر**: `زمان حضور در صفحه` (ثانیه) یا `عمق اسکرول` (درصد).
    *   **مقدار تریگر**: مقدار عددی برای تریگر انتخاب شده.
    *   **هدف‌گذاری صفحه**: `همه صفحات` یا `URL(های) خاص` (لیستی از URLها را با کاما یا در خطوط جداگانه وارد کنید).

4.  **ساعات کاری و حالت آفلاین**:
    *   در دسترس بودن خود را برای هر روز هفته (باز/بسته، از ساعت، تا ساعت) تنظیم کنید. زمان‌ها بر اساس منطقه زمانی سایت وردپرس شما هستند (در صفحه تنظیمات نمایش داده می‌شود).
    *   **رفتار آفلاین**: انتخاب کنید که ویجت چت هنگام خارج از ساعات کاری "پیام آفلاین را در ویجت نشان دهد" یا "ویجت چت را کاملاً پنهان کند".

5.  **تنظیمات بارگذاری فایل**:
    *   **فعال کردن بارگذاری فایل**: به بازدیدکنندگان اجازه ارسال فایل بدهید.
    *   **انواع فایل مجاز**: لیست پسوندهای فایل مجاز جدا شده با کاما (مثلاً `jpg,pdf,txt`). برای اجازه دادن به تمام انواع مجاز توسط وردپرس، خالی بگذارید.
    *   **حداکثر اندازه فایل (مگابایت)**: حداکثر اندازه بارگذاری را تنظیم کنید.

6.  **محافظت از هرزنامه**:
    *   **فعال کردن محدودیت نرخ پیام**: از ارسال بیش از حد سریع پیام توسط کاربران جلوگیری کنید.
    *   **محدودیت نرخ: پیام‌ها**: حداکثر تعداد پیام‌های مجاز.
    *   **محدودیت نرخ: دوره (ثانیه)**: پنجره زمانی برای محدودیت پیام.

7.  **پاسخ‌های از پیش تعریف‌شده**:
    *   میانبرها و پیام‌های کامل مربوطه را ایجاد کنید. اپراتورها می‌توانند میانبر را در تلگرام تایپ کنند تا پیام کامل ارسال شود. حداکثر 10 پاسخ.

8.  **تنظیمات وبهوک**:
    *   URLهایی را برای دریافت اعلان‌ها (وبهوک‌ها) برای رویدادهای چت پیکربندی کنید: شروع چت، پیام جدید بازدیدکننده، پیام جدید اپراتور.
    *   **کلید مخفی وبهوک**: یک کلید مخفی اختیاری برای تولید هدر `X-TLC-Signature` (HMAC-SHA256 از محتوای JSON) برای تأیید اعتبار وبهوک.

9.  **تنظیمات عمومی**:
    *   **پاکسازی داده‌ها هنگام حذف نصب**: اگر علامت زده شود، تمام تنظیمات افزونه، تاریخچه چت و جداول سفارشی هنگام حذف نصب افزونه حذف می‌شوند.

**کلید رمزگذاری (مهم برای رمزگذاری پیام - ویژگی مفهومی)**:
برای اینکه ویژگی رمزگذاری پیام (در حال حاضر مفهومی) کار کند، باید `TLC_ENCRYPTION_KEY` را در فایل `wp-config.php` خود تعریف کنید:
`define('TLC_ENCRYPTION_KEY', 'your-random-32-byte-secure-key-here');`
کلید باید یک رشته تصادفی امن از نظر رمزنگاری و به طول 32 بایت برای AES-256 باشد. *توجه: محتوای پیام در حال حاضر به طور پیش‌فرض در پایگاه داده با این نسخه رمزگذاری نمی‌شود.*

### راهنمای استفاده پایه

**برای بازدیدکنندگان**:
1.  روی دکمه ویجت چت در وب‌سایت کلیک کنید.
2.  اگر فرم پیش از چت فعال باشد، نام خود (و به صورت اختیاری ایمیل) را وارد کرده و روی "شروع گفتگو" کلیک کنید.
3.  پیام خود را در قسمت ورودی تایپ کرده و Enter را فشار دهید یا روی "ارسال" کلیک کنید.
4.  اگر بارگذاری فایل فعال باشد، روی آیکون گیره کاغذ کلیک کنید تا فایلی را انتخاب و ارسال کنید.
5.  اگر امتیازدهی رضایت فعال باشد، روی دکمه "پایان چت" (X) در سربرگ کلیک کنید تا به جلسه امتیاز دهید.

**برای اپراتورها (پاسخ از طریق تلگرام)**:
1.  شما پیام‌های جدید از بازدیدکنندگان را مستقیماً در کلاینت تلگرام خود (یا چت گروهی پیکربندی شده) دریافت خواهید کرد.
2.  پیام شامل پیام بازدیدکننده، شناسه جلسه، URL صفحه و سایر اطلاعات جمع‌آوری شده بازدیدکننده خواهد بود.
3.  برای پاسخ، به سادگی **مستقیماً به پیام ربات** در تلگرام پاسخ دهید. پاسخ شما برای بازدیدکننده صحیح در وب‌سایت ارسال می‌شود.
4.  **پاسخ‌های آماده**: اگر پاسخ‌های از پیش تعریف‌شده تنظیم شده باشند، میانبر دقیق (مثلاً `/greeting`) را به عنوان پاسخ خود در تلگرام تایپ کنید، و سیستم آن را با پیام کامل آماده جایگزین می‌کند.

### استفاده از کد کوتاه

اگر "حالت نمایش ویجت" را روی "دستی از طریق کد کوتاه" تنظیم کرده باشید، ویجت شناور به طور خودکار ظاهر نمی‌شود. در عوض، می‌توانید ویجت چت را در هر کجای محتوای خود (نوشته‌ها، برگه‌ها، ابزارک‌هایی که کدهای کوتاه را پردازش می‌کنند) با استفاده از کد زیر قرار دهید:
`[telegram_live_chat_widget]`

ویجت جاسازی شده به طور پیش‌فرض "باز" ظاهر می‌شود و دکمه شناور یا دکمه بستن (X) سربرگ خود را نخواهد داشت.

### امکانات توسعه‌دهندگان

*   **JavaScript API**: ویجت را از طریق جاوا اسکریپت قالب یا سایر افزونه‌های خود کنترل کنید.
    *   `window.TLC_Chat_API.show()`
    *   `window.TLC_Chat_API.hide()`
    *   `window.TLC_Chat_API.toggle()`
    *   `window.TLC_Chat_API.isOpen()` - بررسی می‌کند که آیا ناحیه اصلی چت باز است یا خیر.
    *   `window.TLC_Chat_API.isWidgetVisible()` - بررسی می‌کند که آیا هر بخشی از UI ویجت فعال است یا خیر.
    *   `window.TLC_Chat_API.sendMessage(text)`
    *   `window.TLC_Chat_API.setVisitorInfo({name: 'John', email: 'john@example.com'})`
    *   `window.TLC_Chat_API.triggerAutoMessage("سلام از API!")`
*   **وبهوک‌های خروجی**: اعلان‌های لحظه‌ای درباره رویدادهای چت را برای ادغام با سایر سیستم‌ها (CRMها، تجزیه و تحلیل و غیره) دریافت کنید. URLها را در تنظیمات ادمین پیکربندی کنید. محتواها JSON هستند.
*   **REST API (فقط خواندنی)**: به داده‌های جلسه چت و پیام‌ها به صورت برنامه‌نویسی دسترسی پیدا کنید. نیاز به قابلیت `manage_options` دارد.
    *   `GET /wp-json/tlc/v1/sessions`
    *   `GET /wp-json/tlc/v1/sessions/<session_id>`
    *   `GET /wp-json/tlc/v1/sessions/<session_id>/messages`

---

## Changelog

*(Placeholder for future versions)*

### 0.7.0 (Current Development)
*   Added Developer Tools: JS API, Webhooks, REST API.
*   Added Shortcode for manual widget placement.
*   Added Per-Page/Post disabling of floating widget.

*(Previous phase summaries would go here)*

---

## License

This plugin is licensed under the GPLv2 or later.
© 2023 Your Name
