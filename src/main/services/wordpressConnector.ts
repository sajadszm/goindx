// src/main/services/wordpressConnector.ts
import axios, { AxiosInstance, AxiosError } from 'axios';
import { SiteConfig, WPUser, WPPost, WPPostCreatePayload, WPPostUpdatePayload, SiteSessionAuth } from '@/common/types'; // Added SiteSessionAuth
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
// Now optionally takes credentials for session-based authentication.
const createApiClient = (
  site: Pick<SiteConfig, 'url'>, // Only needs URL from SiteConfig
  authDetails?: SiteSessionAuth   // Optional session auth details
): AxiosInstance => {
  // Use a default REST API prefix; this might become configurable per session later.
  const restApiPrefix = authDetails?.restApiPrefix || 'wp-json';
  const baseURL = `${site.url.replace(/\/$/, '')}/${restApiPrefix}/`;

  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
  };

  let auth = undefined;
  if (authDetails?.username && authDetails?.passwordOrAppPass) {
    auth = {
      username: authDetails.username,
      password: authDetails.passwordOrAppPass,
    };
  }

  return axios.create({
    baseURL,
    auth,
    headers,
    timeout: 15000,
  });
};

export const wordpressConnector = {
  // testConnection now needs explicit credentials for the test.
  // SiteConfig is used for URL and potentially other non-auth site details.
  testConnection: async (
    siteConfig: Pick<SiteConfig, 'url'>, // Only needs URL
    authDetails: SiteSessionAuth         // Requires session auth details for test
  ): Promise<{ success: boolean; message: string; data?: WPUser }> => {
    try {
      let url = siteConfig.url;
      if (!/^https?:\/\//i.test(url)) {
        url = 'https://' + url;
      }
      const siteForClient = { url }; // Use this for creating client and for messages

      // Create client WITH auth details for this test
      const apiClient = createApiClient(siteForClient, authDetails);

      const response = await apiClient.get<WPUser>('wp/v2/users/me', {
        params: { _: new Date().getTime() }
      });

      if (response.status === 200 && response.data && response.data.id) {
        return {
          success: true,
          message: `Successfully connected to ${siteForClient.url} as ${response.data.name || authDetails.username}.`,
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
            errorMessage = `API endpoint not found (404). ${serverMessage || `Check site URL, REST API prefix ('${authDetails.restApiPrefix || 'wp-json'}'), and ensure REST API is enabled.`}`;
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
  get: async <T>(
    siteId: string,
    authDetails: SiteSessionAuth, // Added authDetails
    endpoint: string,
    params?: Record<string, any>
  ): Promise<T> => {
    const site = siteStore.getSiteById(siteId);
    if (!site) throw new Error(`Site with ID ${siteId} not found.`);

    const apiClient = createApiClient(site, authDetails); // Pass authDetails
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
  post: async <T>(
    siteId: string,
    authDetails: SiteSessionAuth, // Added authDetails
    endpoint: string,
    data: Record<string, any>
  ): Promise<T> => {
    const site = siteStore.getSiteById(siteId);
    if (!site) throw new Error(`Site with ID ${siteId} not found.`);

    const apiClient = createApiClient(site, authDetails); // Pass authDetails
    try {
      const response = await apiClient.post<T>(endpoint, data);
      return response.data;
    } catch (error) {
      console.error(`Error posting data to ${site.url}/${endpoint}:`, error);
      const err = error as AxiosError<WPErrorResponse>;
      throw new Error(err.response?.data?.message || err.message || 'Failed to post data to WordPress');
    }
  },

  put: async <T>(
    siteId: string,
    authDetails: SiteSessionAuth, // Added authDetails
    endpoint: string,
    data: Record<string, any>
  ): Promise<T> => {
    const site = siteStore.getSiteById(siteId);
    if (!site) throw new Error(`Site with ID ${siteId} not found.`);
    const apiClient = createApiClient(site, authDetails); // Pass authDetails
    try {
      const response = await apiClient.put<T>(endpoint, data);
      return response.data;
    } catch (error) {
      console.error(`Error updating data at ${site.url}/${endpoint}:`, error);
      const err = error as AxiosError<WPErrorResponse>;
      throw new Error(err.response?.data?.message || err.message || 'Failed to update data in WordPress');
    }
  },

  delete: async <T>(
    siteId: string,
    authDetails: SiteSessionAuth, // Added authDetails
    endpoint: string
  ): Promise<T> => {
    const site = siteStore.getSiteById(siteId);
    if (!site) throw new Error(`Site with ID ${siteId} not found.`);
    const apiClient = createApiClient(site, authDetails); // Pass authDetails
    try {
      const response = await apiClient.delete<T>(endpoint, { params: { force: true } });
      return response.data;
    } catch (error) {
      console.error(`Error deleting resource at ${site.url}/${endpoint}:`, error);
      const err = error as AxiosError<WPErrorResponse>;
      throw new Error(err.response?.data?.message || err.message || 'Failed to delete resource in WordPress');
    }
  },

  // --- Post Specific Methods ---
  getPosts: async (siteId: string, authDetails: SiteSessionAuth, params?: Record<string, any>): Promise<WPPost[]> => {
    const defaultParams = { context: 'view', ...params }; // context=edit is better for raw data
    return wordpressConnector.get<WPPost[]>(siteId, authDetails, 'wp/v2/posts', defaultParams);
  },

  getPost: async (siteId: string, authDetails: SiteSessionAuth, postId: number, params?: Record<string, any>): Promise<WPPost> => {
    const defaultParams = { context: 'view', ...params };
    return wordpressConnector.get<WPPost>(siteId, authDetails, `wp/v2/posts/${postId}`, defaultParams);
  },

  createPost: async (siteId: string, authDetails: SiteSessionAuth, postData: WPPostCreatePayload): Promise<WPPost> => {
    const payload = { status: 'draft', ...postData };
    return wordpressConnector.post<WPPost>(siteId, authDetails, 'wp/v2/posts', payload);
  },

  updatePost: async (siteId: string, authDetails: SiteSessionAuth, postId: number, postData: Partial<Omit<WPPostUpdatePayload, 'id'>>): Promise<WPPost> => {
    return wordpressConnector.put<WPPost>(siteId, authDetails, `wp/v2/posts/${postId}`, postData);
  },

  deletePost: async (siteId: string, authDetails: SiteSessionAuth, postId: number, force = false): Promise<{ deleted: boolean; previous: WPPost }> => {
    const site = siteStore.getSiteById(siteId);
    if (!site) throw new Error(`Site with ID ${siteId} not found.`);
    // For deletePost, we call createApiClient directly as it's a specific implementation of delete.
    const apiClient = createApiClient(site, authDetails);
    try {
      const response = await apiClient.delete<{ deleted?: boolean; previous: WPPost }>(`wp/v2/posts/${postId}`, { params: { force } });
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
