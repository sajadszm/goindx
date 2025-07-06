// src/main/services/wordpressConnector.ts
import axios, { AxiosInstance, AxiosError } from 'axios';
import { SiteConfig, WPUser, WPPost, WPPostCreatePayload, WPPostUpdatePayload } from '@/common/types';
import { siteStore } from './siteStore'; // Ensure siteStore is imported

// Define a type for expected WordPress API error responses
interface WPErrorResponse {
  code?: string;
  message?: string;
  data?: {
    status?: number;
    [key: string]: any;
  };
}

// Helper to create an Axios instance for a given site configuration
const createApiClient = (site: SiteConfig): AxiosInstance => {
  const baseURL = `${site.url.replace(/\/$/, '')}/${site.restApiPrefix || 'wp-json'}/`;

  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
  };

  let auth = undefined;
  if (site.username && site.applicationPassword) {
    // Prefer Application Password if available (works like Basic Auth header)
    auth = {
      username: site.username,
      password: site.applicationPassword,
    };
  }
  // Later, add JWT or other auth methods here

  return axios.create({
    baseURL,
    auth, // Axios handles Basic Auth encoding if username/password are provided
    headers,
    timeout: 15000, // 15 seconds timeout
  });
};

export const wordpressConnector = {
  testConnection: async (siteConfig: SiteConfig): Promise<{ success: boolean; message: string; data?: WPUser }> => {
    try {
      // Ensure URL has a protocol
      let url = siteConfig.url;
      if (!/^https?:\/\//i.test(url)) {
        url = 'https://' + url; // Default to HTTPS
      }
      const validatedSiteConfig = { ...siteConfig, url };

      const apiClient = createApiClient(validatedSiteConfig);

      // A good endpoint to test authentication is '/wp/v2/users/me'
      // It requires authentication and returns the current user's details.
      const response = await apiClient.get<WPUser>('wp/v2/users/me', {
        // Send a timestamp to try and bypass caching for tests
        params: { _: new Date().getTime() }
      });

      if (response.status === 200 && response.data && response.data.id) {
        return {
          success: true,
          message: `Successfully connected to ${validatedSiteConfig.url} as ${response.data.name || validatedSiteConfig.username}.`,
          data: response.data,
        };
      } else {
        return {
          success: false,
          message: `Connected, but failed to retrieve user data. Status: ${response.status}`,
        };
      }
    } catch (error) {
      let errorMessage = 'An unknown error occurred.';
      if (axios.isAxiosError(error)) {
        const axiosError = error as AxiosError;
        if (axiosError.response) {
          // The request was made and the server responded with a status code
          // that falls out of the range of 2xx
          console.error('WP Connection Error Response:', axiosError.response.data);
          const status = axiosError.response.status;
          const responseData = axiosError.response.data as WPErrorResponse; // Type assertion
          const serverMessage = responseData?.message;

          if (status === 401) {
            errorMessage = `Authentication failed. ${serverMessage || 'Please check username and password/application password.'}`;
          } else if (status === 403) {
            errorMessage = `Forbidden. ${serverMessage || 'The credentials may be correct but lack permissions for REST API access.'}`;
          } else if (status === 404) {
            errorMessage = `API endpoint not found (404). ${serverMessage || `Check site URL, REST API prefix ('${siteConfig.restApiPrefix || 'wp-json'}'), and ensure REST API is enabled.`}`;
          } else {
            errorMessage = serverMessage || `Server error: ${status} - ${axiosError.response.statusText}.`;
          }
        } else if (axiosError.request) {
          // The request was made but no response was received
          console.error('WP Connection Error Request:', axiosError.request);
          errorMessage = 'No response from server. Check site URL, internet connection, and if the site is online.';
          if (axiosError.code === 'ENOTFOUND') {
            errorMessage = `Site not found (DNS lookup failed). Check the URL: ${siteConfig.url}`;
          } else if (axiosError.code === 'ECONNREFUSED') {
            errorMessage = `Connection refused by server. Is WordPress running at ${siteConfig.url}?`;
          }
        } else {
          // Something happened in setting up the request that triggered an Error
          console.error('WP Connection Error Message:', axiosError.message);
          errorMessage = `Connection setup error: ${axiosError.message}.`;
        }
      } else if (error instanceof Error) {
        console.error('WP Connection Generic Error:', error);
        errorMessage = error.message;
      }
      return { success: false, message: errorMessage };
    }
  },

  // Example of a generic GET request
  get: async <T>(siteId: string, endpoint: string, params?: Record<string, any>): Promise<T> => {
    const site = siteStore.getSiteById(siteId);
    if (!site) throw new Error(`Site with ID ${siteId} not found.`);

    const apiClient = createApiClient(site);
    try {
      const response = await apiClient.get<T>(endpoint, { params });
      return response.data;
    } catch (error) {
      // Handle error appropriately, maybe re-throw a custom error
      console.error(`Error fetching data from ${site.url}/${endpoint}:`, error);
      const err = error as AxiosError<WPErrorResponse>; // Specify WPErrorResponse for AxiosError
      throw new Error(err.response?.data?.message || err.message || 'Failed to fetch data from WordPress');
    }
  },

  // Example of a generic POST request
  post: async <T>(siteId: string, endpoint: string, data: Record<string, any>): Promise<T> => {
    const site = siteStore.getSiteById(siteId);
    if (!site) throw new Error(`Site with ID ${siteId} not found.`);

    const apiClient = createApiClient(site);
    try {
      const response = await apiClient.post<T>(endpoint, data);
      return response.data;
    } catch (error) {
      console.error(`Error posting data to ${site.url}/${endpoint}:`, error);
      const err = error as AxiosError<WPErrorResponse>;
      throw new Error(err.response?.data?.message || err.message || 'Failed to post data to WordPress');
    }
  },

  put: async <T>(siteId: string, endpoint: string, data: Record<string, any>): Promise<T> => {
    const site = siteStore.getSiteById(siteId);
    if (!site) throw new Error(`Site with ID ${siteId} not found.`);
    const apiClient = createApiClient(site);
    try {
      const response = await apiClient.put<T>(endpoint, data);
      return response.data;
    } catch (error) {
      console.error(`Error updating data at ${site.url}/${endpoint}:`, error);
      const err = error as AxiosError<WPErrorResponse>;
      throw new Error(err.response?.data?.message || err.message || 'Failed to update data in WordPress');
    }
  },

  delete: async <T>(siteId: string, endpoint: string): Promise<T> => {
    const site = siteStore.getSiteById(siteId);
    if (!site) throw new Error(`Site with ID ${siteId} not found.`);
    const apiClient = createApiClient(site);
    try {
      // For delete, WP often returns the object that was deleted, or a specific structure.
      // The generic <T> might need to be { previous: YourObjectType, deleted: true } etc. for some endpoints.
      const response = await apiClient.delete<T>(endpoint, { params: { force: true } });
      return response.data;
    } catch (error) {
      console.error(`Error deleting resource at ${site.url}/${endpoint}:`, error);
      const err = error as AxiosError<WPErrorResponse>;
      throw new Error(err.response?.data?.message || err.message || 'Failed to delete resource in WordPress');
    }
  },

  // --- Post Specific Methods ---
  getPosts: async (siteId: string, params?: Record<string, any>): Promise<WPPost[]> => {
    // Default to fetch context=view, can be overridden by params
    const defaultParams = { context: 'view', ...params };
    return wordpressConnector.get<WPPost[]>(siteId, 'wp/v2/posts', defaultParams);
  },

  getPost: async (siteId: string, postId: number, params?: Record<string, any>): Promise<WPPost> => {
    const defaultParams = { context: 'view', ...params };
    return wordpressConnector.get<WPPost>(siteId, `wp/v2/posts/${postId}`, defaultParams);
  },

  createPost: async (siteId: string, postData: WPPostCreatePayload): Promise<WPPost> => {
    // Ensure status is provided, default to 'draft' if not.
    const payload = { status: 'draft', ...postData };
    return wordpressConnector.post<WPPost>(siteId, 'wp/v2/posts', payload);
  },

  updatePost: async (siteId: string, postId: number, postData: Partial<Omit<WPPostUpdatePayload, 'id'>>): Promise<WPPost> => {
    return wordpressConnector.put<WPPost>(siteId, `wp/v2/posts/${postId}`, postData);
  },

  // Deleting a post can mean moving to trash or permanent deletion.
  // WP REST API default for DELETE /wp/v2/posts/<id> is to trash.
  // To permanently delete, add ?force=true.
  // The response includes the trashed/deleted post object.
  deletePost: async (siteId: string, postId: number, force = false): Promise<{ deleted: boolean; previous: WPPost }> => {
    const site = siteStore.getSiteById(siteId);
    if (!site) throw new Error(`Site with ID ${siteId} not found.`);
    const apiClient = createApiClient(site);
    try {
      const response = await apiClient.delete<{ deleted?: boolean; previous: WPPost }>(`wp/v2/posts/${postId}`, { params: { force } });
      // If WP_TRASH is disabled on the WP site, `deleted` might not be in the response,
      // but `previous` (the deleted post object) usually is.
      // A 200 OK response generally means success.
      return { deleted: response.data.deleted || true, previous: response.data.previous };
    } catch (error) {
      console.error(`Error deleting post ${postId} from ${site.url}:`, error);
       const err = error as AxiosError<WPErrorResponse>; // Specify WPErrorResponse
      throw new Error(err.response?.data?.message || err.message || `Failed to delete post ${postId}`);
    }
  },
};

// Note on Application Passwords:
// To use Application Passwords:
// 1. Install the "Application Passwords" plugin on your WordPress site (if not part of core for your WP version).
//    Many modern WordPress versions (5.6+) have this built-in under Users > Profile > Application Passwords.
// 2. Generate a new Application Password. Give it a name (e.g., "DesktopApp").
// 3. Copy the generated password (e.g., "xxxx xxxx xxxx xxxx xxxx xxxx"). This is shown only once.
// 4. In this app, use your normal WordPress username and this generated Application Password as the password.
//    The `applicationPassword` field in `SiteConfig` is intended for this.
// Application Passwords are generally more secure than using your main password for API access
// because they can be individually revoked and don't grant login access to the WP admin dashboard.
// Basic Auth header format for Application Passwords is the same: `Authorization: Basic base64(username:application_password)`
// Axios handles the base64 encoding automatically when `auth: { username, password }` is provided.
