import requests
import time

# The public Overpass API endpoint.
OVERPASS_API_URL = "https://overpass-api.de/api/interpreter"

def _run_overpass_query(query):
    """
    Helper function to run a query against the Overpass API.
    """
    time.sleep(2) # Respectful delay.
    headers = {
        "User-Agent": "LocalBusinessFinder/1.0 (Personal Project; https://github.com/user/repo)"
    }
    try:
        response = requests.post(OVERPASS_API_URL, data=query, headers=headers, timeout=60)
        response.raise_for_status()
        data = response.json()
        return data.get("elements", []), None
    except requests.exceptions.RequestException as e:
        return None, f"Network error connecting to Overpass API: {e}"

def fetch_businesses(city, keyword):
    """
    Fetches business data from OSM using a tag-first, name-second approach.

    Args:
        city (str): The city or region to search in.
        keyword (str): The business keyword to search for.

    Returns:
        list: A list of business dictionaries.
        str: An error message if the request fails.
    """
    # --- FIX 1: OSM QUERY LOGIC ---
    # The query is now split into two parts for better accuracy.
    # 1. Primary Query: Searches for the keyword in specific business-related tags.
    #    This is the preferred search method as it returns more relevant results.
    primary_query = f"""
    [out:json][timeout:50];
    area[name="{city}"]->.searchArea;
    (
      nwr(area.searchArea)[~"^(amenity|shop|office|craft|tourism)$"~"{keyword}", i];
    );
    out center;
    """

    elements, error = _run_overpass_query(primary_query)
    if error:
        return None, error

    # 2. Fallback Query: If the primary query yields no results, fall back
    #    to searching the 'name' tag. This is less precise but provides a safety net.
    if not elements:
        fallback_query = f"""
        [out:json][timeout:50];
        area[name="{city}"]->.searchArea;
        (
          nwr(area.searchArea)["name"~"{keyword}", i];
        );
        out center;
        """
        elements, error = _run_overpass_query(fallback_query)
        if error:
            return None, error

    if not elements:
        return [], None

    # Filter out elements that don't have a 'tags' field.
    businesses = [elem for elem in elements if "tags" in elem]
    return businesses, None
