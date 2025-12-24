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
        self.ui = AppUI(root, self.start_search_thread)

    def start_search_thread(self, city, keyword):
        """
        Starts the search process in a new thread to keep the UI responsive.
        """
        self.ui.set_ui_state(True)
        self.ui.status_area.configure(state='normal')
        self.ui.status_area.delete('1.0', tk.END)
        self.ui.status_area.configure(state='disabled')

        thread = threading.Thread(target=self.run_search, args=(city, keyword))
        thread.daemon = True
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
                return
            if not businesses:
                self.log("No businesses found matching the criteria in OSM.")
                return

            self.log(f"Found {len(businesses)} potential businesses. Now classifying and verifying...")

            qualified_leads = []
            for i, business in enumerate(businesses, 1):
                tags = business.get("tags", {})
                business_name = tags.get("name", "N/A")
                website_url = tags.get("website")

                self.log(f"({i}/{len(businesses)}) Processing: {business_name}")

                # a. Initial classification, now with redirect resolution.
                status, platform = classify_website(website_url, resolve_redirects=True)

                # --- FIX 2: DIRECTORY VS SOCIAL MISCLASSIFICATION ---
                # Businesses are now only candidates if they have NO website or a SOCIAL ONLY website.
                # Directory sites are now correctly excluded from the final output.
                if status in ['NO_WEBSITE', 'SOCIAL_ONLY']:
                    self.log(f"  -> Candidate (Status: {status}). Performing secondary web verification...")
                    evidence_url, verifier_error = verify_business_website(business_name, city)
                    if verifier_error:
                        self.log(f"  -> WARNING: {verifier_error}")

                    if not evidence_url:
                        self.log(f"  -> SUCCESS: Verification passed. Adding to leads.")
                        lead = {
                            "business_name": business_name, "city_region": city, "keyword": keyword,
                            "phone": tags.get("phone") or tags.get("contact:phone"),
                            "osm_website": website_url, "final_status": status,
                            "social_type": platform, "evidence_url": "",
                        }
                        qualified_leads.append(lead)
                    else:
                        self.log(f"  -> Discarded. Verifier found a potential custom website: {evidence_url}")
                else:
                    self.log(f"  -> Discarded. Initial classification was '{status}'.")

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
            self.ui.set_ui_state(False)

    def log(self, message):
        """
        Logs a message to the UI's status area from any thread.
        """
        self.root.after(0, self.ui.log_message, message)


def main():
    root = tk.Tk()
    app = AppController(root)
    root.mainloop()

if __name__ == "__main__":
    main()
