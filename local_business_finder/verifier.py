import time
from serpapi import GoogleSearch
from urllib.parse import urlparse
from classifier import SOCIAL_DOMAINS, DIRECTORY_DOMAINS
from config import SERPAPI_KEY

def verify_business_website(business_name, city):
    """
    Performs a web search to verify if a business has a custom website.

    Args:
        business_name (str): The name of the business to search for.
        city (str): The city/region where the business is located.

    Returns:
        tuple: (evidence_url, error)
    """
    if not SERPAPI_KEY or SERPAPI_KEY == "YOUR_API_KEY_HERE":
        return None, "SerpApi API key is missing from config.py. Skipping verification."

    # --- FIX 5: SEARCH QUERY CONTEXT ---
    # The search query now includes the full city/region context for better accuracy.
    search_query = f'"{business_name}" "{city}" official website'

    params = {
        "api_key": SERPAPI_KEY, "engine": "google", "q": search_query,
        "google_domain": "google.com", "hl": "en", "gl": "us",
    }

    time.sleep(1.5)
    try:
        search = GoogleSearch(params)
        results = search.get_dict()
    except Exception as e:
        return None, f"SerpApi request failed: {e}"

    organic_results = results.get("organic_results", [])
    if not organic_results:
        return None, None

    # --- FIX 3: WEB VERIFIER LOGIC ---
    # The verifier now evaluates the top N search results (up to 5) to find any
    # evidence of a custom website. It will only return early if a custom domain
    # is definitively found. Social and directory links are ignored.
    non_custom_domains = SOCIAL_DOMAINS.union(DIRECTORY_DOMAINS)

    for result in organic_results[:5]:
        link = result.get("link")
        if not link:
            continue

        try:
            parsed_url = urlparse(link)
            domain = parsed_url.netloc.replace("www.", "")
        except Exception:
            continue

        is_non_custom = any(domain == nc_domain or domain.endswith("." + nc_domain) for nc_domain in non_custom_domains)

        if not is_non_custom:
            # A custom domain was found. This business is not a lead.
            return link, None

    # If the loop completes without finding a custom domain, the business is clear.
    return None, None
