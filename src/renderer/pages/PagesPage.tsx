// src/renderer/pages/PagesPage.tsx
import React from 'react';
import { Container, Typography, Paper } from '@mui/material';

const PagesPage: React.FC = () => {
  return (
    <Container maxWidth="lg" sx={{ mt: 4, mb: 4 }}>
      <Paper sx={{ p: 3 }}>
        <Typography variant="h4" component="h1" gutterBottom>
          Pages Management
        </Typography>
        <Typography variant="body1">
          View, create, edit, and delete pages for the selected WordPress site.
        </Typography>
        {/* Placeholder for pages list, filters, and add new button */}
      </Paper>
    </Container>
  );
};

export default PagesPage;
