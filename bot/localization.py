import json
import os
import logging
from typing import Dict, Any

logger = logging.getLogger(__name__)

translations: Dict[str, Dict[str, str]] = {}
DEFAULT_LANG = 'en'
LOCALES_DIR = 'locales'

def load_translations() -> None:
    """Loads all translation files from the locales directory."""
    if not os.path.exists(LOCALES_DIR):
        logger.error(f"Locales directory '{LOCALES_DIR}' not found. Cannot load translations.")
        return

    for filename in os.listdir(LOCALES_DIR):
        if filename.endswith(".json"):
            lang_code = filename.split(".")[0]
            filepath = os.path.join(LOCALES_DIR, filename)
            try:
                with open(filepath, 'r', encoding='utf-8') as f:
                    translations[lang_code] = json.load(f)
                logger.info(f"Loaded translations for language: {lang_code}")
            except json.JSONDecodeError:
                logger.error(f"Error decoding JSON from file: {filepath}")
            except Exception as e:
                logger.error(f"Error loading translation file {filepath}: {e}")

    if not translations:
        logger.warning("No translations were loaded. The bot might not respond with proper text.")

def get_text(language_code: str, key: str, **kwargs: Any) -> str:
    """
    Retrieves a translated string for a given language and key.
    Falls back to English if the key is not found in the specified language.
    Supports basic keyword argument formatting.
    """
    if not language_code: # Fallback if language_code is None or empty
        language_code = DEFAULT_LANG

    lang_translations = translations.get(language_code)

    if lang_translations and key in lang_translations:
        text = lang_translations[key]
    elif DEFAULT_LANG in translations and key in translations[DEFAULT_LANG]:
        logger.debug(f"Key '{key}' not found for language '{language_code}'. Falling back to '{DEFAULT_LANG}'.")
        text = translations[DEFAULT_LANG][key]
    else:
        logger.warning(f"Key '{key}' not found for language '{language_code}' or in default language '{DEFAULT_LANG}'.")
        return f"[KEY_NOT_FOUND: {key}]"

    try:
        return text.format(**kwargs)
    except KeyError as e:
        logger.error(f"Missing formatting argument for key '{key}' in language '{language_code}': {e}")
        return text # Return unformatted text if formatting fails

# Initialize translations when the module is loaded
load_translations()

if __name__ == '__main__':
    # Basic test for localization
    logging.basicConfig(level=logging.INFO)
    logger.info("Running localization module test...")

    # Ensure test files exist for this direct run
    if not os.path.exists(LOCALES_DIR):
        os.makedirs(LOCALES_DIR)

    test_en = {
        "greeting": "Hello, {name}!",
        "farewell": "Goodbye!"
    }
    test_fa = {
        "greeting": "سلام، {name}!",
        "farewell": "خداحافظ!"
    }
    with open(os.path.join(LOCALES_DIR, 'en.json'), 'w', encoding='utf-8') as f:
        json.dump(test_en, f, ensure_ascii=False, indent=2)
    with open(os.path.join(LOCALES_DIR, 'fa.json'), 'w', encoding='utf-8') as f:
        json.dump(test_fa, f, ensure_ascii=False, indent=2)

    # Reload translations for test
    translations.clear()
    load_translations()

    print(f"English greeting: {get_text('en', 'greeting', name='John')}")
    print(f"Farsi greeting: {get_text('fa', 'greeting', name='امیر')}")
    print(f"Farsi farewell: {get_text('fa', 'farewell')}")
    print(f"English farewell (fallback from fa): {get_text('fa', 'unknown_key_test', fallback_text=get_text('en', 'farewell'))}") #This specific test line is conceptual
    print(f"Missing key (en): {get_text('en', 'missing_key')}")
    print(f"Missing key (fa, fallback to en missing): {get_text('fa', 'missing_key')}")
    print(f"Formatting error test (en): {get_text('en', 'greeting')}") # Missing 'name' kwarg

    # Clean up test files
    # os.remove(os.path.join(LOCALES_DIR, 'en.json'))
    # os.remove(os.path.join(LOCALES_DIR, 'fa.json'))
    logger.info("Localization module test finished.")
