from urllib.parse import urlparse

# --- Domain Lists ---
# These lists define which domains are considered 'social' or 'directory' sites.
# These are treated as non-custom websites, making the business a potential lead.

SOCIAL_DOMAINS = {
    "instagram.com",
    "facebook.com",
    "linkedin.com",
    "twitter.com",
    "x.com",
    "t.me",
    "telegram.me",
    "wa.me",
    "whatsapp.com",
    "linktr.ee",
}

DIRECTORY_DOMAINS = {
    "yelp.com",
    "yellowpages.com",
    "mapquest.com",
    "bbb.org",
    "foursquare.com",
}

# --- Classification Logic ---

def classify_website(website_url):
    """
    Classifies a website URL to determine if it's a custom site, social media,
    directory, or if there's no website at all.

    Args:
        website_url (str or None): The website URL from the OSM data.

    Returns:
        tuple: A tuple containing:
               - final_status (str): 'NO_WEBSITE', 'SOCIAL_ONLY', or 'CUSTOM_WEBSITE'.
               - social_type (str or None): The name of the social/directory platform
                 (e.g., 'Facebook', 'Yelp') or None if not applicable.
    """
    if not website_url or not website_url.strip():
        return 'NO_WEBSITE', None

    try:
        # Prepend 'http://' if no scheme is present to allow urlparse to work correctly.
        if '://' not in website_url:
            website_url = 'http://' + website_url

        parsed_url = urlparse(website_url)
        # Get the domain and remove 'www.' prefix if it exists.
        domain = parsed_url.netloc.replace("www.", "")
    except Exception:
        # If parsing fails for any reason, treat it as a custom website to be safe.
        return 'CUSTOM_WEBSITE', None

    # Check against social media domains
    for social_domain in SOCIAL_DOMAINS:
        if domain == social_domain or domain.endswith("." + social_domain):
            # Capitalize the first letter for display (e.g., 'facebook.com' -> 'Facebook')
            platform_name = social_domain.split('.')[0].capitalize()
            return 'SOCIAL_ONLY', platform_name

    # Check against directory domains
    for directory_domain in DIRECTORY_DOMAINS:
        if domain == directory_domain or domain.endswith("." + directory_domain):
            platform_name = directory_domain.split('.')[0].capitalize()
            return 'SOCIAL_ONLY', platform_name

    # If it's not in any of the lists, it's a custom website.
    return 'CUSTOM_WEBSITE', None
