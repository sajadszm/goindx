import logging
import datetime
from telegram import Update, InlineKeyboardButton, InlineKeyboardMarkup
from telegram.ext import ContextTypes, CommandHandler, CallbackQueryHandler # Added CallbackQueryHandler

from bot.database import get_user, update_user_subscription, add_user # Added add_user
from bot.localization import get_text

logger = logging.getLogger(__name__)

PAYMENT_METHOD_PREFIX = "pay_method_"

async def status_command(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    user = update.effective_user
    if not user or not update.message:
        logger.warning(f"User or message missing in status_command: user={user}, message={update.message if hasattr(update, 'message') else 'N/A'}")
        return

    db_user = get_user(user.id)
    if not db_user:
        add_user(user.id) # Add with default lang
        db_user = get_user(user.id) # Try fetching again
        if not db_user: # If still not found, something is wrong with DB
            logger.error(f"User {user.id} not found in DB even after attempting to add in status_command.")
            await update.message.reply_text(get_text('en', 'error_generic'))
            return

    lang = db_user['language']
    status = db_user['subscription_status']
    expiration_date_str = db_user['subscription_expiration_date']
    message_key = 'subscription_status_inactive'
    message_params = {}

    if status == 'active':
        if expiration_date_str:
            try:
                try:
                    expiration_date = datetime.datetime.fromisoformat(expiration_date_str)
                except ValueError:
                    expiration_date = datetime.datetime.strptime(expiration_date_str, '%Y-%m-%d %H:%M:%S')

                if datetime.datetime.now() > expiration_date:
                    update_user_subscription(user.id, 'expired', expiration_date)
                    message_key = 'subscription_status_inactive'
                    # Consider adding: get_text(lang, 'status_expired_on', date=expiration_date.strftime('%Y-%m-%d'))
                else:
                    message_key = 'subscription_status_active'
                    message_params = {'expiration_date': expiration_date.strftime('%Y-%m-%d %H:%M')}
            except ValueError as e:
                logger.error(f"Error parsing expiration_date_str '{expiration_date_str}': {e}")
                message_key = 'error_generic'
        else:
            message_key = 'subscription_status_active'
            message_params = {'expiration_date': get_text(lang, 'never_expires', default="Does not expire")}

    status_text = get_text(lang, message_key, **message_params)
    await update.message.reply_text(status_text)

async def subscribe_command(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    user = update.effective_user
    if not user or not update.message:
        logger.warning(f"User or message missing in subscribe_command: user={user}, message={update.message if hasattr(update, 'message') else 'N/A'}")
        return

    db_user = get_user(user.id)
    if not db_user:
        add_user(user.id) # Add with default lang
        db_user = get_user(user.id) # Try fetching again
        if not db_user: # If still not found, something is wrong with DB
            logger.error(f"User {user.id} not found in DB even after attempting to add in subscribe_command.")
            await update.message.reply_text(get_text('en', 'error_generic'))
            return
    lang = db_user['language']

    keyboard = [
        [InlineKeyboardButton(get_text(lang, "payment_method_paypal", default="PayPal"), callback_data=f"{PAYMENT_METHOD_PREFIX}paypal")],
        [InlineKeyboardButton(get_text(lang, "payment_method_bitcoin", default="Bitcoin"), callback_data=f"{PAYMENT_METHOD_PREFIX}bitcoin")],
        [InlineKeyboardButton(get_text(lang, "payment_method_zarinpal", default="Zarinpal (ریال)"), callback_data=f"{PAYMENT_METHOD_PREFIX}zarinpal")],
    ]
    reply_markup = InlineKeyboardMarkup(keyboard)
    intro_text = get_text(lang, 'subscribe_intro', default="Choose a payment method for subscription:")
    await update.message.reply_text(intro_text, reply_markup=reply_markup)

async def payment_method_callback(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Handles selection of a payment method from /subscribe command."""
    query = update.callback_query
    user = update.effective_user
    if not query or not user:
        logger.warning(f"Query or user missing in payment_method_callback: query={query}, user={user}")
        if query: await query.answer()
        return

    await query.answer() # Answer the callback query first

    db_user = get_user(user.id)
    lang = db_user['language'] if db_user else 'en'

    method = "unknown" # Default
    try:
        method = query.data.split(PAYMENT_METHOD_PREFIX)[1]
    except (IndexError, AttributeError) as e:
        logger.error(f"Error parsing payment method from callback data '{query.data}': {e}")
        await query.edit_message_text(text=get_text(lang, 'error_generic'))
        return

    # For now, these are placeholders. Actual implementation will follow.
    method_name_display = method.capitalize()
    if method == "paypal":
        method_name_display = "PayPal"
    elif method == "bitcoin":
        method_name_display = "Bitcoin"
    elif method == "zarinpal":
        method_name_display = "Zarinpal"

    response_text = get_text(lang, 'payment_method_selected_wip', method_name=method_name_display)

    await query.edit_message_text(text=response_text)

# Note: register_handlers for these new callbacks will be in bot/handlers.py
