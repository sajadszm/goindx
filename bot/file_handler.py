import logging
import os
import httpx
import re
import time
import mimetypes # To guess original filename extension
from urllib.parse import urlparse, unquote
from typing import Optional # Added Optional for type hint

from telegram import Update, InputFile
from telegram.ext import ContextTypes, MessageHandler, filters as Filters # Renamed to avoid conflict
from telegram.constants import ParseMode
import telegram # Added for telegram.error.TelegramError

from bot.database import get_user, update_user_subscription # update_user_subscription for checking expired
from bot.localization import get_text
from utils import format_file_size # Assuming utils.py is in root, adjust if it's in bot/
import datetime

logger = logging.getLogger(__name__)

MAX_FREE_SIZE_BYTES = 500 * 1024 * 1024  # 500 MB
# Telegram's documented limit for files sent by bots is 50MB, for files downloaded by bots is 20MB.
# For sending files > 50MB, bots must provide a public URL to Telegram.
# We will try to send directly, and if it fails due to size, we'll inform user.
# The 2GB limit is for users uploading TO Telegram directly.
MAX_BOT_UPLOAD_SIZE_BYTES = 50 * 1024 * 1024 # More realistic limit for direct bot uploads
MAX_TELEGRAM_DOWNLOAD_FROM_URL_SIZE_BYTES = 2000 * 1024 * 1024 # Theoretical 2GB, but let's be generous for HEAD check

DOWNLOAD_DIR = "downloads"

# Ensure DOWNLOAD_DIR exists
if not os.path.exists(DOWNLOAD_DIR):
    try:
        os.makedirs(DOWNLOAD_DIR)
        logger.info(f"Created download directory: {DOWNLOAD_DIR}")
    except OSError as e:
        logger.error(f"Could not create download directory {DOWNLOAD_DIR}: {e}")
        # This is a critical error, bot might not function for downloads
        # Depending on policy, could raise an exception or try to continue

def get_filename_from_url(url: str, headers: Optional[httpx.Headers] = None) -> str:
    """Attempts to extract a filename from URL or Content-Disposition header."""
    # 1. Check Content-Disposition header
    if headers:
        content_disposition = headers.get("content-disposition")
        if content_disposition:
            # Matches filename="filename.ext" or filename*=UTF-8''filename.ext
            match = re.search(r"filename\*?=([^;\n]+)", content_disposition)
            if match:
                filename_part = match.group(1).strip()
                # Handle RFC 5987 encoding (filename*=UTF-8''urlencoded_filename)
                if filename_part.lower().startswith("utf-8''"):
                    filename_part = unquote(filename_part[7:])
                # Remove quotes if any
                filename = filename_part.strip('"\'')
                if filename:
                    return filename

    # 2. Parse URL path
    parsed_url = urlparse(url)
    filename = os.path.basename(unquote(parsed_url.path))
    if filename:
        return filename

    # 3. Fallback: generate a name with guessed extension
    content_type = headers.get("content-type") if headers else None
    extension = mimetypes.guess_extension(content_type.split(';')[0]) if content_type else ".dat"
    return f"downloaded_file{extension or '.unknown'}"


