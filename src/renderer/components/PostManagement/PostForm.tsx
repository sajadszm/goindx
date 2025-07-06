// src/renderer/components/PostManagement/PostForm.tsx
import React, { useState, useEffect } from 'react';
import { WPPost, WPPostCreatePayload, SiteConfig } from '@/common/types';
import {
  Box,
  Button,
  TextField,
  Typography,
  CircularProgress,
  Alert,
  Paper,
  FormControl,
  InputLabel,
  Select,
  MenuItem,
  SelectChangeEvent,
  Grid,
  IconButton, // Added IconButton
} from '@mui/material';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';

interface PostFormProps {
  // Expects either a post object for editing, or undefined for creating a new post.
  // `null` could indicate that no post is selected for editing, effectively same as undefined for new.
  post?: WPPost | null;
  activeSite: SiteConfig | null; // The site this post belongs to or will be created on
  onSubmit: (payload: WPPostCreatePayload | (WPPostCreatePayload & {id: number})) => Promise<{ success: boolean; message: string; data?: WPPost }>;
  onCancel: () => void; // To go back to the post list or clear the form
  isLoading?: boolean;
}

const PostForm: React.FC<PostFormProps> = ({
  post,
  activeSite,
  onSubmit,
  onCancel,
  isLoading,
}) => {
  const [title, setTitle] = useState('');
  const [content, setContent] = useState('');
  const [status, setStatus] = useState<'publish' | 'draft' | 'pending' | 'private'>('draft');
  const [excerpt, setExcerpt] = useState('');
  // Add more fields as needed: slug, categories, tags, featured_media etc.

  const [formMessage, setFormMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

  useEffect(() => {
    if (post) {
      setTitle(post.title.rendered || '');
      setContent(post.content.rendered || ''); // Note: This is rendered HTML. For editing, raw content is better.
                                            // The WP API can provide raw content if requested with context=edit.
                                            // For simplicity now, we'll use rendered, but this is a key point for improvement.

      // Ensure the status from the post is one of the allowed form statuses
      const allowedFormStatuses: Array<typeof status> = ['publish', 'draft', 'pending', 'private'];
      if (allowedFormStatuses.includes(post.status as any)) {
        setStatus(post.status as typeof status);
      } else {
        setStatus('draft'); // Default to 'draft' if post status is 'trash', 'future', etc.
      }
      setExcerpt(post.excerpt.rendered || '');
    } else {
      // Reset form for new post
      setTitle('');
      setContent('');
      setStatus('draft');
      setExcerpt('');
    }
    setFormMessage(null); // Clear message when post changes or form is reset
  }, [post]);

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    setFormMessage(null);

    if (!activeSite) {
      setFormMessage({ type: 'error', text: 'No active site selected.' });
      return;
    }
    if (!title.trim()) {
      setFormMessage({ type: 'error', text: 'Post title cannot be empty.' });
      return;
    }

    const payload: WPPostCreatePayload = {
      title,
      content, // For a real editor, this would be raw content, not rendered HTML.
      status,
      excerpt,
      // TODO: Add author, categories, tags, featured_media etc.
    };

    let result;
    if (post && post.id) {
      // Editing existing post
      result = await onSubmit({ ...payload, id: post.id });
    } else {
      // Creating new post
      result = await onSubmit(payload);
    }

    setFormMessage({ type: result.success ? 'success' : 'error', text: result.message });
    // If successful and creating new, parent might want to clear form or navigate.
    // If successful and editing, parent might just show success message.
    // The onCancel or a successful navigation would typically occur in the parent component (PostsPage).
  };

  if (!activeSite) {
    return (
      <Alert severity="warning" sx={{m:2}}>Please select an active WordPress site first.</Alert>
    );
  }

  return (
    <Paper elevation={2} sx={{ p: { xs: 2, md: 3 }, mt: 2 }}>
      <Box sx={{ display: 'flex', alignItems: 'center', mb: 2 }}>
        <IconButton onClick={onCancel} sx={{ mr: 1 }}>
          <ArrowBackIcon />
        </IconButton>
        <Typography variant="h5" component="h2">
          {post ? `Edit Post: ${post.title.rendered}` : 'Create New Post'}
        </Typography>
      </Box>
      <Typography variant="subtitle1" gutterBottom sx={{mb:2}}>
        For site: {activeSite.name || activeSite.url}
      </Typography>

      <form onSubmit={handleSubmit}>
        <Grid container spacing={2}>
          <Grid item xs={12}>
            <TextField
              label="Title"
              fullWidth
              value={title}
              onChange={(e) => setTitle(e.target.value)}
              required
              variant="outlined"
              disabled={isLoading}
            />
          </Grid>
          <Grid item xs={12}>
            <TextField
              label="Content"
              fullWidth
              multiline
              rows={10} // Simple textarea for now
              value={content}
              onChange={(e) => setContent(e.target.value)}
              variant="outlined"
              helperText="NOTE: This is a basic text editor. Rich text editor (TinyMCE/Block Editor like) to be implemented. For now, you can use HTML."
              disabled={isLoading}
            />
            {/* TODO: Replace with a Rich Text Editor (e.g., TinyMCE, TipTap, Slate.js) */}
          </Grid>
          <Grid item xs={12} sm={6}>
            <FormControl fullWidth variant="outlined" disabled={isLoading}>
              <InputLabel id="status-select-label">Status</InputLabel>
              <Select
                labelId="status-select-label"
                value={status}
                onChange={(e: SelectChangeEvent<typeof status>) => setStatus(e.target.value as typeof status)}
                label="Status"
              >
                <MenuItem value="publish">Publish</MenuItem>
                <MenuItem value="draft">Draft</MenuItem>
                <MenuItem value="pending">Pending Review</MenuItem>
                <MenuItem value="private">Private</MenuItem>
              </Select>
            </FormControl>
          </Grid>
          <Grid item xs={12}>
            <TextField
              label="Excerpt (Optional)"
              fullWidth
              multiline
              rows={3}
              value={excerpt}
              onChange={(e) => setExcerpt(e.target.value)}
              variant="outlined"
              disabled={isLoading}
            />
          </Grid>
          {/* TODO: Add fields for Categories, Tags, Featured Image etc. */}
          {/* These would require fetching categories/tags/media from the WP site */}
        </Grid>

        {formMessage && (
          <Alert severity={formMessage.type} sx={{ mt: 3, mb: 1, whiteSpace: 'pre-wrap' }}>
            {formMessage.text}
          </Alert>
        )}

        <Box sx={{ mt: 3, display: 'flex', justifyContent: 'flex-end', gap: 2 }}>
          <Button onClick={onCancel} variant="outlined" color="secondary" disabled={isLoading}>
            Cancel
          </Button>
          <Button
            type="submit"
            variant="contained"
            color="primary"
            disabled={isLoading}
            startIcon={isLoading ? <CircularProgress size={20} color="inherit" /> : null}
          >
            {isLoading ? (post ? 'Saving...' : 'Creating...') : (post ? 'Save Changes' : 'Create Post')}
          </Button>
        </Box>
      </form>
      <Typography variant="caption" display="block" sx={{mt: 2}}>
        Note: For full CPT/Custom Field support, this form will need to be made dynamic, potentially fetching the schema for the selected post type and rendering fields accordingly.
      </Typography>
    </Paper>
  );
};

export default PostForm;
