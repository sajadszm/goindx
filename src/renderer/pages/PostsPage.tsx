// src/renderer/pages/PostsPage.tsx
import React, { useState, useEffect, useCallback } from 'react';
import { WPPost, SiteConfig, WPPostCreatePayload, SiteSessionAuth } from '@/common/types'; // Added SiteSessionAuth
import { useSession } from '@/renderer/context/SessionContext'; // Import useSession
import PostList from '@/renderer/components/PostManagement/PostList';
import PostForm from '@/renderer/components/PostManagement/PostForm';
import {
  Container,
  Typography,
  CircularProgress,
  Alert,
  Box,
  Paper,
} from '@mui/material';

type ViewMode = 'list' | 'form';

const PostsPage: React.FC = () => {
  const { activeSite, sessionAuth } = useSession(); // Get active site and session auth from context

  const [posts, setPosts] = useState<WPPost[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [viewMode, setViewMode] = useState<ViewMode>('list');
  const [editingPost, setEditingPost] = useState<WPPost | null>(null);

  const fetchPosts = useCallback(async () => {
    if (!activeSite || !sessionAuth) {
      setPosts([]);
      // ProtectedRoute should prevent this page from rendering if !activeSite or !sessionAuth
      // However, as a safeguard:
      if (!activeSite) setError("No active site selected. Please log in via the Login page.");
      else if (!sessionAuth) setError("No active session. Please log in again.");
      return;
    }

    setIsLoading(true);
    setError(null);
    try {
      if (window.electronAPI) {
        const result = await window.electronAPI.invoke(
            'posts:get-all',
            activeSite.id,
            sessionAuth, // Pass sessionAuth
            { context: 'edit', per_page: 20 } // Requesting 'edit' context for raw content
        );
        if (result.success) {
          setPosts(result.data || []);
        } else {
          setError(result.message || `Failed to fetch posts for ${activeSite.name}.`);
          setPosts([]);
        }
      } else {
        setError("Electron API not available.");
        setPosts([]);
      }
    } catch (err: any) {
      setError(err.message || 'An unexpected error occurred while fetching posts.');
      setPosts([]);
    } finally {
      setIsLoading(false);
    }
  }, [activeSite, sessionAuth]);

  useEffect(() => {
    // Fetch posts when activeSite or sessionAuth changes (and are valid)
    if (activeSite && sessionAuth) {
      fetchPosts();
    } else {
      setPosts([]); // Clear posts if no active site or session
    }
  }, [activeSite, sessionAuth, fetchPosts]);

  const handleCreatePost = () => {
    if (!activeSite || !sessionAuth) {
        setError("Cannot create post: No active site session. Please log in.");
        return;
    }
    setEditingPost(null);
    setViewMode('form');
  };

  const handleEditPost = (post: WPPost) => {
     if (!activeSite || !sessionAuth) {
        setError("Cannot edit post: No active site session. Please log in.");
        return;
    }
    setEditingPost(post);
    setViewMode('form');
  };

  const handleCancelForm = () => {
    setEditingPost(null);
    setViewMode('list');
  };

  const handleFormSubmit = async (payload: WPPostCreatePayload | (WPPostCreatePayload & {id: number})) => {
    if (!activeSite || !sessionAuth) {
      return { success: false, message: "No active site session. Please log in.", data: undefined };
    }
    setIsLoading(true);
    const ipcChannel = 'id' in payload ? 'posts:update' : 'posts:create';
    const ipcArgs = 'id' in payload
        ? [activeSite.id, sessionAuth, payload.id, payload]
        : [activeSite.id, sessionAuth, payload];

    let result = { success: false, message: "Submission failed", data: undefined };
    try {
      if (window.electronAPI) {
        result = await window.electronAPI.invoke(ipcChannel, ...ipcArgs);
        if (result.success) {
          fetchPosts();
          setViewMode('list');
          setEditingPost(null);
        }
        // Error message from result will be shown by PostForm
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
    if (!activeSite || !sessionAuth) {
        setError("Cannot delete post: No active site session. Please log in.");
        return;
    }
    if (!window.confirm('Are you sure you want to delete this post? This might be permanent depending on site settings.')) {
      return;
    }
    setIsLoading(true);
    setError(null);
    try {
      if (window.electronAPI) {
        const result = await window.electronAPI.invoke('posts:delete', activeSite.id, sessionAuth, postId, false);
        if (result.success) {
          fetchPosts();
        } else {
          setError(result.message || 'Failed to delete post.');
        }
      } else {
         setError('Electron API not available.');
      }
    } catch (err: any) {
      setError(err.message || 'An unexpected error occurred during deletion.');
    } finally {
      setIsLoading(false);
    }
  };

  // If ProtectedRoute is working, activeSite and sessionAuth should exist here.
  // But as a fallback or for clarity:
  if (!activeSite || !sessionAuth) {
    return (
        <Container maxWidth="xl" sx={{ mt: 2, mb: 4 }}>
            <Alert severity="warning">
                Please select a site and log in via the 'Login' page to manage posts.
            </Alert>
        </Container>
    );
  }

  return (
    <Container maxWidth="xl" sx={{ mt: 2, mb: 4 }}>
      <Paper elevation={1} sx={{ p: 2, mb: 3 }}>
        <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 2 }}>
          <Typography variant="h5" component="h1">
            Post Management for: {activeSite.name || activeSite.url}
          </Typography>
          {/* Site selector dropdown is removed, site is now from SessionContext */}
        </Box>
      </Paper>

      {error && <Alert severity="error" sx={{ mb: 2, whiteSpace: 'pre-wrap' }} onClose={() => setError(null)}>{error}</Alert>}

      {viewMode === 'list' ? (
        <PostList
          posts={posts}
          onEditPost={handleEditPost}
          onDeletePost={handleDeletePost}
          onCreatePost={handleCreatePost}
          isLoading={isLoading}
          activeSite={activeSite} // Pass activeSite for display purposes in PostList
        />
      ) : (
        <PostForm
          post={editingPost}
          activeSite={activeSite} // Pass activeSite for display/context in PostForm
          onSubmit={handleFormSubmit}
          onCancel={handleCancelForm}
          isLoading={isLoading}
        />
      )}
    </Container>
  );
};

export default PostsPage;
