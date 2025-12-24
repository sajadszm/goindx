from urllib.parse import urlparse
import requests
import time

# --- Domain Lists ---
SOCIAL_DOMAINS = {
    "instagram.com", "facebook.com", "linkedin.com", "twitter.com", "x.com",
    "t.me", "telegram.me", "wa.me", "whatsapp.com", "linktr.ee",
}
DIRECTORY_DOMAINS = {
    "yelp.com", "yellowpages.com", "mapquest.com", "bbb.org", "foursquare.com",
}

# --- Classification Logic ---

def classify_website(website_url, resolve_redirects=False):
    """
    Classifies a URL, with an option to follow redirects.

    Args:
        website_url (str or None): The URL from the OSM data.
        resolve_redirects (bool): If True, performs a HEAD request to find the
                                  final URL after redirects.

    Returns:
        tuple: (final_status, platform_name)
    """
    if not website_url or not website_url.strip():
        return 'NO_WEBSITE', None

    # --- FIX 4: REDIRECT-AWARE WEBSITE CHECK ---
    # If requested, resolve the URL to its final destination before classifying.
    if resolve_redirects:
        final_url, error = _resolve_url_redirect(website_url)
        if error:
            # If redirection fails, conservatively classify as custom.
            return 'CUSTOM_WEBSITE', None
        website_url = final_url

    return _classify_url_structure(website_url)

def _classify_url_structure(url):
    """
    Classifies a URL based on its domain.
    """
    try:
        if '://' not in url:
            url = 'http://' + url
        parsed_url = urlparse(url)
        domain = parsed_url.netloc.replace("www.", "")
    except Exception:
        return 'CUSTOM_WEBSITE', None

    # --- FIX 2: DIRECTORY VS SOCIAL MISCLASSIFICATION ---
    # Check against social media domains first.
    for social_domain in SOCIAL_DOMAINS:
        if domain == social_domain or domain.endswith("." + social_domain):
            platform_name = social_domain.split('.')[0].capitalize()
            return 'SOCIAL_ONLY', platform_name

    # Check against directory domains and assign the new 'DIRECTORY_ONLY' status.
    for directory_domain in DIRECTORY_DOMAINS:
        if domain == directory_domain or domain.endswith("." + directory_domain):
            platform_name = directory_domain.split('.')[0].capitalize()
            return 'DIRECTORY_ONLY', platform_name

    return 'CUSTOM_WEBSITE', None

def _resolve_url_redirect(url):
    """

    Performs a lightweight HEAD request to find the final URL after redirects.
    """
    time.sleep(1) # Be respectful to servers.
    try:
        if '://' not in url:
            url = 'http://' + url
        # Use a standard browser User-Agent.
        headers = {"User-Agent": "Mozilla/5.0"}
        response = requests.head(url, allow_redirects=True, timeout=10, headers=headers)
        return response.url, None
    except requests.RequestException as e:
        return None, f"Could not resolve URL {url}: {e}"
