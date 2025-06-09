import logging
import os
from dotenv import load_dotenv
from telegram.ext import Application

# Import and initialize database and translations first
from bot.database import initialize_database
from bot.localization import load_translations, get_text # get_text might be used for early error messages if needed

# Initialize database and load translations at startup
initialize_database()
load_translations() # Load translations once when the bot starts

from bot.handlers import register_handlers # Now import handlers

# Load environment variables from .env file
load_dotenv()

TELEGRAM_BOT_TOKEN = os.getenv("794691559:AAFrnC1mqBbN3n8dJ5Vq6W8O7k5Hczc-QEM")
ADMIN_IDS_STR = os.getenv("457060017")
ADMIN_IDS = []
if ADMIN_IDS_STR:
    try:
        ADMIN_IDS = [int(admin_id.strip()) for admin_id in ADMIN_IDS_STR.split(',') if admin_id.strip().isdigit()]
    except ValueError:
        logging.error("Invalid ADMIN_IDS format in .env. Expected comma-separated numbers.")

logging.basicConfig(
    format="%(asctime)s - %(name)s - %(levelname)s - %(message)s", level=logging.INFO
)
logger = logging.getLogger(__name__)

if not TELEGRAM_BOT_TOKEN:
    logger.error("FATAL: TELEGRAM_BOT_TOKEN not found in .env file! Please create or check .env.")
    exit(1)

if not ADMIN_IDS: # This is a warning, not fatal
    logger.warning("WARNING: ADMIN_IDS not found, empty, or invalid in .env file. Admin functionality will be restricted.")

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
