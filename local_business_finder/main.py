import tkinter as tk
import threading
from ui import AppUI
from osm import fetch_businesses
from classifier import classify_website
from verifier import verify_business_website
from exporter import export_to_csv

class AppController:
    """
    Main controller for the application. Orchestrates the UI and the backend modules.
    """
    def __init__(self, root):
        self.root = root
        # Pass the start_search method to the UI. It will be called on button click.
        self.ui = AppUI(root, self.start_search_thread)

    def start_search_thread(self, city, keyword):
        """
        Starts the search process in a new thread to keep the UI responsive.
        """
        # Disable UI elements while the search is in progress.
        self.ui.set_ui_state(True)
        # Clear the status area for the new search.
        self.ui.status_area.configure(state='normal')
        self.ui.status_area.delete('1.0', tk.END)
        self.ui.status_area.configure(state='disabled')

        # Create and start the background thread.
        thread = threading.Thread(target=self.run_search, args=(city, keyword))
        thread.daemon = True # Allows the main app to exit even if the thread is running.
        thread.start()

    def run_search(self, city, keyword):
        """
        The main search logic that runs in a background thread.
        """
        try:
            self.log(f"Starting search for '{keyword}' in '{city}'...")

            # 1. Fetch businesses from OpenStreetMap.
            self.log("Fetching data from OpenStreetMap...")
            businesses, error = fetch_businesses(city, keyword)
            if error:
                self.log(f"ERROR: {error}")
                return # Stop the process if fetching fails.
            if not businesses:
                self.log("No businesses found matching the criteria in OSM.")
                return

            self.log(f"Found {len(businesses)} potential businesses in OSM. Now classifying...")

            qualified_leads = []
            # 2. Classify and verify each business.
            for i, business in enumerate(businesses, 1):
                tags = business.get("tags", {})
                business_name = tags.get("name", "N/A")
                website_url = tags.get("website")

                self.log(f"({i}/{len(businesses)}) Processing: {business_name}")

                # a. Initial classification based on OSM data.
                status, social_type = classify_website(website_url)

                if status in ['NO_WEBSITE', 'SOCIAL_ONLY']:
                    # b. Secondary verification for candidates.
                    self.log(f"  -> Candidate. Performing secondary web verification...")
                    evidence_url, verifier_error = verify_business_website(business_name, city)
                    if verifier_error:
                        self.log(f"  -> WARNING: {verifier_error}")

                    # If no custom website was found, add to leads list.
                    if not evidence_url:
                        self.log(f"  -> SUCCESS: No custom website found. Adding to leads.")
                        lead = {
                            "business_name": business_name,
                            "city_region": city,
                            "keyword": keyword,
                            "phone": tags.get("phone") or tags.get("contact:phone"),
                            "osm_website": website_url,
                            "final_status": status,
                            "social_type": social_type,
                            "evidence_url": "", # Will be empty unless we want to log directory links
                        }
                        qualified_leads.append(lead)
                    else:
                        self.log(f"  -> Discarded. Found potential custom website: {evidence_url}")
                else:
                    self.log("  -> Discarded. Business has a custom website in OSM.")

            # 3. Export the results.
            if qualified_leads:
                self.log(f"Found {len(qualified_leads)} qualified leads. Exporting to CSV...")
                file_path, export_error = export_to_csv(qualified_leads, city, keyword)
                if export_error:
                    self.log(f"ERROR: Could not export CSV: {export_error}")
                else:
                    self.log(f"SUCCESS! Results saved to: {file_path}")
            else:
                self.log("Search complete. No qualified leads were found.")

        except Exception as e:
            self.log(f"An unexpected error occurred: {e}")
        finally:
            # 4. Re-enable the UI when the process is finished.
            self.ui.set_ui_state(False)

    def log(self, message):
        """
        Logs a message to the UI's status area from any thread.
        """
        # Safely schedule the UI update on the main thread.
        self.root.after(0, self.ui.log_message, message)


def main():
    """
    Main function to initialize and run the application.
    """
    root = tk.Tk()
    app = AppController(root)
    root.mainloop()

if __name__ == "__main__":
    main()
