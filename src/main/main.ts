// src/main/main.ts
import { app, BrowserWindow, ipcMain, dialog } from 'electron';
import path from 'path';
import url from 'url';

// --- Global Error Handling for Main Process ---
process.on('uncaughtException', (error) => {
  console.error('[Main Process] Uncaught Exception:', error.name, error.message);
  console.error(error.stack);
  // dialog.showErrorBox('Unhandled Exception', `${error.name}: ${error.message}\n\n${error.stack}`);
  // app.quit(); // Consider if appropriate
});

process.on('unhandledRejection', (reason, promise) => {
  console.error('[Main Process] Unhandled Rejection at:', promise, 'reason:', reason);
  if (reason instanceof Error) {
    console.error(reason.stack);
  }
  // dialog.showErrorBox('Unhandled Rejection', `Reason: ${String(reason)}\n\nSee console for more details.`);
});


let mainWindow: BrowserWindow | null;
const isDev = process.env.NODE_ENV === 'development';

function createWindow() {
  mainWindow = new BrowserWindow({
    width: 1200,
    height: 800,
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'), // Correct path after compilation
      contextIsolation: true,
      nodeIntegration: false,
    },
  });

  if (isDev) {
    mainWindow.loadURL('http://localhost:9000');
    mainWindow.webContents.openDevTools();
  } else {
    mainWindow.loadURL(
      url.format({
        pathname: path.join(__dirname, '../renderer/index.html'), // Relative to dist/main
        protocol: 'file:',
        slashes: true,
      })
    );
  }

  mainWindow.on('closed', () => {
    mainWindow = null;
  });
}

app.on('ready', createWindow);

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') {
    app.quit();
  }
});

// --- Global Error Handling for Main Process ---
process.on('uncaughtException', (error) => {
  console.error('[Main Process] Uncaught Exception:', error.name, error.message);
  console.error(error.stack);
  // In a real app, you might want to inform the user via a dialog and possibly quit gracefully.
  // dialog.showErrorBox('Unhandled Exception', `${error.name}: ${error.message}\n\n${error.stack}`);
  // app.quit();
});

process.on('unhandledRejection', (reason, promise) => {
  console.error('[Main Process] Unhandled Rejection at:', promise, 'reason:', reason);
  if (reason instanceof Error) {
    console.error(reason.stack);
  }
  // dialog.showErrorBox('Unhandled Rejection', `Reason: ${reason}\n\nSee console for more details.`);
});


// Enhanced logging for existing IPC handlers (example for one, others would be similar)
ipcMain.handle('sites:add', async (event, siteData: Omit<SiteConfig, 'id' | 'restApiPrefix'> & { restApiPrefix?: string }) => {
  console.log(`[IPC RECV] sites:add - URL: ${siteData.url}`);
  try {
    const fullSiteDataForTest: SiteConfig = {
        ...siteData,
        id: 'temp-test-id',
        url: siteData.url,
        username: siteData.username,
        applicationPassword: siteData.applicationPassword,
        restApiPrefix: siteData.restApiPrefix || 'wp-json',
    };
    const connectionTest = await wordpressConnector.testConnection(fullSiteDataForTest);
    if (!connectionTest.success) {
      console.warn(`[IPC WARN] sites:add - Connection test failed for ${siteData.url}: ${connectionTest.message}`);
      return { success: false, message: `Connection test failed: ${connectionTest.message}`, site: null };
    }
    console.log(`[IPC INFO] sites:add - Connection test successful for ${siteData.url}`);

    const newSite = siteStore.addSite({
        ...siteData,
        restApiPrefix: siteData.restApiPrefix || 'wp-json',
    });
    console.log(`[IPC INFO] sites:add - Site added successfully: ${newSite.id} (${newSite.url})`);
    return { success: true, message: `Site ${newSite.name || newSite.url} added. ${connectionTest.message}`, site: newSite };
  } catch (error: any) {
    console.error(`[IPC ERROR] sites:add - Failed for ${siteData.url}:`, error.message, error.stack);
    return { success: false, message: error.message || 'Failed to add site.', site: null };
  }
});

app.on('activate', () => {
  if (BrowserWindow.getAllWindows().length === 0) {
    createWindow();
  }
});

