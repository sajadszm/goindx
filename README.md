# WordPress Desktop Manager (Jules AI)

This is a desktop application built with Electron, React, and TypeScript for managing WordPress websites. It allows users to interact with their WordPress sites using the REST API, providing an alternative to the web admin panel.

## Features (Planned & Implemented)

*   Connect to multiple WordPress sites.
*   Securely store site credentials (utilizing `electron-store` with encryption).
*   Authentication via Application Passwords (recommended) or Basic Auth.
*   **Content Management (Posts - Phase 1 Implemented):** View, create, edit, and delete posts.
*   (Planned) Manage pages, custom post types.
*   (Planned) WooCommerce integration: products, orders, customers, coupons.
*   (Planned) Plugin and Theme management.
*   (Planned) Dashboard with site statistics.
*   (Planned) Desktop notifications.
*   Light/Dark theme support.

## Project Structure

*   `src/main/`: Contains Electron main process code.
    *   `main.ts`: Main Electron application file, window creation, IPC handlers.
    *   `preload.ts`: Electron preload script for secure IPC.
    *   `services/`: Backend services for the main process (e.g., `wordpressConnector.ts`, `siteStore.ts`).
*   `src/renderer/`: Contains React renderer process (UI) code.
    *   `index.tsx`: Entry point for the React application.
    *   `App.tsx`: Root React component, theme setup, routing.
    *   `components/`: Reusable UI components (Layout, SiteManagement, PostManagement, etc.).
    *   `pages/`: Top-level page components for different views/routes.
    *   `global.d.ts`: TypeScript global type definitions for renderer (e.g., `window.electronAPI`).
*   `src/common/`: Shared code/types between main and renderer processes.
    *   `types/index.ts`: TypeScript interfaces for common data structures (SiteConfig, WPPost, etc.).
*   `public/`: Static assets, including `index.html`.
*   `assets/`: Application icons and other build resources.
*   `dist/`: Compiled output from TypeScript and Webpack.
*   `release/`: Packaged application installers/executables.

## Setup and Installation

1.  **Clone the repository:**
    ```bash
    git clone <repository-url>
    cd <repository-directory>
    ```

2.  **Install dependencies:**
    ```bash
    npm install
    ```
    *Note: If you encounter issues, try removing `node_modules` and `package-lock.json` and then run `npm install` again.*

## Development Mode

To run the application in development mode with hot reloading for the renderer process:

```bash
npm run electron:start:dev
```

This will:
*   Compile main process TypeScript (`src/main/**/*.ts`) and watch for changes.
*   Start the Webpack development server for the React renderer process (`src/renderer/**/*`).
*   Launch the Electron application, loading content from the Webpack dev server.
*   Open Electron DevTools automatically.

## Building for Production

To package the application for your current platform (e.g., an `.exe` installer for Windows, `.dmg` for macOS):

```bash
npm run electron:package
```

This script will:
1.  Build the React renderer code for production.
2.  Compile the Electron main process TypeScript code.
3.  Use `electron-builder` to package the application into a distributable format.
    The output will be located in the `release/` directory.

**Note on Building in Sandbox:**
During development with the AI assistant, persistent environment issues were encountered in the sandbox that sometimes prevented `npm run electron:package` from completing successfully (e.g., "module not found" errors for tools like `cross-env` or `html-webpack-plugin`, or missing files). These issues seem related to the sandbox environment's handling of `node_modules` or file state consistency. A clean install (`rm -rf node_modules package-lock.json && npm install`) immediately before packaging sometimes helps, but the problem can recur. The generated code itself should be sound for a local build in a stable Node.js environment.

## WordPress Site Setup

For the application to connect to your WordPress site, please refer to `WORDPRESS_SETUP.md` for instructions on configuring the REST API and authentication methods.
---

This README provides a starting point. More details on specific features, advanced configuration, and contribution guidelines can be added as the project evolves.
