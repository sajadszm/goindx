import time
from serpapi import GoogleSearch
from urllib.parse import urlparse

# Import the domain lists and API key from other modules.
from classifier import SOCIAL_DOMAINS, DIRECTORY_DOMAINS
from config import SERPAPI_KEY

def verify_business_website(business_name, city):
    """
    Performs a web search to verify if a business has a custom website.

    This function is called only for businesses that passed the initial OSM filter
    (i.e., they have no website or only a social media link in OSM).

    Args:
        business_name (str): The name of the business to search for.
        city (str): The city/region where the business is located.

    Returns:
        tuple: A tuple containing:
               - evidence_url (str or None): The URL of a custom website if found,
                 otherwise None.
               - error (str or None): An error message if the verification
                 failed, otherwise None.
    """
    # 1. Check if the API key has been provided.
    if not SERPAPI_KEY or SERPAPI_KEY == "YOUR_API_KEY_HERE":
        return None, "SerpApi API key is missing from config.py. Skipping verification."

    # 2. Construct the search query.
    search_query = f'"{business_name}" "{city}" official website'

    # 3. Set up the parameters for the SerpApi search.
    params = {
        "api_key": SERPAPI_KEY,
        "engine": "google",
        "q": search_query,
        "google_domain": "google.com",
        "hl": "en",
        "gl": "us",
    }

    # 4. Perform the search and handle potential errors.
    time.sleep(1.5) # Add a respectful delay between API calls.
    try:
        search = GoogleSearch(params)
        results = search.get_dict()
    except Exception as e:
        return None, f"SerpApi request failed: {e}"

    # 5. Analyze the organic search results.
    organic_results = results.get("organic_results", [])
    if not organic_results:
        return None, None # No results found, so no evidence of a website.

    # Combine social and directory domains for easier checking.
    non_custom_domains = SOCIAL_DOMAINS.union(DIRECTORY_DOMAINS)

    for result in organic_results[:5]: # Check the top 5 results for relevance.
        link = result.get("link")
        if not link:
            continue

        try:
            parsed_url = urlparse(link)
            domain = parsed_url.netloc.replace("www.", "")
        except Exception:
            continue # Skip malformed URLs.

        # Check if the domain is a known social or directory site.
        is_non_custom = False
        for non_custom_domain in non_custom_domains:
            if domain == non_custom_domain or domain.endswith("." + non_custom_domain):
                is_non_custom = True
                break

        # If the domain is not in the non-custom lists, we've found a custom website.
        if not is_non_custom:
            return link, None # Return the URL as evidence.

    # 6. If no custom website was found in the top results, return None.
    return None, None