// Placeholder for initial IPC, will be expanded
ipcMain.handle('my-invokable-ipc', async (event, ...args) => {
  console.log('IPC message received on main process:', args);
  return { reply: `Main process received: ${args.join(', ')}` };
});

// --- WordPress Site Management IPC Handlers ---
import { siteStore } from './services/siteStore';
import { wordpressConnector } from './services/wordpressConnector';
import { SiteConfig } from '@/common/types'; // Ensure this path is correct based on tsconfig

ipcMain.handle('sites:add', async (event, siteData: Omit<SiteConfig, 'id' | 'restApiPrefix'> & { restApiPrefix?: string }) => {
  console.log(`[IPC RECV] sites:add - URL: ${siteData.url}`);
  try {
    const fullSiteDataForTest: SiteConfig = {
        ...siteData,
        id: 'temp-test-id', // Temporary ID for testing, actual ID generated by siteStore
        url: siteData.url, // Ensure URL is present
        username: siteData.username,
        applicationPassword: siteData.applicationPassword,
        restApiPrefix: siteData.restApiPrefix || 'wp-json',
    };
    const connectionTest = await wordpressConnector.testConnection(fullSiteDataForTest);
    if (!connectionTest.success) {
      console.warn(`[IPC WARN] sites:add - Connection test failed for ${siteData.url}: ${connectionTest.message}`);
      return { success: false, message: `Connection test failed: ${connectionTest.message}`, site: null };
    }
    console.log(`[IPC INFO] sites:add - Connection test successful for ${siteData.url}`);

    const newSite = siteStore.addSite({
        ...siteData,
        restApiPrefix: siteData.restApiPrefix || 'wp-json',
    });
    console.log(`[IPC INFO] sites:add - Site added successfully: ${newSite.id} (${newSite.url})`);
    return { success: true, message: `Site ${newSite.name || newSite.url} added. ${connectionTest.message}`, site: newSite };
  } catch (error: any) {
    console.error(`[IPC ERROR] sites:add - Failed for ${siteData.url}:`, error.message, error.stack);
    return { success: false, message: error.message || 'Failed to add site.', site: null };
  }
});

ipcMain.handle('sites:get-all', async () => {
  console.log(`[IPC RECV] sites:get-all`);
  try {
    const sites = siteStore.getSites();
    console.log(`[IPC INFO] sites:get-all - Found ${sites.length} sites.`);
    return { success: true, sites };
  } catch (error: any) {
    console.error(`[IPC ERROR] sites:get-all:`, error.message, error.stack);
    return { success: false, message: error.message || 'Failed to retrieve sites.', sites: [] };
  }
});

ipcMain.handle('sites:get-by-id', async (event, id: string) => {
  try {
    const site = siteStore.getSiteById(id);
    return site ? { success: true, site } : { success: false, message: `Site with ID ${id} not found.`, site: null };
  } catch (error: any) {
    console.error(`Error getting site ${id}:`, error);
    return { success: false, message: error.message || `Failed to retrieve site ${id}.`, site: null };
  }
});

ipcMain.handle('sites:update', async (event, id: string, siteData: Partial<Omit<SiteConfig, 'id'>>) => {
  try {
    const updatedSite = siteStore.updateSite(id, siteData);
    if (updatedSite) {
      // Optionally re-test if sensitive data changed
      // const connectionTest = await wordpressConnector.testConnection(updatedSite);
      // if (!connectionTest.success) {
      //   return { success: false, message: `Site updated, but connection test failed: ${connectionTest.message}`, site: updatedSite };
      // }
      return { success: true, message: 'Site updated successfully.', site: updatedSite };
    }
    return { success: false, message: `Site with ID ${id} not found for update.`, site: null };
  } catch (error: any) {
    console.error(`Error updating site ${id}:`, error);
    return { success: false, message: error.message || `Failed to update site ${id}.`, site: null };
  }
});

