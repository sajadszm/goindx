// src/main/main.ts
import { app, BrowserWindow, ipcMain, dialog } from 'electron';
import path from 'path';
import url from 'url';
import { siteStore } from './services/siteStore';
import { wordpressConnector } from './services/wordpressConnector';
import { SiteConfig, SiteSessionAuth, WPPostCreatePayload, WPPostUpdatePayload } from '@/common/types';

// --- Global Error Handling for Main Process ---
process.on('uncaughtException', (error) => {
  console.error('[Main Process] Uncaught Exception:', error.name, error.message);
  console.error(error.stack);
  if (!isDev) {
    dialog.showErrorBox('Unhandled Exception', `${error.name}: ${error.message}\n\n${error.stack || 'No stack available.'}`);
  }
});

process.on('unhandledRejection', (reason, promise) => {
  console.error('[Main Process] Unhandled Rejection at:', promise, 'reason:', reason);
  if (reason instanceof Error) {
    console.error(reason.stack);
  }
  if (!isDev) {
    dialog.showErrorBox('Unhandled Rejection', `Reason: ${String(reason)}\n\nSee console for more details.`);
  }
});

let mainWindow: BrowserWindow | null;
const isDev = process.env.NODE_ENV === 'development';

function createWindow() {
  mainWindow = new BrowserWindow({
    width: 1200,
    height: 800,
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
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
        pathname: path.join(__dirname, '../renderer/index.html'),
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

app.on('activate', () => {
  if (BrowserWindow.getAllWindows().length === 0) {
    createWindow();
  }
});

// --- IPC Handlers ---

ipcMain.handle('my-invokable-ipc', async (event, ...args) => {
  console.log('[IPC RECV] my-invokable-ipc - Args:', args);
  return { reply: `Main process received: ${args.join(', ')}` };
});

// --- Site Configuration IPC Handlers ---
ipcMain.handle('sites:add', async (event, siteData: Pick<SiteConfig, 'url' | 'name'>) => {
  console.log(`[IPC RECV] sites:add - URL: ${siteData.url}, Name: ${siteData.name}`);
  try {
    const newSite = siteStore.addSite(siteData);
    console.log(`[IPC INFO] sites:add - Site configured successfully: ${newSite.id} (${newSite.url})`);
    return { success: true, message: `Site '${newSite.name || newSite.url}' configured successfully.`, site: newSite };
  } catch (error: any) {
    console.error(`[IPC ERROR] sites:add - Failed for ${siteData.url}:`, error.message);
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
    console.error(`[IPC ERROR] sites:get-all:`, error.message);
    return { success: false, message: error.message || 'Failed to retrieve sites.', sites: [] };
  }
});

ipcMain.handle('sites:get-by-id', async (event, id: string) => {
  console.log(`[IPC RECV] sites:get-by-id - ID: ${id}`);
  try {
    const site = siteStore.getSiteById(id);
    if (site) {
      console.log(`[IPC INFO] sites:get-by-id - Found site: ${site.name || site.url}`);
      return { success: true, site };
    } else {
      console.warn(`[IPC WARN] sites:get-by-id - Site with ID ${id} not found.`);
      return { success: false, message: `Site with ID ${id} not found.`, site: null };
    }
  } catch (error: any) {
    console.error(`[IPC ERROR] sites:get-by-id - Failed for ID ${id}:`, error.message);
    return { success: false, message: error.message || `Failed to retrieve site ${id}.`, site: null };
  }
});

ipcMain.handle('sites:update', async (event, id: string, siteData: Pick<SiteConfig, 'name'>) => {
  console.log(`[IPC RECV] sites:update - ID: ${id}, New Name: ${siteData.name}`);
  try {
    const updatedSite = siteStore.updateSite(id, siteData);
    if (updatedSite) {
      console.log(`[IPC INFO] sites:update - Site ${id} updated successfully. New name: ${updatedSite.name}`);
      return { success: true, message: 'Site name updated successfully.', site: updatedSite };
    }
    console.warn(`[IPC WARN] sites:update - Site with ID ${id} not found for update.`);
    return { success: false, message: `Site with ID ${id} not found for update.`, site: null };
  } catch (error: any) {
    console.error(`[IPC ERROR] sites:update - Failed for ID ${id}:`, error.message);
    return { success: false, message: error.message || `Failed to update site ${id}.`, site: null };
  }
});

ipcMain.handle('sites:delete', async (event, id: string) => {
  console.log(`[IPC RECV] sites:delete - ID: ${id}`);
  try {
    const deleted = siteStore.deleteSite(id);
    if (deleted) {
      console.log(`[IPC INFO] sites:delete - Site ${id} deleted.`);
      return { success: true, message: 'Site deleted successfully.' };
    } else {
      console.warn(`[IPC WARN] sites:delete - Site ${id} not found for deletion.`);
      return { success: false, message: `Site with ID ${id} not found for deletion.` };
    }
  } catch (error: any) {
    console.error(`[IPC ERROR] sites:delete - Failed for ID ${id}:`, error.message);
    return { success: false, message: error.message || `Failed to delete site ${id}.` };
  }
});

// --- Authentication IPC Handlers ---
ipcMain.handle('auth:login-to-site', async (event, site: Pick<SiteConfig, 'url'>, auth: SiteSessionAuth) => {
  console.log(`[IPC RECV] auth:login-to-site - URL: ${site.url}, Username: ${auth.username}`);
  try {
    const result = await wordpressConnector.testConnection(site, auth);
    if (result.success) {
      console.log(`[IPC INFO] auth:login-to-site - Login test successful for ${site.url}`);
    } else {
      console.warn(`[IPC WARN] auth:login-to-site - Login test failed for ${site.url}: ${result.message}`);
    }
    return result;
  } catch (error: any) {
    console.error(`[IPC ERROR] auth:login-to-site - Failed for ${site.url}:`, error.message);
    return { success: false, message: error.message || 'Failed to test login credentials.' };
  }
});

// --- WordPress Data Fetching IPC Handlers (Now with Session Auth) ---
ipcMain.handle('wp:fetch-data', async (event, siteId: string, authDetails: SiteSessionAuth, endpoint: string, params?: Record<string, any>) => {
  console.log(`[IPC RECV] wp:fetch-data - Site: ${siteId}, User: ${authDetails.username}, Endpoint: ${endpoint}`);
  try {
    const data = await wordpressConnector.get(siteId, authDetails, endpoint, params);
    console.log(`[IPC INFO] wp:fetch-data - Success for site ${siteId}, endpoint ${endpoint}`);
    return { success: true, data };
  } catch (error: any) {
    console.error(`[IPC ERROR] wp:fetch-data - Site: ${siteId}, Endpoint: ${endpoint}:`, error.message);
    return { success: false, message: error.message || `Failed to fetch data from ${endpoint}.` };
  }
});

// --- IPC Handlers for Posts (Now with Session Auth) ---
ipcMain.handle('posts:get-all', async (event, siteId: string, authDetails: SiteSessionAuth, params?: Record<string, any>) => {
  console.log(`[IPC RECV] posts:get-all - Site ID: ${siteId}, User: ${authDetails.username}, Params: ${JSON.stringify(params)}`);
  try {
    const posts = await wordpressConnector.getPosts(siteId, authDetails, params);
    console.log(`[IPC INFO] posts:get-all - Found ${posts.length} posts for site ${siteId}.`);
    return { success: true, data: posts };
  } catch (error: any) {
    console.error(`[IPC ERROR] posts:get-all - Failed for site ${siteId}:`, error.message);
    return { success: false, message: error.message || 'Failed to fetch posts.' };
  }
});

ipcMain.handle('posts:get-one', async (event, siteId: string, authDetails: SiteSessionAuth, postId: number, params?: Record<string, any>) => {
  console.log(`[IPC RECV] posts:get-one - Site ID: ${siteId}, User: ${authDetails.username}, Post ID: ${postId}`);
  try {
    const post = await wordpressConnector.getPost(siteId, authDetails, postId, params);
    return { success: true, data: post };
  } catch (error: any) {
    console.error(`[IPC ERROR] posts:get-one - Site: ${siteId}, Post: ${postId}:`, error.message);
    return { success: false, message: error.message || `Failed to fetch post ${postId}.` };
  }
});

ipcMain.handle('posts:create', async (event, siteId: string, authDetails: SiteSessionAuth, postData: WPPostCreatePayload) => {
  console.log(`[IPC RECV] posts:create - Site ID: ${siteId}, User: ${authDetails.username}`);
  try {
    const newPost = await wordpressConnector.createPost(siteId, authDetails, postData);
    return { success: true, data: newPost, message: 'Post created successfully.' };
  } catch (error: any) {
    console.error(`[IPC ERROR] posts:create - Site: ${siteId}:`, error.message);
    return { success: false, message: error.message || 'Failed to create post.' };
  }
});

ipcMain.handle('posts:update', async (event, siteId: string, authDetails: SiteSessionAuth, postId: number, postData: Partial<Omit<WPPostUpdatePayload, 'id'>>) => {
  console.log(`[IPC RECV] posts:update - Site ID: ${siteId}, User: ${authDetails.username}, Post ID: ${postId}`);
  try {
    const updatedPost = await wordpressConnector.updatePost(siteId, authDetails, postId, postData);
    return { success: true, data: updatedPost, message: 'Post updated successfully.' };
  } catch (error: any) {
    console.error(`[IPC ERROR] posts:update - Site: ${siteId}, Post: ${postId}:`, error.message);
    return { success: false, message: error.message || `Failed to update post ${postId}.` };
  }
});

ipcMain.handle('posts:delete', async (event, siteId: string, authDetails: SiteSessionAuth, postId: number, force?: boolean) => {
  console.log(`[IPC RECV] posts:delete - Site ID: ${siteId}, User: ${authDetails.username}, Post ID: ${postId}`);
  try {
    const result = await wordpressConnector.deletePost(siteId, authDetails, postId, force);
    return { success: true, data: result, message: `Post ${result.previous.status === 'trash' ? 'moved to trash' : 'deleted permanently'}.` };
  } catch (error: any) {
    console.error(`[IPC ERROR] posts:delete - Site: ${siteId}, Post: ${postId}:`, error.message);
    return { success: false, message: error.message || `Failed to delete post ${postId}.` };
  }
});
