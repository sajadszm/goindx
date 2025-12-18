import requests
import time

# The public Overpass API endpoint.
OVERPASS_API_URL = "https://overpass-api.de/api/interpreter"

def fetch_businesses(city, keyword):
    """
    Fetches business data from the OpenStreetMap Overpass API.

    Args:
        city (str): The city or region to search in (e.g., "Austin, TX").
        keyword (str): The business keyword to search for (e.g., "dentist").

    Returns:
        list: A list of dictionaries, where each dictionary represents a business
              with its associated OSM tags.
        str: An error message string if the request fails.
    """
    # Respectful delay to avoid overwhelming the API.
    time.sleep(2)

    # This improved Overpass QL query is more efficient to reduce timeouts.
    # It finds the area ID for the city, then filters nodes/ways/relations
    # in that area using a single regex that checks multiple common tags.
    # The query timeout is set to 50 seconds.
    query = f"""
    [out:json][timeout:50];
    area[name="{city}"]->.searchArea;
    (
      nwr(area.searchArea)[~"^(amenity|shop|office|craft|tourism|name)$"~"{keyword}", i];
    );
    out center;
    """

    headers = {
        "User-Agent": "LocalBusinessFinder/1.0 (Personal Project; https://github.com/user/repo)"
    }

    try:
        # Increased request timeout to 60 seconds for better resilience.
        response = requests.post(OVERPASS_API_URL, data=query, headers=headers, timeout=60)
        response.raise_for_status()  # Raises an HTTPError for bad responses (4xx or 5xx)
    except requests.exceptions.RequestException as e:
        return None, f"Network error connecting to Overpass API: {e}"

    data = response.json()
    elements = data.get("elements", [])

    if not elements:
        return [], None # Return an empty list for no results, no error.

    # Filter out elements that don't have a 'tags' field, which is essential.
    businesses = [elem for elem in elements if "tags" in elem]

    return businesses, None