ipcMain.handle('sites:delete', async (event, id: string) => {
  try {
    const deleted = siteStore.deleteSite(id);
    return deleted ? { success: true, message: 'Site deleted successfully.' } : { success: false, message: `Site with ID ${id} not found for deletion.` };
  } catch (error: any) {
    console.error(`Error deleting site ${id}:`, error);
    return { success: false, message: error.message || `Failed to delete site ${id}.` };
  }
});

ipcMain.handle('sites:test-connection', async (event, siteConfig: SiteConfig) => {
  try {
    // Ensure siteConfig has a default restApiPrefix if not provided
    const configToTest = {
        ...siteConfig,
        restApiPrefix: siteConfig.restApiPrefix || 'wp-json',
    };
    const result = await wordpressConnector.testConnection(configToTest);
    return result;
  } catch (error: any) {
    console.error('Error testing site connection:', error);
    return { success: false, message: error.message || 'Failed to test connection.' };
  }
});


// Example IPC handler for fetching generic data from a WordPress site
// This demonstrates how other features will interact with configured sites.
ipcMain.handle('wp:fetch-data', async (event, siteId: string, endpoint: string, params?: Record<string, any>) => {
  console.log(`[IPC] Received wp:fetch-data for site ${siteId}, endpoint ${endpoint}`);
  try {
    const data = await wordpressConnector.get(siteId, endpoint, params);
    console.log(`[IPC] Successfully fetched data for site ${siteId}, endpoint ${endpoint}`);
    return { success: true, data };
  } catch (error: any) {
    console.error(`[IPC] Error fetching data from endpoint ${endpoint} for site ${siteId}:`, error.message, error.stack);
    return { success: false, message: error.message || `Failed to fetch data from ${endpoint}.` };
  }
});

// --- Global Error Handling for Main Process ---
process.on('uncaughtException', (error) => {
  console.error('[Main Process] Uncaught Exception:', error.message);
  console.error(error.stack);
  // Optionally, notify the user via a dialog or log to a file, then exit gracefully.
  // For now, just logging. Consider app.quit() for critical errors after logging.
});

process.on('unhandledRejection', (reason, promise) => {
  console.error('[Main Process] Unhandled Rejection at:', promise, 'reason:', reason);
  if (reason instanceof Error) {
    console.error(reason.stack);
  }
  // Optionally, notify the user or log to a file.
});


// --- Enhance existing IPC handlers with more logging ---

// Example for sites:add (apply similar logging to other handlers)
ipcMain.handle('sites:add', async (event, siteData: Omit<SiteConfig, 'id' | 'restApiPrefix'> & { restApiPrefix?: string }) => {
  console.log('[IPC] Received sites:add with URL:', siteData.url);
  try {
    const fullSiteDataForTest: SiteConfig = {
        ...siteData,
        id: 'temp-test-id',
        url: siteData.url,
        username: siteData.username,
        applicationPassword: siteData.applicationPassword,
        restApiPrefix: siteData.restApiPrefix || 'wp-json',
    };
    const connectionTest = await wordpressConnector.testConnection(fullSiteDataForTest);
    if (!connectionTest.success) {
      console.warn('[IPC] sites:add - Connection test failed:', connectionTest.message);
      return { success: false, message: `Connection test failed: ${connectionTest.message}`, site: null };
    }
    console.log('[IPC] sites:add - Connection test successful.');

    const newSite = siteStore.addSite({
        ...siteData,
        restApiPrefix: siteData.restApiPrefix || 'wp-json',
    });
    console.log('[IPC] sites:add - Site added successfully:', newSite.id);
    return { success: true, message: `Site ${newSite.name || newSite.url} added. ${connectionTest.message}`, site: newSite };
  } catch (error: any) {
    console.error('[IPC] sites:add - Error adding site:', error.message, error.stack);
    return { success: false, message: error.message || 'Failed to add site.', site: null };
  }
});

// Minimal logging for other handlers for brevity in this example, can be expanded:
ipcMain.handle('sites:get-all', async () => {
  console.log('[IPC] Received sites:get-all');
  try {
    const sites = siteStore.getSites();
    return { success: true, sites };
  } catch (error: any) {
    console.error('[IPC] sites:get-all - Error:', error.message);
    return { success: false, message: error.message || 'Failed to retrieve sites.', sites: [] };
  }
});

