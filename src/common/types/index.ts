// src/common/types/index.ts
export interface UserPreferences {
  theme: 'light' | 'dark';
  notificationsEnabled: boolean;
}

export interface SiteConfig {
  id: string; // Unique identifier (e.g., UUID or site URL hash, generated from URL)
  url: string; // Full URL to the WordPress site (e.g., https://example.com)
  name?: string; // Optional friendly name for the site
  // Credentials (username, password/appPassword) will now be handled per session, not stored here.
  // restApiPrefix will also be handled per session or assumed default 'wp-json'.
}

// To store session-specific authentication data (not persisted with SiteConfig)
export interface SiteSessionAuth {
  username: string;
  passwordOrAppPass: string; // User provides this at "login" for the site
  restApiPrefix?: string; // Can be configured per session or default to 'wp-json'
}


export interface WPUser {
  id: number;
  name: string;
  slug: string;
  avatar_urls?: Record<string, string>;
  // ... other user properties
}

// WordPress Post related types
// Based on common fields from WP REST API /wp/v2/posts
export interface WPPost {
  id: number;
  date: string; // ISO8601 date string
  date_gmt: string; // ISO8601 date string
  guid: { rendered: string };
  modified: string; // ISO8601 date string
  modified_gmt: string; // ISO8601 date string
  slug: string;
  status: 'publish' | 'future' | 'draft' | 'pending' | 'private' | 'trash';
  type: string; // 'post', 'page', or custom post type slug
  link: string;
  title: { rendered: string };
  content: { rendered: string; protected: boolean };
  excerpt: { rendered: string; protected: boolean };
  author: number; // User ID
  featured_media: number; // Media ID, 0 if none
  comment_status: 'open' | 'closed';
  ping_status: 'open' | 'closed';
  sticky: boolean;
  template: string;
  format: string; // e.g., 'standard', 'aside', 'chat', 'gallery', 'link', 'image', 'quote', 'status', 'video', 'audio'
  meta?: any; // Or define more strictly if needed
  categories: number[]; // Array of category IDs
  tags: number[]; // Array of tag IDs
  // _links: any; // WP REST API links object, can be defined more strictly if needed
  // Non-standard fields you might add for the app's use
  siteId?: string; // To associate with one of the configured sites in the app
}

// For creating or updating a post
// Fields are optional as per WP REST API flexibility (e.g., only updating title)
export interface WPPostCreatePayload {
  title?: string;
  content?: string;
  status?: 'publish' | 'future' | 'draft' | 'pending' | 'private';
  date?: string; // For scheduling: YYYY-MM-DDTHH:MM:SS in site's timezone
  date_gmt?: string; // For scheduling: YYYY-MM-DDTHH:MM:SS in GMT
  slug?: string;
  author?: number;
  excerpt?: string;
  featured_media?: number;
  comment_status?: 'open' | 'closed';
  ping_status?: 'open' | 'closed';
  sticky?: boolean;
  password?: string; // For password-protected posts
  categories?: number[];
  tags?: string | number[]; // Can be comma-separated string of names, or array of IDs
  // meta?: Record<string, any>;
}

export type WPPostUpdatePayload = WPPostCreatePayload & {
  id: number; // Required for updates
};
