# Optimized Trend Convergence EA

This document provides instructions on how to install, configure, and efficiently use the Optimized Trend Convergence Expert Advisor for MetaTrader 5.

---

## English Guide

### 1. Introduction

The Optimized Trend Convergence EA is a fully automated trading robot that implements a trend-following strategy combined with a momentum filter. It is designed to identify high-probability entry points, manage trades with a dynamic exit strategy, and apply strict risk management rules.

### 2. Features

*   **Trend Detection:** Uses a dual Exponential Moving Average (EMA) crossover system.
*   **Momentum Filter:** Utilizes the Relative Strength Index (RSI) to confirm signals and avoid false entries.
*   **Automatic Lot Sizing:** Calculates trade volume based on a fixed percentage of your account balance.
*   **Dynamic Exit Strategy:** Manages trades with a calculated Stop Loss, a Take Profit based on a Risk-to-Reward ratio, a breakeven function, and a trailing stop.
*   **Alert System:** Provides on-screen, sound, and email notifications for new signals.
*   **Fully Customizable:** All parameters are available as external inputs.

### 3. Installation Instructions

1.  **Open the Data Folder:** In your MetaTrader 5 terminal, click on `File` in the top menu and then select `Open Data Folder`.
2.  **Navigate to the Experts Folder:** From the Data Folder, navigate to the following directory: `MQL5/Experts/`.
3.  **Copy the EA File:** Copy the `OptimizedTrendConvergenceEA.mq5` file into this `Experts` directory.
4.  **Compile the EA:**
    *   Go back to your MetaTrader 5 terminal.
    *   In the **Navigator** panel (usually on the left), right-click on **Expert Advisors** and select **Refresh**.
    *   You should now see `OptimizedTrendConvergenceEA` in the list. Double-click it to open the source code in **MetaEditor**.
    *   In MetaEditor, click the **Compile** button (or press F7). If there are no errors, you are ready to use the EA.
5.  **Verify:** The EA should now appear in the Navigator panel without a grey icon, indicating it's compiled and ready.

### 4. How to Use

1.  **Attach to a Chart:** Click and drag the `OptimizedTrendConvergenceEA` from the Navigator panel onto the chart and timeframe you wish to trade.
2.  **Configure Parameters:** A window will pop up. Go to the **Inputs** tab to configure all the EA's parameters to your preference. The default values are set to the optimal configuration found during research.
3.  **Enable Algo Trading:** Ensure the **"Allow Algo Trading"** button in the main toolbar of your MT5 terminal is enabled (it should be green).
4.  **Confirm:** Click **OK**. If everything is set up correctly, you will see a smiling face icon on the top right of your chart.

### 5. Recommendations for Efficient Use

*   **Backtest First:** Before running the EA on a live account, use the **Strategy Tester** (`View -> Strategy Tester`) in MetaTrader 5 to backtest it on historical data. This will help you understand its performance characteristics on different currency pairs and timeframes.
*   **Optimize Parameters:** For best results, consider optimizing the input parameters (e.g., `EMA_Fast_Period`, `RSI_Period`) for the specific financial instrument and timeframe you intend to trade.
*   **Start with a Demo Account:** Always run the EA on a demo account for a period to observe its live performance without risking real money.
*   **Use a VPS:** For 24/7 operation and to ensure the EA never misses a trade due to your computer being off or internet disconnects, it is highly recommended to run it on a Virtual Private Server (VPS).

***

## (Persian) راهنمای فارسی

### ۱. معرفی

اکسپرت ادوایزر Optimized Trend Convergence یک ربات معامله‌گر تمام اتوماتیک است که یک استراتژی روند محور را با یک فیلتر مومنتوم ترکیب می‌کند. این اکسپرت برای شناسایی نقاط ورود با احتمال موفقیت بالا، مدیریت معاملات با استراتژی خروج پویا و اعمال قوانین سخت‌گیرانه مدیریت ریسک طراحی شده است.

### ۲. ویژگی‌ها

