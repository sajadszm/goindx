import sqlite3
import logging
import datetime
from typing import List, Tuple, Optional, Any
import os

DATABASE_NAME = os.path.join(os.getcwd(), 'bot_database.db')

logger = logging.getLogger(__name__)

def get_db_connection() -> sqlite3.Connection:
    """Establishes a connection to the SQLite database."""
    conn = sqlite3.connect(DATABASE_NAME)
    conn.row_factory = sqlite3.Row # Access columns by name
    return conn

def initialize_database() -> None:
    """Initializes the database and creates tables if they don't exist."""
    conn = get_db_connection()
    cursor = conn.cursor()

    # User Profiles Table
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS users (
            user_id INTEGER PRIMARY KEY,
            language TEXT DEFAULT 'en',
            subscription_status TEXT DEFAULT 'inactive', -- 'active', 'inactive', 'expired'
            subscription_expiration_date DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ''')
    logger.info("Table 'users' checked/created.")

    # Payments Table
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS payments (
            payment_id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            method TEXT, -- 'paypal', 'bitcoin', 'zarinpal', 'manual'
            amount REAL,
            currency TEXT,
            status TEXT, -- 'pending', 'completed', 'failed', 'expired'
            transaction_id TEXT UNIQUE, -- From payment gateway, can be NULL for some methods initially
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users (user_id)
        )
    ''')
    logger.info("Table 'payments' checked/created.")

    # Admin Logs Table
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS admin_logs (
            log_id INTEGER PRIMARY KEY AUTOINCREMENT,
            admin_id INTEGER, -- Telegram User ID of the admin
            action TEXT, -- e.g., 'updated_subscription', 'broadcast_message'
            target_user_id INTEGER, -- Optional, FK to users if action is on a specific user
            details TEXT, -- Additional details, like new subscription status or message content
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (target_user_id) REFERENCES users (user_id)
        )
    ''')
    logger.info("Table 'admin_logs' checked/created.")

    conn.commit()
    conn.close()
    logger.info("Database initialization complete.")

# --- User Management ---
def add_user(user_id: int, language: str = 'en') -> None:
    """Adds a new user or updates their language if they already exist (e.g., on /start)."""
    conn = get_db_connection()
    cursor = conn.cursor()
    try:
        cursor.execute('''
            INSERT INTO users (user_id, language, subscription_status, created_at)
            VALUES (?, ?, 'inactive', datetime('now'))
            ON CONFLICT(user_id) DO UPDATE SET
            language=excluded.language
        ''', (user_id, language))
        conn.commit()
        logger.info(f"User {user_id} added or language updated to {language}.")
    except sqlite3.Error as e:
        logger.error(f"Error adding/updating user {user_id}: {e}")
    finally:
        conn.close()

def get_user(user_id: int) -> Optional[sqlite3.Row]:
    """Retrieves a user's profile from the database."""
    conn = get_db_connection()
    cursor = conn.cursor()
    try:
        cursor.execute("SELECT * FROM users WHERE user_id = ?", (user_id,))
        user = cursor.fetchone()
        return user
    except sqlite3.Error as e:
        logger.error(f"Error getting user {user_id}: {e}")
        return None
    finally:
        conn.close()

def update_user_language(user_id: int, language: str) -> bool:
    """Updates a user's selected language."""
    conn = get_db_connection()
    cursor = conn.cursor()
    try:
        cursor.execute("UPDATE users SET language = ? WHERE user_id = ?", (language, user_id))
        conn.commit()
        if cursor.rowcount > 0:
            logger.info(f"Language for user {user_id} updated to {language}.")
            return True
        logger.warning(f"Attempted to update language for non-existent user {user_id}.")
        return False
    except sqlite3.Error as e:
        logger.error(f"Error updating language for user {user_id}: {e}")
        return False
    finally:
        conn.close()

def update_user_subscription(user_id: int, status: str, expiration_date: Optional[datetime.datetime] = None) -> bool:
    """Updates a user's subscription status and expiration date."""
    conn = get_db_connection()
    cursor = conn.cursor()
    try:
        cursor.execute('''
            UPDATE users
            SET subscription_status = ?, subscription_expiration_date = ?
            WHERE user_id = ?
        ''', (status, expiration_date.strftime('%Y-%m-%d %H:%M:%S') if expiration_date else None, user_id))
        conn.commit()
        if cursor.rowcount > 0:
            logger.info(f"Subscription for user {user_id} updated to {status} with expiration {expiration_date}.")
            return True
        logger.warning(f"Attempted to update subscription for non-existent user {user_id}.")
        return False
    except sqlite3.Error as e:
        logger.error(f"Error updating subscription for user {user_id}: {e}")
        return False
    finally:
        conn.close()

def get_all_users() -> List[sqlite3.Row]:
    """Retrieves all users from the database."""
    conn = get_db_connection()
    cursor = conn.cursor()
    try:
        cursor.execute("SELECT * FROM users ORDER BY created_at DESC")
        users = cursor.fetchall()
        return users
    except sqlite3.Error as e:
        logger.error(f"Error getting all users: {e}")
        return []
    finally:
        conn.close()

# --- Payment Management ---
def add_payment_record(user_id: int, method: str, amount: float, currency: str, status: str, transaction_id: Optional[str] = None) -> Optional[int]:
    """Adds a new payment record to the database."""
    conn = get_db_connection()
    cursor = conn.cursor()
    try:
        cursor.execute('''
            INSERT INTO payments (user_id, method, amount, currency, status, transaction_id, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))
        ''', (user_id, method, amount, currency, status, transaction_id))
        conn.commit()
        payment_id = cursor.lastrowid
        logger.info(f"Payment record added for user {user_id} via {method}, ID: {payment_id}.")
        return payment_id
    except sqlite3.Error as e:
        logger.error(f"Error adding payment record for user {user_id}: {e}")
        return None
    finally:
        conn.close()

def update_payment_status(payment_id: int, status: str, transaction_id: Optional[str] = None) -> bool:
    """Updates the status and transaction_id of a payment record."""
    conn = get_db_connection()
    cursor = conn.cursor()
    try:
        if transaction_id:
            cursor.execute('''
                UPDATE payments
                SET status = ?, transaction_id = ?, updated_at = datetime('now')
                WHERE payment_id = ?
            ''', (status, transaction_id, payment_id))
        else:
            cursor.execute('''
                UPDATE payments
                SET status = ?, updated_at = datetime('now')
                WHERE payment_id = ?
            ''', (status, payment_id))
        conn.commit()
        if cursor.rowcount > 0:
            logger.info(f"Payment ID {payment_id} status updated to {status}.")
            return True
        logger.warning(f"Attempted to update status for non-existent payment ID {payment_id}.")
        return False
    except sqlite3.Error as e:
        logger.error(f"Error updating payment status for ID {payment_id}: {e}")
        return False
    finally:
        conn.close()

def get_payment_by_transaction_id(transaction_id: str) -> Optional[sqlite3.Row]:
    """Retrieves a payment record by its transaction ID."""
    conn = get_db_connection()
    cursor = conn.cursor()
    try:
        cursor.execute("SELECT * FROM payments WHERE transaction_id = ?", (transaction_id,))
        payment = cursor.fetchone()
        return payment
    except sqlite3.Error as e:
        logger.error(f"Error getting payment by transaction_id {transaction_id}: {e}")
        return None
    finally:
        conn.close()

def get_payment_by_id(payment_id: int) -> Optional[sqlite3.Row]:
    """Retrieves a payment record by its internal payment_id."""
    conn = get_db_connection()
    cursor = conn.cursor()
    try:
        cursor.execute("SELECT * FROM payments WHERE payment_id = ?", (payment_id,))
        payment = cursor.fetchone()
        return payment
    except sqlite3.Error as e:
        logger.error(f"Error getting payment by payment_id {payment_id}: {e}")
        return None
    finally:
        conn.close()

def get_user_payments(user_id: int) -> List[sqlite3.Row]:
    """Retrieves all payment records for a specific user."""
    conn = get_db_connection()
    cursor = conn.cursor()
    try:
        cursor.execute("SELECT * FROM payments WHERE user_id = ? ORDER BY created_at DESC", (user_id,))
        payments = cursor.fetchall()
        return payments
    except sqlite3.Error as e:
        logger.error(f"Error getting payments for user {user_id}: {e}")
        return []
    finally:
        conn.close()

def get_all_payments() -> List[sqlite3.Row]:
    """Retrieves all payment records from the database."""
    conn = get_db_connection()
    cursor = conn.cursor()
    try:
        cursor.execute("SELECT * FROM payments ORDER BY created_at DESC")
        payments = cursor.fetchall()
        return payments
    except sqlite3.Error as e:
        logger.error(f"Error getting all payments: {e}")
        return []
    finally:
        conn.close()

# --- Admin Logging ---
def add_admin_log(admin_id: int, action: str, target_user_id: Optional[int] = None, details: str = "") -> None:
    """Adds a log entry for an admin action."""
    conn = get_db_connection()
    cursor = conn.cursor()
    try:
        cursor.execute('''
            INSERT INTO admin_logs (admin_id, action, target_user_id, details, timestamp)
            VALUES (?, ?, ?, ?, datetime('now'))
        ''', (admin_id, action, target_user_id, details))
        conn.commit()
        logger.info(f"Admin log added: Admin {admin_id}, Action {action}, Target {target_user_id}.")
    except sqlite3.Error as e:
        logger.error(f"Error adding admin log for admin {admin_id}, action {action}: {e}")
    finally:
        conn.close()

def get_admin_logs(limit: int = 100) -> List[sqlite3.Row]:
    """Retrieves the latest admin logs."""
    conn = get_db_connection()
    cursor = conn.cursor()
    try:
        cursor.execute("SELECT * FROM admin_logs ORDER BY timestamp DESC LIMIT ?", (limit,))
        logs = cursor.fetchall()
        return logs
    except sqlite3.Error as e:
        logger.error(f"Error getting admin logs: {e}")
        return []
    finally:
        conn.close()

if __name__ == '__main__':
    # This block is for testing the database module directly
    logging.basicConfig(level=logging.INFO, format="%(asctime)s - %(name)s - %(levelname)s - %(message)s")
    logger.info("Running database module directly for testing...")
    initialize_database()
    logger.info("Database initialized (or verified).")

    # Test user functions
    add_user(12345, 'en')
    add_user(67890, 'fa')
    user_12345 = get_user(12345)
    if user_12345:
        logger.info(f"User 12345: Lang={user_12345['language']}, Sub={user_12345['subscription_status']}")

    update_user_language(12345, 'ru')
    user_12345_updated = get_user(12345)
    if user_12345_updated:
        logger.info(f"User 12345 (updated): Lang={user_12345_updated['language']}")

    expiration = datetime.datetime.now() + datetime.timedelta(days=30)
    update_user_subscription(67890, 'active', expiration)
    user_67890_sub = get_user(67890)
    if user_67890_sub:
        logger.info(f"User 67890 (sub): Status={user_67890_sub['subscription_status']}, Expires={user_67890_sub['subscription_expiration_date']}")

    all_users = get_all_users()
    logger.info(f"Total users: {len(all_users)}")

    # Test payment functions
    payment_id = add_payment_record(12345, 'paypal', 10.0, 'USD', 'pending', 'PAYPAL_TRANS_ID_123')
    if payment_id:
        logger.info(f"Added payment with ID: {payment_id}")
        update_payment_status(payment_id, 'completed')
        retrieved_payment = get_payment_by_id(payment_id)
        if retrieved_payment:
            logger.info(f"Payment {payment_id}: Status={retrieved_payment['status']}, TXN_ID={retrieved_payment['transaction_id']}")

    add_payment_record(67890, 'bitcoin', 0.001, 'BTC', 'completed', 'BTC_TRANS_ID_XYZ')
    user_payments = get_user_payments(67890)
    logger.info(f"User 67890 has {len(user_payments)} payment records.")

    all_payments = get_all_payments()
    logger.info(f"Total payments in system: {len(all_payments)}")

    # Test admin logs
    add_admin_log(99999, 'test_action', target_user_id=12345, details='This is a test log entry.')
    admin_logs = get_admin_logs(5)
    logger.info(f"Last {len(admin_logs)} admin logs:")
    for log_entry in admin_logs:
        logger.info(f"  Log ID {log_entry['log_id']}: Admin {log_entry['admin_id']} did '{log_entry['action']}' on user {log_entry['target_user_id'] if log_entry['target_user_id'] else 'N/A'}")

    logger.info("Database module test run complete.")
