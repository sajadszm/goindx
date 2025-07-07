// src/renderer/pages/SettingsPage.tsx
import React from 'react';
import { Container, Typography, Paper, Box, Link } from '@mui/material'; // Added Box, Link
import { useTheme } from '@mui/material/styles'; // Added useTheme

const SettingsPage: React.FC = () => {
  const theme = useTheme(); // Get the current theme
  return (
    <Container maxWidth="lg" sx={{ mt: 4, mb: 4 }}>
      <Paper sx={{ p: 3 }}>
        <Typography variant="h4" component="h1" gutterBottom>
          Application Settings
        </Typography>
        <Typography variant="body1">
          Manage application-wide settings, such as theme, notifications, and API preferences.
        </Typography>
        {/* Placeholder for various application settings options */}

        <Box sx={{ mt: 4, pt: 3, borderTop: '1px solid', borderColor: 'divider' }}>
          <Typography variant="h5" component="h2" gutterBottom>
            About This Application
          </Typography>
          <Typography variant="body1" gutterBottom>
            WordPress Desktop Manager
          </Typography>
          <Typography variant="body2" color="textSecondary" gutterBottom>
            Version: {process.env.npm_package_version || '0.1.0'}
            {/* Note: process.env.npm_package_version is available in Node.js environments.
                For renderer, this needs to be exposed via preload or fetched via IPC if needed dynamically.
                For simplicity, hardcoding or leaving as is for now. electron-builder usually handles version in about panel.
            */}
          </Typography>
          <Typography variant="body1" sx={{ mt: 1 }}>
            Programmed by: Sajad Sameie
          </Typography>
          <Typography variant="body1">
            Source / Contact: <Link href="https://instagram.com/sajjad.sameie" target="_blank" rel="noopener noreferrer" color="primary">
              instagram.com/sajjad.sameie
            </Link>
          </Typography>
        </Box>
      </Paper>
    </Container>
  );
};

export default SettingsPage;
