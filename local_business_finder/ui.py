import tkinter as tk
from tkinter import scrolledtext

class AppUI:
    """
    Handles the graphical user interface for the application using Tkinter.
    """
    def __init__(self, root, start_callback=None):
        """
        Initializes the UI components.
        :param root: The root Tkinter window.
        :param start_callback: A function to call when the 'Start' button is clicked.
                               It should accept 'city' and 'keyword' as arguments.
        """
        self.root = root
        self.start_callback = start_callback
        self.root.title("Local Business Finder")

        # Set a minimum window size
        self.root.minsize(450, 350)

        # Main frame
        main_frame = tk.Frame(root, padx=10, pady=10)
        main_frame.pack(fill=tk.BOTH, expand=True)

        # Input frame
        input_frame = tk.Frame(main_frame)
        input_frame.pack(fill=tk.X, pady=(0, 10))

        tk.Label(input_frame, text="City / Region:").grid(row=0, column=0, sticky="w", pady=2)
        self.city_entry = tk.Entry(input_frame)
        self.city_entry.grid(row=0, column=1, sticky="ew", padx=(5, 0))
        self.city_entry.insert(0, "Austin, TX") # Default example text

        tk.Label(input_frame, text="Business Keyword:").grid(row=1, column=0, sticky="w", pady=2)
        self.keyword_entry = tk.Entry(input_frame)
        self.keyword_entry.grid(row=1, column=1, sticky="ew", padx=(5, 0))
        self.keyword_entry.insert(0, "plumber") # Default example text

        input_frame.grid_columnconfigure(1, weight=1)

        # Button frame
        button_frame = tk.Frame(main_frame)
        button_frame.pack(fill=tk.X)

        self.start_button = tk.Button(button_frame, text="Start", command=self.start_action)
        self.start_button.pack()

        # Status area
        status_frame = tk.Frame(main_frame)
        status_frame.pack(fill=tk.BOTH, expand=True, pady=(10, 0))

        tk.Label(status_frame, text="Status Log:").pack(anchor="w")
        self.status_area = scrolledtext.ScrolledText(status_frame, wrap=tk.WORD, height=10)
        self.status_area.pack(fill=tk.BOTH, expand=True)
        self.status_area.configure(state='disabled')

    def start_action(self):
        """
        Handles the 'Start' button click event.
        Retrieves inputs and calls the callback.
        """
        city = self.city_entry.get().strip()
        keyword = self.keyword_entry.get().strip()

        if not city or not keyword:
            self.log_message("ERROR: City/Region and Keyword cannot be empty.")
            return

        if self.start_callback:
            self.start_callback(city, keyword)

    def log_message(self, message):
        """
        Logs a message to the status text area.
        :param message: The string message to log.
        """
        self.status_area.configure(state='normal')
        self.status_area.insert(tk.END, message + "\n")
        self.status_area.configure(state='disabled')
        self.status_area.see(tk.END)
        # Force the UI to update to show the message immediately
        self.root.update_idletasks()

    def set_ui_state(self, is_running):
        """
        Enables or disables UI elements based on the application's running state.
        :param is_running: Boolean, True if the process is running, False otherwise.
        """
        state = tk.DISABLED if is_running else tk.NORMAL
        self.start_button.config(state=state)
        self.city_entry.config(state=state)
        self.keyword_entry.config(state=state)
