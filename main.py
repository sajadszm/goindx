import logging
from telegram.ext import Application

from bot.database import initialize_database
from bot.localization import load_translations
from bot.handlers import register_handlers

# Initialize database and load translations
initialize_database()
load_translations()

# 🔐 اطلاعات بات (برای تست)
TELEGRAM_BOT_TOKEN = "794691559:AAFrnC1mqBbN3n8dJ5Vq6W8O7k5Hczc-QEM"
ADMIN_IDS = [457060017]

logging.basicConfig(
    format="%(asctime)s - %(name)s - %(levelname)s - %(message)s",
    level=logging.INFO
)
logger = logging.getLogger(__name__)

def main() -> None:
    logger.info("Starting bot...")

    application = Application.builder().token(TELEGRAM_BOT_TOKEN).build()

    # Register all handlers
    register_handlers(application)

    logger.info("Bot application created with handlers. Starting polling...")
    application.run_polling()
    logger.info("Bot stopped.")

if __name__ == "__main__":
    main()
