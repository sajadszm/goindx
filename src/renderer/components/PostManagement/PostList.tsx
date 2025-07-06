// src/renderer/components/PostManagement/PostList.tsx
import React from 'react';
import { WPPost, SiteConfig } from '@/common/types';
import {
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Paper,
  Button,
  Box,
  Typography,
  Chip,
  Tooltip,
  IconButton,
} from '@mui/material';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import AddIcon from '@mui/icons-material/Add';

interface PostListProps {
  posts: WPPost[];
  onEditPost: (post: WPPost) => void;
  onDeletePost: (postId: number) => void;
  onCreatePost: () => void;
  isLoading?: boolean;
  activeSite?: SiteConfig | null;
}

const PostList: React.FC<PostListProps> = ({
  posts,
  onEditPost,
  onDeletePost,
  onCreatePost,
  isLoading,
  activeSite,
}) => {
  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString(undefined, {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    });
  };

  return (
    <Paper sx={{ p: 2, mt: 2 }}>
      <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
        <Typography variant="h6">
          Posts {activeSite ? `for ${activeSite.name || activeSite.url}` : ''}
        </Typography>
        <Button
          variant="contained"
          startIcon={<AddIcon />}
          onClick={onCreatePost}
          disabled={isLoading || !activeSite}
        >
          Add New Post
        </Button>
      </Box>
      <TableContainer>
        <Table stickyHeader aria-label="posts table">
          <TableHead>
            <TableRow>
              <TableCell>Title</TableCell>
              <TableCell>Status</TableCell>
              <TableCell>Date</TableCell>
              <TableCell align="right">Actions</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {isLoading && posts.length === 0 ? (
              <TableRow>
                <TableCell colSpan={4} align="center">
                  <Typography>Loading posts...</Typography>
                </TableCell>
              </TableRow>
            ) : !isLoading && posts.length === 0 && activeSite ? (
                <TableRow>
                    <TableCell colSpan={4} align="center">
                        <Typography>No posts found for this site. Click "Add New Post" to create one.</Typography>
                    </TableCell>
                </TableRow>
            ) : !isLoading && !activeSite ? (
                <TableRow>
                    <TableCell colSpan={4} align="center">
                        <Typography>Please select a site to view posts.</Typography>
                    </TableCell>
                </TableRow>
            ) : (
              posts.map((post) => (
                <TableRow hover key={post.id}>
                  <TableCell>
                    <Typography variant="subtitle2" component="div">
                      {post.title.rendered || '(no title)'}
                    </Typography>
                  </TableCell>
                  <TableCell>
                    <Chip label={post.status} size="small" color={post.status === 'publish' ? 'success' : 'default'} />
                  </TableCell>
                  <TableCell>{formatDate(post.date)}</TableCell>
                  <TableCell align="right">
                    <Tooltip title="Edit Post">
                      <IconButton onClick={() => onEditPost(post)} size="small" sx={{ mr: 0.5 }}>
                        <EditIcon fontSize="small" />
                      </IconButton>
                    </Tooltip>
                    <Tooltip title="Delete Post">
                      <IconButton onClick={() => onDeletePost(post.id)} size="small" color="error">
                        <DeleteIcon fontSize="small" />
                      </IconButton>
                    </Tooltip>
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </TableContainer>
      {/* TODO: Add Pagination if posts are many */}
    </Paper>
  );
};

export default PostList;
