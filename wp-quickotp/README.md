# WP-QuickOTP

A commercial-grade WordPress plugin that provides passwordless, mobile-number + OTP login and registration for WooCommerce.

## Installation

1.  Download the plugin as a ZIP file.
2.  Go to your WordPress admin area and navigate to "Plugins > Add New".
3.  Click the "Upload Plugin" button.
4.  Select the ZIP file and click "Install Now".
5.  Activate the plugin.

## Setup

1.  Go to "WP Quick OTP" in your WordPress admin menu.
2.  **General Settings:** Enable the plugin and set the default country code and sender number.
3.  **SMS Providers:** Select your SMS provider (sms.ir or Melipayamak), enter your API credentials, and send a test SMS to ensure everything is working correctly.
4.  **Login/Registration:** Choose between a popup or a dedicated page for the login form and customize the form's text.
5.  **Checkout Integration:** Enable the "Require OTP at checkout" option to redirect non-logged-in users to the OTP page. You can also enable the "Auto-fill billing phone" option to automatically populate the billing phone field with the user's verified mobile number.
6.  **OTP Settings:** Configure the OTP length, expiry time, resend delay, max attempts, and other security settings.
7.  **Templates & Appearance:** Choose a template for the login form and customize its appearance.
8.  **Logs & Reports:** Enable logging to keep track of all OTP-related events.
9.  **Support & Tools:** Use the tools on this page to clear the plugin's cache or reset its settings.

## Developer Hooks

The plugin includes a number of action and filter hooks for developers to extend its functionality.

### Actions

*   `wpqo_after_login_success`
*   `wpqo_after_login_failure`
*   `wpqo_after_registration`
*   `wpqo_sms_send_success`
*   `wpqo_sms_send_error`
*   `wpqo_before_checkout_redirect`

### Filters

*   `wpqo_filter_otp_message`
*   `wpqo_filter_phone_normalize`
*   `wpqo_filter_redirect_url`
*   `wpqo_filter_template_output`
*   `wpqo_filter_checkout_validation`

## Note on Localization

The `.mo` file for the Persian translation could not be generated due to missing tools in the development environment. You will need to generate the `.mo` file from the `.po` file located in the `languages` directory. You can use a tool like [Poedit](https://poedit.net/) to do this.

## Changelog

### 2.0.0
*   Major refactoring and restructuring of the plugin.
*   Added a tabbed interface to the admin panel.
*   Added a popup login form.
*   Added a "Test SMS" button to the admin panel.
*   Added a "Logs & Reports" page to the admin panel.
*   Added a "Support & Tools" page to the admin panel.
*   Added a number of new settings to the admin panel.
*   Added a number of new developer hooks.
*   Improved the SMS provider integrations.
*   Improved the WooCommerce integration.

### 1.0.0
*   Initial release.
