// src/renderer/pages/ThemesPage.tsx
import React from 'react';
import { Container, Typography, Paper } from '@mui/material';

const ThemesPage: React.FC = () => {
  return (
    <Container maxWidth="lg" sx={{ mt: 4, mb: 4 }}>
      <Paper sx={{ p: 3 }}>
        <Typography variant="h4" component="h1" gutterBottom>
          Theme Management
        </Typography>
        <Typography variant="body1">
          Install, activate, and delete themes.
        </Typography>
        {/* Placeholder for theme list and management actions */}
      </Paper>
    </Container>
  );
};

export default ThemesPage;
