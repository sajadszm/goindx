import csv
import os
import re

def sanitize_filename(name_part):
    """
    Sanitizes a string to be used as part of a filename.
    Removes special characters and replaces spaces with underscores.
    """
    if not name_part:
        return "unknown"
    # Remove characters that are not letters, numbers, spaces, or underscores
    s = re.sub(r'[^\w\s-]', '', name_part).strip()
    # Replace spaces and hyphens with a single underscore
    s = re.sub(r'[-\s]+', '_', s)
    return s

def export_to_csv(leads, city, keyword):
    """
    Exports a list of qualified business leads to a CSV file on the user's Desktop.

    Args:
        leads (list): A list of dictionaries, where each dictionary represents a lead.
        city (str): The city/region that was searched.
        keyword (str): The business keyword that was searched.

    Returns:
        tuple: A tuple containing:
               - file_path (str or None): The full path to the saved CSV file, or None on failure.
               - error (str or None): An error message if saving failed, or None on success.
    """
    # 1. Define the CSV header according to the specifications.
    header = [
        "business_name",
        "city_region",
        "keyword",
        "phone",
        "osm_website",
        "final_status",
        "social_type",
        "evidence_url",
    ]

    # 2. Determine the path to the user's Desktop.
    try:
        desktop_path = os.path.join(os.path.expanduser('~'), 'Desktop')
        # Create the Desktop directory if it doesn't exist (useful in some environments).
        if not os.path.exists(desktop_path):
            os.makedirs(desktop_path)
    except Exception as e:
        return None, f"Could not determine Desktop path: {e}"

    # 3. Construct the filename.
    sanitized_city = sanitize_filename(city)
    sanitized_keyword = sanitize_filename(keyword)
    filename = f"leads_{sanitized_city}_{sanitized_keyword}.csv"
    file_path = os.path.join(desktop_path, filename)

    # 4. Write the data to the CSV file.
    try:
        with open(file_path, 'w', newline='', encoding='utf-8') as csvfile:
            writer = csv.DictWriter(csvfile, fieldnames=header)

            # Write the header row.
            writer.writeheader()

            # Write the lead data.
            for lead in leads:
                # Ensure all keys are present, defaulting to an empty string if not.
                row_data = {key: lead.get(key, "") for key in header}
                writer.writerow(row_data)

    except IOError as e:
        return None, f"Failed to write to CSV file at {file_path}: {e}"
    except Exception as e:
        return None, f"An unexpected error occurred during CSV export: {e}"

    return file_path, None
