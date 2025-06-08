import logging
from telegram import Update, InlineKeyboardButton, InlineKeyboardMarkup
from telegram.ext import CommandHandler, CallbackQueryHandler, ContextTypes, Application, MessageHandler, Filters

from bot.database import add_user, get_user, update_user_language
from bot.localization import get_text
from bot.file_handler import handle_url_message
from bot.subscription_handlers import status_command, subscribe_command, payment_method_callback, PAYMENT_METHOD_PREFIX

# Import admin handlers
from bot.admin_handlers import (
    admin_help_command, admin_view_users_command, admin_view_payments_command,
    admin_view_logs_command, admin_update_user_command, admin_broadcast_command
)

logger = logging.getLogger(__name__)

LANG_CALLBACK_PREFIX = "lang_select_"

async def start_command(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    user = update.effective_user
    if not user or not update.message:
        logger.warning(f"User or message missing in start_command: user={user}, message={update.message if hasattr(update, 'message') else 'N/A'}")
        return
    add_user(user_id=user.id)
    keyboard = [
        [InlineKeyboardButton("English", callback_data=f"{LANG_CALLBACK_PREFIX}en")],
        [InlineKeyboardButton("فارسی (Persian)", callback_data=f"{LANG_CALLBACK_PREFIX}fa")],
        [InlineKeyboardButton("Русский (Russian)", callback_data=f"{LANG_CALLBACK_PREFIX}ru")],
        [InlineKeyboardButton("हिन्दी (Hindi)", callback_data=f"{LANG_CALLBACK_PREFIX}hi")],
    ]
    reply_markup = InlineKeyboardMarkup(keyboard)
    await update.message.reply_text(get_text('en', 'welcome_message'), reply_markup=reply_markup)

async def language_command(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    user = update.effective_user
    if not user or not update.message:
        logger.warning(f"User or message missing in language_command: user={user}, message={update.message if hasattr(update, 'message') else 'N/A'}")
        return
    db_user = get_user(user.id)
    current_lang = 'en' # Default
    if db_user:
        current_lang = db_user['language']
    else: # User not in DB, add them
        add_user(user.id) # User will have 'en' by default from add_user

    keyboard = [
        [InlineKeyboardButton("English", callback_data=f"{LANG_CALLBACK_PREFIX}en")],
        [InlineKeyboardButton("فارسی (Persian)", callback_data=f"{LANG_CALLBACK_PREFIX}fa")],
        [InlineKeyboardButton("Русский (Russian)", callback_data=f"{LANG_CALLBACK_PREFIX}ru")],
        [InlineKeyboardButton("हिन्दी (Hindi)", callback_data=f"{LANG_CALLBACK_PREFIX}hi")],
    ]
    reply_markup = InlineKeyboardMarkup(keyboard)
    await update.message.reply_text(get_text(current_lang, 'language_select_prompt'), reply_markup=reply_markup)

async def language_select_callback(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    query = update.callback_query
    user = update.effective_user
    if not query or not user:
        logger.warning(f"Query or user missing in language_select_callback: query={query}, user={user}")
        if query: await query.answer()
        return

    await query.answer()
    lang_code = 'en' # Default
    try:
        lang_code = query.data.split(LANG_CALLBACK_PREFIX)[1]
    except Exception as e:
        logger.error(f"Error parsing lang code from {query.data}: {e}")
        db_user_temp = get_user(user.id); error_lang = db_user_temp['language'] if db_user_temp else 'en'
        await query.edit_message_text(text=get_text(error_lang, 'error_generic'))
        return

    if update_user_language(user.id, lang_code):
        await query.edit_message_text(text=get_text(lang_code, 'language_selected'))
        await context.bot.send_message(chat_id=user.id, text=get_text(lang_code, 'help_message'))
    else:
        db_user = get_user(user.id); current_lang = db_user['language'] if db_user else 'en'
        await query.edit_message_text(text=get_text(current_lang, 'error_generic'))

async def help_command(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    user = update.effective_user
    if not user or not update.message:
        logger.warning(f"User or message missing in help_command: user={user}, message={update.message if hasattr(update, 'message') else 'N/A'}")
        return

    db_user = get_user(user.id)
    lang_code = 'en' # Default
    if db_user:
        lang_code = db_user['language']
    else: # User not in DB, add them
        add_user(user.id) # User will have 'en' by default

    await update.message.reply_text(get_text(lang_code, 'help_message'))

def register_handlers(application: Application) -> None:
    # Regular user commands
    application.add_handler(CommandHandler("start", start_command))
    application.add_handler(CommandHandler("language", language_command))
    application.add_handler(CommandHandler("help", help_command))
    application.add_handler(CommandHandler("status", status_command))
    application.add_handler(CommandHandler("subscribe", subscribe_command))

    # Admin commands
    application.add_handler(CommandHandler("admin_help", admin_help_command))
    application.add_handler(CommandHandler("admin_view_users", admin_view_users_command))
    application.add_handler(CommandHandler("admin_view_payments", admin_view_payments_command))
    application.add_handler(CommandHandler("admin_view_logs", admin_view_logs_command))
    application.add_handler(CommandHandler("admin_update_user", admin_update_user_command))
    application.add_handler(CommandHandler("admin_broadcast", admin_broadcast_command))

    # Callback Handlers
    application.add_handler(CallbackQueryHandler(language_select_callback, pattern=f"^{LANG_CALLBACK_PREFIX}"))
    application.add_handler(CallbackQueryHandler(payment_method_callback, pattern=f"^{PAYMENT_METHOD_PREFIX}"))

    # Message Handler for URLs
    application.add_handler(MessageHandler(Filters.TEXT & ~Filters.COMMAND, handle_url_message))
    logger.info("All command and message handlers registered.")
