// src/renderer/pages/PostsPage.tsx
import React, { useState, useEffect, useCallback } from 'react';
import { WPPost, SiteConfig, WPPostCreatePayload } from '@/common/types';
import PostList from '@/renderer/components/PostManagement/PostList';
import PostForm from '@/renderer/components/PostManagement/PostForm';
import {
  Container,
  Typography,
  CircularProgress,
  Alert,
  Box,
  Select,
  MenuItem,
  FormControl,
  InputLabel,
  Paper,
  SelectChangeEvent
} from '@mui/material';

type ViewMode = 'list' | 'form';

const PostsPage: React.FC = () => {
  const [posts, setPosts] = useState<WPPost[]>([]);
  const [configuredSites, setConfiguredSites] = useState<SiteConfig[]>([]);
  const [activeSite, setActiveSite] = useState<SiteConfig | null>(null);

  const [isLoading, setIsLoading] = useState(false);
  const [isLoadingSites, setIsLoadingSites] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [viewMode, setViewMode] = useState<ViewMode>('list'); // 'list' or 'form'
  const [editingPost, setEditingPost] = useState<WPPost | null>(null); // Post being edited or null for new

  // Fetch configured sites for the site selector
  const fetchConfiguredSites = useCallback(async () => {
    setIsLoadingSites(true);
    try {
      if (window.electronAPI) {
        const result = await window.electronAPI.invoke('sites:get-all');
        if (result.success && result.sites.length > 0) {
          setConfiguredSites(result.sites);
          // Auto-select the first site if none is active, or if activeSite is no longer in the list
          if (!activeSite || !result.sites.find((s: SiteConfig) => s.id === activeSite.id)) {
             setActiveSite(result.sites[0]);
          }
        } else if (result.success && result.sites.length === 0) {
            setConfiguredSites([]);
            setActiveSite(null);
            setPosts([]); // Clear posts if no sites
            setError("No sites configured. Please add a site in 'Manage Sites'.");
        } else {
          setError(result.message || 'Failed to fetch configured sites.');
        }
      }
    } catch (err: any) {
      setError(err.message || 'Error fetching sites.');
    } finally {
      setIsLoadingSites(false);
    }
  }, [activeSite]); // activeSite in dependency to re-evaluate if it's still valid

  useEffect(() => {
    fetchConfiguredSites();
  }, [fetchConfiguredSites]); // Run once on mount

  // Fetch posts for the active site
  const fetchPosts = useCallback(async () => {
    if (!activeSite) {
      setPosts([]);
      // setError('Please select a site to view posts.'); // Optional: show message or rely on PostList's message
      return;
    }
    setIsLoading(true);
    setError(null);
    try {
      if (window.electronAPI) {
        // Example params: ?per_page=20&orderby=date&order=desc&context=edit (to get raw content for editing)
        // For now, using defaults context=view
        const result = await window.electronAPI.invoke('posts:get-all', activeSite.id, { context: 'edit', per_page: 20 });
        if (result.success) {
          setPosts(result.data || []);
        } else {
          setError(result.message || `Failed to fetch posts for ${activeSite.name}.`);
          setPosts([]);
        }
      }
    } catch (err: any) {
      setError(err.message || 'An unexpected error occurred while fetching posts.');
      setPosts([]);
    } finally {
      setIsLoading(false);
    }
  }, [activeSite]);

  useEffect(() => {
    if (activeSite) {
      fetchPosts();
    } else {
        setPosts([]); // Clear posts if no site selected
    }
  }, [activeSite, fetchPosts]);

  const handleSiteChange = (event: SelectChangeEvent<string>) => {
    const siteId = event.target.value;
    const selected = configuredSites.find(s => s.id === siteId);
    if (selected) {
      setActiveSite(selected);
      setViewMode('list'); // Go back to list when site changes
      setEditingPost(null);
    }
  };

  const handleCreatePost = () => {
    setEditingPost(null);
    setViewMode('form');
  };

  const handleEditPost = (post: WPPost) => {
    setEditingPost(post);
    setViewMode('form');
  };

  const handleCancelForm = () => {
    setEditingPost(null);
    setViewMode('list');
  };

  const handleFormSubmit = async (payload: WPPostCreatePayload | (WPPostCreatePayload & {id: number})) => {
    if (!activeSite) {
      return { success: false, message: "No active site selected.", data: undefined };
    }
    setIsLoading(true); // Consider a specific isLoadingForm state
    const ipcChannel = 'id' in payload ? 'posts:update' : 'posts:create';
    const ipcArgs = 'id' in payload ? [activeSite.id, payload.id, payload] : [activeSite.id, payload];

    let result = { success: false, message: "Submission failed", data: undefined };
    try {
      if (window.electronAPI) {
        result = await window.electronAPI.invoke(ipcChannel, ...ipcArgs);
        if (result.success) {
          fetchPosts(); // Refresh post list
          setViewMode('list');
          setEditingPost(null);
        }
      } else {
        result.message = "Electron API not available.";
      }
    } catch (err: any) {
      result.message = err.message || "An unexpected error occurred.";
    } finally {
      setIsLoading(false);
    }
    return result;
  };

  const handleDeletePost = async (postId: number) => {
    if (!activeSite) return;
    if (!window.confirm('Are you sure you want to delete this post? This might be permanent depending on site settings.')) {
      return;
    }
    setIsLoading(true);
    setError(null);
    try {
      if (window.electronAPI) {
        const result = await window.electronAPI.invoke('posts:delete', activeSite.id, postId, false); // false = move to trash by default
        if (result.success) {
          fetchPosts(); // Refresh list
          // Optionally show a success message from result.message
        } else {
          setError(result.message || 'Failed to delete post.');
        }
      }
    } catch (err: any) {
      setError(err.message || 'An unexpected error occurred during deletion.');
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <Container maxWidth="xl" sx={{ mt: 2, mb: 4 }}> {/* Using xl for more space */}
      <Paper elevation={1} sx={{ p: 2, mb: 3 }}>
        <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 2 }}>
          <Typography variant="h5" component="h1">
            Post Management
          </Typography>
          {isLoadingSites ? (
            <CircularProgress size={24} />
          ) : configuredSites.length > 0 ? (
            <FormControl sx={{ minWidth: 250 }} size="small">
              <InputLabel id="active-site-select-label">Active WordPress Site</InputLabel>
              <Select
                labelId="active-site-select-label"
                value={activeSite?.id || ''}
                label="Active WordPress Site"
                onChange={handleSiteChange}
              >
                {configuredSites.map((site) => (
                  <MenuItem key={site.id} value={site.id}>
                    {site.name || site.url}
                  </MenuItem>
                ))}
              </Select>
            </FormControl>
          ) : (
            <Typography color="textSecondary">No sites configured.</Typography>
          )}
        </Box>
      </Paper>

      {error && <Alert severity="error" sx={{ mb: 2, whiteSpace: 'pre-wrap' }}>{error}</Alert>}

      {viewMode === 'list' ? (
        <PostList
          posts={posts}
          onEditPost={handleEditPost}
          onDeletePost={handleDeletePost}
          onCreatePost={handleCreatePost}
          isLoading={isLoading}
          activeSite={activeSite}
        />
      ) : (
        <PostForm
          post={editingPost}
          activeSite={activeSite}
          onSubmit={handleFormSubmit}
          onCancel={handleCancelForm}
          isLoading={isLoading} // You might want a more specific isLoadingForm state
        />
      )}
    </Container>
  );
};

export default PostsPage;
