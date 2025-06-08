import logging
import os
import functools # For the decorator
import datetime
from telegram import Update
from telegram.ext import ContextTypes, CommandHandler

from bot.database import (
    get_all_users, get_user, update_user_subscription,
    add_admin_log, get_all_payments, get_admin_logs # Import all needed DB functions
)
from bot.localization import get_text
# from utils import format_file_size # Not used in this version

logger = logging.getLogger(__name__)

# Load Admin IDs from environment variable
ADMIN_IDS_STR = os.getenv("ADMIN_IDS", "")
ADMIN_IDS = []
if ADMIN_IDS_STR:
    try:
        ADMIN_IDS = [int(admin_id.strip()) for admin_id in ADMIN_IDS_STR.split(',') if admin_id.strip().isdigit()]
        if ADMIN_IDS: # Log only if there are actual IDs
            logger.info(f"Admin IDs loaded: {ADMIN_IDS}")
    except ValueError:
        logger.error("Invalid ADMIN_IDS format in .env for admin_handlers. Expected comma-separated numbers.")
if not ADMIN_IDS: # This check is after parsing attempt
    logger.warning("No Admin IDs configured or successfully loaded. Admin panel will be inaccessible.")

def admin_check(func):
    """Decorator to check if the user is an admin."""
    @functools.wraps(func)
    async def wrapper(update: Update, context: ContextTypes.DEFAULT_TYPE, *args, **kwargs):
        user = update.effective_user
        if not user:
            logger.warning(f"No effective_user for admin command {func.__name__}")
            return

        db_user_for_lang = get_user(user.id)
        lang = db_user_for_lang['language'] if db_user_for_lang else 'en'

        if user.id not in ADMIN_IDS:
            logger.warning(f"User {user.id} ({user.username if user.username else 'N/A'}) attempted unauthorized admin command: {func.__name__}")
            if update.message: # Check if update.message exists to reply
                await update.message.reply_text(get_text(lang, 'admin_access_denied', default="You are not authorized to use this command."))
            return

        return await func(update, context, *args, **kwargs)
    return wrapper

@admin_check
async def admin_help_command(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Displays help message for admin commands."""
    user = update.effective_user # Already checked by decorator, but good for clarity
    if not user or not update.message : return

    db_user = get_user(user.id)
    lang = db_user['language'] if db_user else 'en'

    help_text = get_text(lang, 'admin_help_text', default=(
        "👑 *Admin Commands* 👑\n"
        "/admin_help - Show this help message\n"
        "/admin_view_users - View all users\n"
        "/admin_view_payments - View payment logs (WIP)\n"
        "/admin_view_logs - View admin action logs (WIP)\n"
        "/admin_update_user <user_id> <status (active/inactive)> [duration_days] - Update user subscription (WIP)\n"
        "/admin_broadcast <message> - Send a message to all users (WIP)"
    ))
    await update.message.reply_text(help_text, parse_mode='Markdown')
    if user: add_admin_log(admin_id=user.id, action='admin_help_command_used')

@admin_check
async def admin_view_users_command(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Displays a list of all users and their subscription status."""
    admin_user = update.effective_user
    if not admin_user or not update.message : return

    db_admin_user = get_user(admin_user.id)
    lang = db_admin_user['language'] if db_admin_user else 'en'

    all_db_users = get_all_users()
    if not all_db_users:
        await update.message.reply_text(get_text(lang, 'admin_no_users_found', default="No users found in the database."))
        return

    message_parts = [get_text(lang, 'admin_view_users_header', default="👥 *All Users List* ({count} total):").format(count=len(all_db_users))]

    for db_user_row in all_db_users:
        user_id = db_user_row['user_id']
        user_lang_code = db_user_row['language'] # Renamed to avoid conflict with outer 'lang'
        sub_status = db_user_row['subscription_status']
        exp_date_str = db_user_row['subscription_expiration_date']

        exp_date_display = "N/A"
        if exp_date_str:
            try:
                exp_dt = datetime.datetime.fromisoformat(exp_date_str.split('.')[0]) # Handle potential microseconds
                exp_date_display = exp_dt.strftime('%Y-%m-%d')
                if sub_status == 'active' and datetime.datetime.now() > exp_dt:
                    sub_status = f"expired (was active, needs update from {exp_date_display})"
            except ValueError:
                exp_date_display = "Invalid Date"

        user_info = get_text(lang, 'admin_user_entry_format', default=(
            "ID: `{user_id}` | Lang: `{user_lang_code}` | Sub: `{sub_status}` | Expires: `{exp_date_display}`"
        )).format(user_id=user_id, user_lang_code=user_lang_code, sub_status=sub_status, exp_date_display=exp_date_display)
        message_parts.append(user_info)

    full_message = "\n".join(message_parts)

    if len(full_message) > 4096:
        # Basic chunking if message is too long
        current_chunk = ""
        for part in message_parts:
            if len(current_chunk) + len(part) + 1 > 4096:
                await update.message.reply_text(current_chunk, parse_mode='Markdown')
                current_chunk = part
            else:
                if current_chunk: current_chunk += "\n"
                current_chunk += part
        if current_chunk: # Send the last part
            await update.message.reply_text(current_chunk, parse_mode='Markdown')
    else:
        await update.message.reply_text(full_message, parse_mode='Markdown')

    if admin_user: add_admin_log(admin_id=admin_user.id, action='admin_view_users')

@admin_check
async def admin_view_payments_command(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    admin_user = update.effective_user;
    if not admin_user or not update.message : return
    lang = get_user(admin_user.id)['language'] if get_user(admin_user.id) else 'en'
    await update.message.reply_text(get_text(lang, 'command_under_construction', default="This command is under construction."))
    if admin_user: add_admin_log(admin_id=admin_user.id, action='admin_view_payments_placeholder')

@admin_check
async def admin_view_logs_command(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    admin_user = update.effective_user;
    if not admin_user or not update.message : return
    lang = get_user(admin_user.id)['language'] if get_user(admin_user.id) else 'en'
    await update.message.reply_text(get_text(lang, 'command_under_construction', default="This command is under construction."))
    if admin_user: add_admin_log(admin_id=admin_user.id, action='admin_view_logs_placeholder')

@admin_check
async def admin_update_user_command(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    admin_user = update.effective_user;
    if not admin_user or not update.message : return
    lang = get_user(admin_user.id)['language'] if get_user(admin_user.id) else 'en'
    await update.message.reply_text(get_text(lang, 'command_under_construction', default="This command is under construction."))
    if admin_user: add_admin_log(admin_id=admin_user.id, action='admin_update_user_placeholder')

@admin_check
async def admin_broadcast_command(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    admin_user = update.effective_user;
    if not admin_user or not update.message : return
    lang = get_user(admin_user.id)['language'] if get_user(admin_user.id) else 'en'
    await update.message.reply_text(get_text(lang, 'command_under_construction', default="This command is under construction."))
    if admin_user: add_admin_log(admin_id=admin_user.id, action='admin_broadcast_placeholder')