async def handle_url_message(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    """Handles incoming text messages that might contain a URL to download."""
    if not update.message or not update.message.text:
        return

    message_text = update.message.text
    user = update.effective_user

    if not user:
        logger.warning("No effective_user in handle_url_message.")
        return

    # Basic URL validation (very simplistic)
    if not (message_text.startswith("http://") or message_text.startswith("https://") or message_text.startswith("ftp://")):
        # Not a URL, or not a type we handle. Could be a regular message.
        # Depending on bot's strictness, either ignore or reply with "not a valid URL"
        # For now, assume other handlers might pick it up, or it's just chat.
        # If this is the ONLY text handler, then a reply might be good.
        # logger.info(f"Ignoring non-URL message from {user.id}: {message_text[:50]}")
        return

    url = message_text.strip()

    db_user = get_user(user.id)
    if not db_user:
        add_user(user.id) # Add user if they somehow skipped /start
        db_user = get_user(user.id) # fetch again
        if not db_user: # Still not found, critical DB error
             logger.error(f"Failed to add/get user {user.id} in handle_url_message after attempting to add.")
             await update.message.reply_text(get_text('en', 'error_generic'))
             return

    lang = db_user['language']

    await update.message.reply_text(get_text(lang, 'processing_url', url=url))

    temp_file_path: Optional[str] = None # Ensure it's defined for finally block
    status_message = None # Initialize status_message
    upload_status_message = None # Initialize upload_status_message
    is_subscribed = False # Initialize is_subscribed
    downloaded_bytes = 0 # Initialize downloaded_bytes

    try:
        async with httpx.AsyncClient(timeout=20.0, follow_redirects=True) as client: # Timeout for HEAD and initial GET connection
            # 1. File Size Check (HEAD Request)
            file_size: Optional[int] = None
            original_filename: str = "downloaded_file"
            headers_dict: Optional[httpx.Headers] = None # Use Optional for headers_dict
            try:
                head_response = await client.head(url)
                head_response.raise_for_status() # Check for HTTP errors
                headers_dict = head_response.headers
                content_length = headers_dict.get("Content-Length")
                if content_length and content_length.isdigit():
                    file_size = int(content_length)
                original_filename = get_filename_from_url(url, headers_dict)
                logger.info(f"HEAD request for {url}: Size={file_size}, Filename={original_filename}")
            except httpx.RequestError as e:
                logger.warning(f"HEAD request failed for {url}: {e}. Proceeding to download attempt.")
                # Do not return yet, try GET
            except httpx.HTTPStatusError as e:
                logger.error(f"HEAD request HTTP error for {url}: {e.response.status_code} - {e.response.text}")
                await update.message.reply_text(get_text(lang, 'url_not_downloadable') + f" (Error: {e.response.status_code})")
                return

            if file_size is not None: # Only apply size checks if size is known
                if file_size > MAX_TELEGRAM_DOWNLOAD_FROM_URL_SIZE_BYTES:
                    await update.message.reply_text(get_text(lang, 'file_size_limit_exceeded_telegram',
                                                             file_size=format_file_size(file_size),
                                                             limit_gb=MAX_TELEGRAM_DOWNLOAD_FROM_URL_SIZE_BYTES // (1024**3)))
                    return

                if file_size > MAX_FREE_SIZE_BYTES:
                    sub_status = db_user['subscription_status']
                    sub_exp_date_str = db_user['subscription_expiration_date']

                    if sub_status == 'active':
                        if sub_exp_date_str:
                            try:
                                sub_exp_date = datetime.datetime.fromisoformat(sub_exp_date_str)
                                if sub_exp_date > datetime.datetime.now():
                                    is_subscribed = True
                                else:
                                    update_user_subscription(user.id, 'expired', sub_exp_date)
                                    logger.info(f"Subscription for user {user.id} found expired.")
                            except ValueError:
                                logger.error(f"Could not parse subscription_expiration_date '{sub_exp_date_str}' for user {user.id}")
                        else:
                            is_subscribed = True

                    if not is_subscribed:
                        await update.message.reply_text(get_text(lang, 'file_size_limit_exceeded_free',
                                                                 file_size=format_file_size(file_size),
                                                                 limit_mb=MAX_FREE_SIZE_BYTES // (1024**2)))
                        await update.message.reply_text(get_text(lang, 'subscription_required'))
                        return
            else:
                logger.info(f"File size unknown for {url} after HEAD. Proceeding with download cautiously.")


            # 2. File Download
            status_message = await update.message.reply_text(get_text(lang, 'download_started', url=url))

            safe_filename = re.sub(r'[^\w\.\-_ ]', '_', original_filename)
            safe_filename = safe_filename[:200]
            if not safe_filename: safe_filename = "downloaded_file.dat"

            temp_file_path = os.path.join(DOWNLOAD_DIR, f"{user.id}_{int(time.time())}_{safe_filename}")

            last_progress_update_time = time.time()

            async with client.stream('GET', url, timeout=300.0) as response:
                response.raise_for_status()

                if file_size is None and response.headers.get("Content-Length") and response.headers.get("Content-Length").isdigit():
                    file_size = int(response.headers.get("Content-Length"))
                    logger.info(f"File size obtained from GET response: {file_size} for {url}")
                    # Re-run size checks
                    if file_size > MAX_TELEGRAM_DOWNLOAD_FROM_URL_SIZE_BYTES:
                        await context.bot.edit_message_text(chat_id=status_message.chat_id, message_id=status_message.message_id,
                                                            text=get_text(lang, 'file_size_limit_exceeded_telegram',
                                                                          file_size=format_file_size(file_size),
                                                                          limit_gb=MAX_TELEGRAM_DOWNLOAD_FROM_URL_SIZE_BYTES // (1024**3)))
                        return
                    if file_size > MAX_FREE_SIZE_BYTES and not is_subscribed: # Re-check subscription logic
                         if db_user['subscription_status'] == 'active': # Check again more thoroughly
                            sub_exp_date_str = db_user['subscription_expiration_date']
                            if sub_exp_date_str:
                                try:
                                    sub_exp_date = datetime.datetime.fromisoformat(sub_exp_date_str)
                                    if sub_exp_date > datetime.datetime.now():
                                        is_subscribed = True # Update flag
                                except ValueError: pass # Keep is_subscribed as False
                         if not is_subscribed:
                            await context.bot.edit_message_text(chat_id=status_message.chat_id, message_id=status_message.message_id,
                                                                text=get_text(lang, 'file_size_limit_exceeded_free',
                                                                    file_size=format_file_size(file_size),
                                                                    limit_mb=MAX_FREE_SIZE_BYTES // (1024**2)))
                            return


                with open(temp_file_path, 'wb') as f:
                    async for chunk in response.aiter_bytes(chunk_size=8192):
                        f.write(chunk)
                        downloaded_bytes += len(chunk)

                        current_time = time.time()
                        if current_time - last_progress_update_time > 5:
                            progress_text_val = f"{format_file_size(downloaded_bytes)}"
                            if file_size:
                                percentage = (downloaded_bytes / file_size) * 100
                                progress_text_val += f" / {format_file_size(file_size)} ({percentage:.1f}%)"

                            try:
                                await context.bot.edit_message_text(
                                    chat_id=status_message.chat_id,
                                    message_id=status_message.message_id,
                                    text=get_text(lang, 'download_progress', progress=progress_text_val)
                                )
                                last_progress_update_time = current_time
                            except Exception as e:
                                logger.debug(f"Failed to edit progress message: {e}")

                        # More robust early exit checks
                        if not is_subscribed and downloaded_bytes > MAX_FREE_SIZE_BYTES:
                            raise ValueError(f"Downloaded data ({format_file_size(downloaded_bytes)}) exceeded free limit ({format_file_size(MAX_FREE_SIZE_BYTES)}) during download.")
                        if downloaded_bytes > MAX_BOT_UPLOAD_SIZE_BYTES: # Check against bot's practical upload limit
                            raise ValueError(f"Downloaded data ({format_file_size(downloaded_bytes)}) exceeded bot upload limit ({format_file_size(MAX_BOT_UPLOAD_SIZE_BYTES)}) during download.")


            await context.bot.edit_message_text(chat_id=status_message.chat_id, message_id=status_message.message_id,
                                                text=get_text(lang, 'download_complete'))

            upload_status_message = await update.message.reply_text(get_text(lang, 'upload_progress', progress="0%"))

            final_downloaded_size = os.path.getsize(temp_file_path)
            if final_downloaded_size > MAX_BOT_UPLOAD_SIZE_BYTES:
                await context.bot.edit_message_text(
                    chat_id=upload_status_message.chat_id, message_id=upload_status_message.message_id,
                    text=get_text(lang, 'file_size_limit_exceeded_telegram',
                                  file_size=format_file_size(final_downloaded_size),
                                  limit_gb=f"{MAX_BOT_UPLOAD_SIZE_BYTES // (1024**2)} MB (Bot Limit)"))
                logger.warning(f"File {safe_filename} ({format_file_size(final_downloaded_size)}) too large for direct bot upload. User {user.id}.")
                return

            logger.info(f"Attempting to upload {safe_filename} ({format_file_size(final_downloaded_size)}) for user {user.id}")

            with open(temp_file_path, 'rb') as f_doc:
                sent_message = await context.bot.send_document(
                    chat_id=user.id,
                    document=InputFile(f_doc, filename=safe_filename),
                    caption=get_text(lang, 'file_sent_successfully') + f"\nSource: {url}",
                    parse_mode=ParseMode.HTML,
                    connect_timeout=30, read_timeout=120, write_timeout=120 # Increased timeouts
                )
            if sent_message:
                 await context.bot.delete_message(chat_id=upload_status_message.chat_id, message_id=upload_status_message.message_id)
                 logger.info(f"File {safe_filename} sent to user {user.id} successfully.")
            else: # Should not happen if no exception from send_document
                raise Exception("Sending document returned no message or failed silently.")


    except httpx.TimeoutException as e:
        logger.error(f"Timeout for {url}: {e}")
        error_msg_timeout = get_text(lang, 'url_not_downloadable') + " (Timeout)"
        if status_message:
             await context.bot.edit_message_text(chat_id=status_message.chat_id, message_id=status_message.message_id, text=error_msg_timeout)
        else:
            await update.message.reply_text(error_msg_timeout)
    except httpx.RequestError as e:
        logger.error(f"RequestError for {url}: {e}")
        error_msg_req = get_text(lang, 'url_not_downloadable')
        if status_message:
             await context.bot.edit_message_text(chat_id=status_message.chat_id, message_id=status_message.message_id, text=error_msg_req)
        else:
            await update.message.reply_text(error_msg_req)
    except httpx.HTTPStatusError as e:
        logger.error(f"HTTPStatusError for {url} during GET: {e.response.status_code} - {e.response.text if e.response else 'No response text'}")
        error_text_http = get_text(lang, 'url_not_downloadable') + f" (Server Error: {e.response.status_code})"
        if status_message:
            await context.bot.edit_message_text(chat_id=status_message.chat_id, message_id=status_message.message_id, text=error_text_http)
        else:
            await update.message.reply_text(error_text_http)
    except ValueError as e:
        logger.error(f"ValueError during download for {url}: {e}")
        error_key_value = 'file_size_limit_exceeded_free' if 'free limit' in str(e) else 'file_size_limit_exceeded_telegram' # Simplified
        limit_val_value = MAX_FREE_SIZE_BYTES if 'free limit' in str(e) else MAX_BOT_UPLOAD_SIZE_BYTES
        error_text_value = get_text(lang, error_key_value,
                              file_size=format_file_size(downloaded_bytes),
                              limit_mb=limit_val_value // (1024**2))
        if status_message: # status_message should exist if download started
            await context.bot.edit_message_text(chat_id=status_message.chat_id, message_id=status_message.message_id, text=error_text_value)
        else: # Should ideally not happen if error is during download phase
            await update.message.reply_text(error_text_value)

    except telegram.error.TelegramError as e:
        logger.error(f"Telegram API error while sending file from {url} for user {user.id}: {e}")
        error_text_telegram: str
        if "file is too big" in str(e).lower() or "request entity too large" in str(e).lower():
            error_text_telegram = get_text(lang, 'file_size_limit_exceeded_telegram',
                                  file_size=format_file_size(os.path.getsize(temp_file_path) if temp_file_path and os.path.exists(temp_file_path) else 0),
                                  limit_gb=f"{MAX_BOT_UPLOAD_SIZE_BYTES // (1024**2)} MB (Bot Limit)")
        else:
            error_text_telegram = get_text(lang, 'error_generic') + f" (TG Error: {e})"

        if upload_status_message:
            await context.bot.edit_message_text(chat_id=upload_status_message.chat_id, message_id=upload_status_message.message_id, text=error_text_telegram)
        elif status_message:
             await context.bot.edit_message_text(chat_id=status_message.chat_id, message_id=status_message.message_id, text=error_text_telegram)
        else:
            await update.message.reply_text(error_text_telegram)

    except Exception as e:
        logger.error(f"Unexpected error handling URL {url} for user {user.id}: {e}", exc_info=True)
        error_text_exc = get_text(lang, 'error_generic')
        if upload_status_message:
            await context.bot.edit_message_text(chat_id=upload_status_message.chat_id, message_id=upload_status_message.message_id, text=error_text_exc)
        elif status_message:
             await context.bot.edit_message_text(chat_id=status_message.chat_id, message_id=status_message.message_id, text=error_text_exc)
        else:
            await update.message.reply_text(error_text_exc)
    finally:
        if temp_file_path and os.path.exists(temp_file_path):
            try:
                os.remove(temp_file_path)
                logger.info(f"Temporary file {temp_file_path} deleted.")
            except OSError as e:
                logger.error(f"Error deleting temporary file {temp_file_path}: {e}")
