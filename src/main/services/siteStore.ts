// src/main/services/siteStore.ts
import Store from 'electron-store';
import { SiteConfig } from '@/common/types'; // SiteConfig type is now simpler
import crypto from 'crypto';

// Define a schema for your store to ensure type safety and provide defaults.
// Note: electron-store doesn't strictly enforce this at runtime for all operations,
// but it's good for clarity and for `default` values.
interface StoreSchema {
  sites: SiteConfig[];
  // We need a unique, persistent key for encryption.
  // It's best if this is generated once and stored securely,
  // or derived from something unique to the user/app installation.
  // For simplicity here, we'll use a hardcoded key, but THIS IS NOT SECURE for a real app.
  // In a real app, you might generate this on first run and store it in the system keychain,
  // or use a user-provided master password to derive it.
  // encryptionKeyMaterial: string; // Example: store some material to derive key
}

// --- IMPORTANT SECURITY NOTE ---
// The 'encryptionKey' option in electron-store provides basic obfuscation but is not
// a robust security solution if the key is hardcoded or easily discoverable in the app's source.
// For production, consider more advanced key management (e.g., system keychain via node-keytar).
// For this example, we'll use a fixed key.
const ENCRYPTION_KEY = 'this-is-not-a-secure-key-for-prod'; // Replace with a proper key/management strategy

const store = new Store<StoreSchema>({
  defaults: {
    sites: [],
    // encryptionKeyMaterial: crypto.randomBytes(16).toString('hex') // Generate on first run example
  },
  // The 'encryptionKey' option encrypts the entire store file.
  // It should be a string of 16, 24, or 32 characters for AES-128, AES-192, or AES-256 respectively.
  // Or a Buffer of 16, 24, or 32 bytes.
  encryptionKey: ENCRYPTION_KEY,
  // It's good practice to name your config file
  name: 'wp-desktop-manager-config',
  // You can also specify a project name to avoid conflicts if you have multiple apps using electron-store
  // projectName: 'wp-desktop-manager',
});

// Function to generate a unique ID for a site (can be more sophisticated)
const generateSiteId = (url: string): string => {
  return crypto.createHash('sha256').update(url).digest('hex').substring(0, 16);
};

const MAX_SITES = 5;

export const siteStore = {
  addSite: (newSiteData: Omit<SiteConfig, 'id'>): SiteConfig => { // newSiteData now only contains url and optional name
    const sites = store.get('sites', []);

    if (sites.length >= MAX_SITES) {
      throw new Error(`Maximum number of sites (${MAX_SITES}) reached. Please remove a site to add a new one.`);
    }

    // Check if site with this URL already exists to prevent duplicates based on URL
    // Normalize URL slightly to avoid issues with/without trailing slash for uniqueness check
    const normalizedNewUrl = newSiteData.url.trim().replace(/\/$/, '');
    if (sites.some(s => s.url.trim().replace(/\/$/, '') === normalizedNewUrl)) {
      throw new Error(`Site with URL ${newSiteData.url} already exists.`);
    }

    const newSite: SiteConfig = {
      name: newSiteData.name, // Will be undefined if not provided, that's fine
      url: normalizedNewUrl, // Store normalized URL
      id: generateSiteId(normalizedNewUrl), // Generate ID based on normalized URL
    };
    sites.push(newSite);
    store.set('sites', sites);
    return newSite;
  },

  getSites: (): SiteConfig[] => {
    return store.get('sites', []);
  },

  getSiteById: (id: string): SiteConfig | undefined => {
    const sites = store.get('sites', []);
    return sites.find(site => site.id === id);
  },

  getSiteByUrl: (url: string): SiteConfig | undefined => {
    const sites = store.get('sites', []);
    const normalizedUrl = url.trim().replace(/\/$/, '');
    return sites.find(site => site.url === normalizedUrl);
  },

  // updateSite now only updates 'name' or 'url'. If URL changes, ID also changes.
  // This means update is more like a 'replace' if URL is modified.
  // A simpler update might only allow changing the 'name'.
  // For now, let's assume if URL changes, we might need to re-generate ID and re-validate uniqueness.
  // Or, more simply, prevent URL change via update, only allow name change.
  // Let's go with: URL change means it's effectively a new site config, so delete old and add new.
  // This update function will only update the 'name'.
  updateSite: (id: string, updatedSiteData: Pick<SiteConfig, 'name'>): SiteConfig | undefined => {
    const sites = store.get('sites', []);
    const siteIndex = sites.findIndex(site => site.id === id);
    if (siteIndex === -1) {
      // console.warn(`Site with ID ${id} not found for update.`);
      return undefined;
    }

    sites[siteIndex] = {
      ...sites[siteIndex],
      name: updatedSiteData.name || sites[siteIndex].name, // Update name, keep URL and ID
    };
    store.set('sites', sites);
    return sites[siteIndex];
  },

  // If a user wants to change a URL, they should delete the old site config and add a new one.
  // This keeps ID generation simple (based on URL).

  deleteSite: (id: string): boolean => {
    let sites = store.get('sites', []);
    const initialLength = sites.length;
    sites = sites.filter(site => site.id !== id);
    if (sites.length < initialLength) {
      store.set('sites', sites);
      return true;
    }
    return false;
  },

  // Clear all sites (useful for development/testing or a reset feature)
  clearAllSites: (): void => {
    store.set('sites', []);
  },
};

// Example of how to handle encryption key generation/storage more robustly (conceptual)
// async function getEncryptionKey(): Promise<string> {
//   const serviceName = 'WordPressDesktopManager';
//   const accountName = 'electron-store-encryption-key';
//   try {
//     const keytar = await import('keytar');
//     let key = await keytar.getPassword(serviceName, accountName);
//     if (!key) {
//       key = crypto.randomBytes(16).toString('hex'); // AES-128
//       await keytar.setPassword(serviceName, accountName, key);
//     }
//     return key;
//   } catch (error) {
//     console.warn('Keytar not available, using fallback insecure key. Install keytar for better security.', error);
//     // Fallback to a less secure method if keytar is not available or fails
//     // This could be a user-derived key or a hardcoded one (less ideal)
//     return 'fallback-insecure-encryption-key';
//   }
// }
// (async () => {
//   const key = await getEncryptionKey();
//   // Re-initialize store if key is fetched asynchronously (more complex setup)
//   // This example uses a synchronous key for simplicity with current electron-store API.
// })();

console.log('Site store initialized. Storage path:', store.path);