ipcMain.handle('sites:delete', async (event, id: string) => {
  console.log('[IPC] Received sites:delete for ID:', id);
  try {
    const deleted = siteStore.deleteSite(id);
    return deleted ? { success: true, message: 'Site deleted successfully.' } : { success: false, message: `Site with ID ${id} not found for deletion.` };
  } catch (error: any) {
    console.error('[IPC] sites:delete - Error for ID', id, ':', error.message);
    return { success: false, message: error.message || `Failed to delete site ${id}.` };
  }
});

ipcMain.handle('sites:test-connection', async (event, siteConfig: SiteConfig) => {
  console.log('[IPC] Received sites:test-connection for URL:', siteConfig.url);
  try {
    const configToTest = { ...siteConfig, restApiPrefix: siteConfig.restApiPrefix || 'wp-json' };
    const result = await wordpressConnector.testConnection(configToTest);
    console.log('[IPC] sites:test-connection - Result:', result.success, result.message);
    return result;
  } catch (error: any) {
    console.error('[IPC] sites:test-connection - Error for URL', siteConfig.url, ':', error.message);
    return { success: false, message: error.message || 'Failed to test connection.' };
  }
});


// Posts IPC logging (example for posts:get-all)
ipcMain.handle('posts:get-all', async (event, siteId: string, params?: Record<string, any>) => {
  console.log(`[IPC] Received posts:get-all for site ${siteId}`);
  try {
    const posts = await wordpressConnector.getPosts(siteId, params);
    return { success: true, data: posts };
  } catch (error: any) {
    console.error(`[IPC] posts:get-all - Error for site ${siteId}:`, error.message);
    return { success: false, message: error.message || 'Failed to fetch posts.' };
  }
});
// Apply similar brief logging to other post handlers: posts:get-one, posts:create, posts:update, posts:delete

// --- IPC Handlers for Posts ---
import { WPPostCreatePayload, WPPostUpdatePayload } from '@/common/types';

ipcMain.handle('posts:get-all', async (event, siteId: string, params?: Record<string, any>) => {
  console.log(`[IPC RECV] posts:get-all - Site ID: ${siteId}, Params: ${JSON.stringify(params)}`);
  try {
    const posts = await wordpressConnector.getPosts(siteId, params);
    console.log(`[IPC INFO] posts:get-all - Found ${posts.length} posts for site ${siteId}.`);
    return { success: true, data: posts };
  } catch (error: any) {
    console.error(`[IPC ERROR] posts:get-all - Failed for site ${siteId}:`, error.message, error.stack);
    return { success: false, message: error.message || 'Failed to fetch posts.' };
  }
});

ipcMain.handle('posts:get-one', async (event, siteId: string, postId: number, params?: Record<string, any>) => {
  try {
    const post = await wordpressConnector.getPost(siteId, postId, params);
    return { success: true, data: post };
  } catch (error: any) {
    return { success: false, message: error.message || `Failed to fetch post ${postId}.` };
  }
});

ipcMain.handle('posts:create', async (event, siteId: string, postData: WPPostCreatePayload) => {
  try {
    const newPost = await wordpressConnector.createPost(siteId, postData);
    return { success: true, data: newPost, message: 'Post created successfully.' };
  } catch (error: any) {
    return { success: false, message: error.message || 'Failed to create post.' };
  }
});

ipcMain.handle('posts:update', async (event, siteId: string, postId: number, postData: Partial<Omit<WPPostUpdatePayload, 'id'>>) => {
  try {
    const updatedPost = await wordpressConnector.updatePost(siteId, postId, postData);
    return { success: true, data: updatedPost, message: 'Post updated successfully.' };
  } catch (error: any) {
    return { success: false, message: error.message || `Failed to update post ${postId}.` };
  }
});

ipcMain.handle('posts:delete', async (event, siteId: string, postId: number, force?: boolean) => {
  try {
    const result = await wordpressConnector.deletePost(siteId, postId, force);
    return { success: true, data: result, message: `Post ${result.previous.status === 'trash' ? 'moved to trash' : 'deleted permanently'}.` };
  } catch (error: any) {
    return { success: false, message: error.message || `Failed to delete post ${postId}.` };
  }
});