*   **تشخیص روند:** استفاده از سیستم کراس اوور دو میانگین متحرک نمایی (EMA).
*   **فیلتر مومنتوم:** بهره‌گیری از شاخص قدرت نسبی (RSI) برای تأیید سیگنال‌ها و جلوگیری از ورودهای کاذب.
*   **حجم لات خودکار:** محاسبه حجم معامله بر اساس درصدی ثابت از موجودی حساب شما.
*   **استراتژی خروج پویا:** مدیریت معاملات با حد ضرر محاسبه‌شده، حد سود مبتنی بر نسبت ریسک به ریوارد، قابلیت ریسک-فری کردن (breakeven) و حد ضرر متحرک (trailing stop).
*   **سیستم هشدار:** ارائه هشدارهای روی صفحه، صوتی و ایمیلی برای سیگنال‌های جدید.
*   **قابلیت سفارشی‌سازی کامل:** تمام پارامترها به عنوان ورودی‌های خارجی در دسترس هستند.

### ۳. دستورالعمل نصب

۱.  **باز کردن پوشه داده:** در ترمینال متاتریدر ۵، روی منوی `File` (فایل) کلیک کرده و سپس `Open Data Folder` (باز کردن پوشه داده) را انتخاب کنید.
۲.  **رفتن به پوشه اکسپرت‌ها:** از پوشه داده، به مسیر زیر بروید: `MQL5/Experts/`.
3.  **کپی کردن فایل اکسپرت:** فایل `OptimizedTrendConvergenceEA.mq5` را در این پوشه (`Experts`) کپی کنید.
۴.  **کامپایل کردن اکسپرت:**
    *   به ترمینال متاتریدر ۵ بازگردید.
    *   در پنل **Navigator** (معمولاً در سمت چپ)، روی **Expert Advisors** راست‌کلیک کرده و **Refresh** (بازخوانی) را انتخاب کنید.
    *   اکنون باید `OptimizedTrendConvergenceEA` را در لیست ببینید. روی آن دوبار کلیک کنید تا کد منبع در **MetaEditor** باز شود.
    *   در MetaEditor، روی دکمه **Compile** کلیک کنید (یا کلید F7 را فشار دهید). اگر خطایی وجود نداشته باشد، اکسپرت آماده استفاده است.
۵.  **تأیید:** آیکون اکسپرت در پنل Navigator دیگر نباید خاکستری باشد، که نشان‌دهنده کامپایل موفق و آمادگی آن است.

### ۴. نحوه استفاده

۱.  **اتصال به نمودار:** اکسپرت `OptimizedTrendConvergenceEA` را از پنل Navigator بکشید و روی نمودار و تایم‌فریم مورد نظر خود رها کنید.
۲.  **پیکربندی پارامترها:** پنجره‌ای باز خواهد شد. به تب **Inputs** بروید تا تمام پارامترهای اکسپرت را مطابق با ترجیحات خود تنظیم کنید. مقادیر پیش‌فرض بر اساس تنظیمات بهینه یافت‌شده در تحقیقات تنظیم شده‌اند.
۳.  **فعال‌سازی معاملات الگوریتمی:** اطمینان حاصل کنید که دکمه **"Allow Algo Trading"** در نوار ابزار اصلی ترمینال MT5 شما فعال (سبز رنگ) باشد.
۴.  **تأیید:** روی **OK** کلیک کنید. اگر همه چیز به درستی تنظیم شده باشد، یک آیکون چهره خندان در گوشه سمت راست بالای نمودار خود خواهید دید.

### ۵. توصیه‌هایی برای استفاده بهینه

*   **ابتدا بک‌تست بگیرید:** قبل از اجرای اکسپرت روی یک حساب واقعی، از **Strategy Tester** (از منوی `View -> Strategy Tester`) در متاتریدر ۵ برای بک‌تست گرفتن روی داده‌های تاریخی استفاده کنید. این کار به شما کمک می‌کند تا عملکرد آن را روی جفت‌ارزها و تایم‌فریم‌های مختلف درک کنید.
*   **بهینه‌سازی پارامترها:** برای کسب بهترین نتایج، پارامترهای ورودی (مانند دوره‌های EMA و سطوح RSI) را برای ابزار مالی و تایم‌فریم خاصی که قصد معامله دارید، بهینه‌سازی کنید.
*   **با حساب دمو شروع کنید:** همیشه اکسپرت را برای مدتی روی یک حساب دمو اجرا کنید تا عملکرد زنده آن را بدون ریسک کردن پول واقعی مشاهده نمایید.
*   **از VPS استفاده کنید:** برای اجرای ۲۴ ساعته و اطمینان از اینکه اکسپرت به دلیل خاموش بودن کامپیوتر یا قطعی اینترنت هیچ معامله‌ای را از دست نمی‌دهد، اکیداً توصیه می‌شود که آن را روی یک سرور مجازی خصوصی (VPS) اجرا کنید.
