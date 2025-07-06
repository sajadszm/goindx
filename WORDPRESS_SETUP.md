# WordPress Site Setup for Desktop Manager

For the WordPress Desktop Manager application to connect to your WordPress website, your site needs to have the REST API enabled and accessible. You also need a secure method for authentication.

## 1. Ensure REST API is Enabled and Accessible

*   The WordPress REST API is enabled by default on most modern WordPress installations (WordPress 4.7+).
*   Your site's REST API base URL is typically `https://yourdomain.com/wp-json/`.
*   **Permalink Settings**: Ensure your permalinks are set to something other than "Plain". Go to `Settings > Permalinks` in your WordPress admin and choose any option like "Post name". "Plain" permalinks can cause issues with REST API routing.
*   **Security Plugins**: Some security plugins (e.g., Wordfence, iThemes Security) or firewall configurations might restrict access to the REST API or specific endpoints. If you encounter connection issues, check your security plugin settings:
    *   Look for options related to REST API access, XML-RPC (though this app uses REST API, some plugins bundle settings), or blocking of unauthorized requests.
    *   You may need to whitelist the IP address from which you are running the desktop application if IP-based restrictions are in place, though this is less common for dynamic IPs.
*   **Hosting Provider**: Some hosting providers might have their own security layers that could interfere with REST API access. If issues persist, check with your host.
*   **Plugin Conflicts**: Rarely, other plugins might interfere with REST API functionality.

## 2. Authentication Methods

The desktop application supports connecting via Application Passwords (recommended) or Basic Authentication over HTTPS.

### A. Application Passwords (Recommended)

Application Passwords are the most secure way to grant API access to applications like this desktop manager. They are specific to an application and can be easily revoked without affecting your main user password.

**Requirements:**
*   WordPress 5.6 or newer (Application Passwords are a core feature).
*   If using an older version of WordPress, you can install the [Application Passwords plugin](https://wordpress.org/plugins/application-passwords/) by George Stephanis.

**How to Generate an Application Password:**

1.  Log in to your WordPress admin dashboard.
2.  Go to **Users > Profile**.
3.  Scroll down to the "Application Passwords" section.
    *   If you don't see this section, ensure your WordPress version is 5.6+ or the plugin is installed and activated.
4.  Enter a name for the new application password in the "New Application Password Name" field (e.g., "Desktop Manager" or "My WP App").
5.  Click the "**Add New Application Password**" button.
6.  A new password will be generated and displayed **only once**. It will look something like `xxxx xxxx xxxx xxxx xxxx xxxx`.
7.  **Copy this password immediately** and store it securely. You will not be able to see it again.
8.  In the desktop application, when adding a new site:
    *   Enter your WordPress **Username**.
    *   Enter the **Application Password** you just generated into the "Application Password" field.

### B. Basic Authentication (Less Secure)

Basic Authentication uses your main WordPress username and password. This method is less secure for API access because it exposes your primary credentials. **It should only be used if Application Passwords are not available and always over a secure HTTPS connection.**

**Requirements:**
*   Your website must be served over **HTTPS**. Sending Basic Auth credentials over unencrypted HTTP is a major security risk.
*   Some hosting environments or security plugins might disable Basic Authentication for the REST API. If it doesn't work, you might see 401 errors or other authentication failures.

**How to Use Basic Authentication:**

1.  In the desktop application, when adding a new site:
    *   Enter your WordPress **Username**.
    *   Enter your main WordPress **Password** into the "Application Password" field. (The field is labeled "Application Password" to encourage its use, but it will work for Basic Auth with your main password too).

## 3. REST API Prefix

*   The standard WordPress REST API prefix is `wp-json`. (e.g., `https://yourdomain.com/wp-json/wp/v2/posts`).
*   Some security plugins or custom setups might change this prefix.
*   The desktop application allows you to specify a custom REST API prefix if needed when adding or editing a site connection. If your site uses a non-standard prefix, ensure you enter it correctly.

## Troubleshooting Connection Issues

*   **Check URL**: Ensure the Site URL is correct (e.g., `https://example.com`, not `https://example.com/wp-admin`).
*   **HTTPS**: Always use HTTPS for security.
*   **Credentials**: Double-check your username and password/application password.
*   **User Role**: The user whose credentials you are using must have appropriate permissions to access the desired REST API endpoints (e.g., to read/write posts, manage WooCommerce, etc.). Typically, an Administrator or Editor role is required for full management.
*   **REST API Disabled**: If you receive errors indicating the API is disabled or endpoints are not found, verify that the REST API is not being blocked by a plugin or server configuration. You can test this by trying to access an endpoint like `https://yourdomain.com/wp-json/` in your browser (it should show some JSON data).

By following these steps, you should be able to securely connect the WordPress Desktop Manager to your websites.
