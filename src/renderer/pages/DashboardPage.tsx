// src/renderer/pages/DashboardPage.tsx
import React from 'react';
import { Container, Typography, Paper } from '@mui/material';

const DashboardPage: React.FC = () => {
  return (
    <Container maxWidth="lg" sx={{ mt: 4, mb: 4 }}>
      <Paper sx={{ p: 3 }}>
        <Typography variant="h4" component="h1" gutterBottom>
          Dashboard
        </Typography>
        <Typography variant="body1">
          Welcome to your WordPress Desktop Manager Dashboard. Site-specific statistics and summaries will appear here.
        </Typography>
        {/* Placeholder for dashboard widgets */}
      </Paper>
    </Container>
  );
};

export default DashboardPage;
