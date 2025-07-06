// src/renderer/App.tsx
import React, { useState, useMemo } from 'react';
import { HashRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { ThemeProvider, createTheme, CssBaseline, PaletteMode } from '@mui/material';
import MainLayout from '@/renderer/components/Layout/MainLayout';
import ErrorBoundary from '@/renderer/components/ErrorBoundary/ErrorBoundary';
import DashboardPage from '@/renderer/pages/DashboardPage';
import SitesPage from '@/renderer/pages/SitesPage';
import PostsPage from '@/renderer/pages/PostsPage';
import PagesPage from '@/renderer/pages/PagesPage';
import WooCommercePage from '@/renderer/pages/WooCommercePage';
import PluginsPage from '@/renderer/pages/PluginsPage';
import ThemesPage from '@/renderer/pages/ThemesPage';
import SettingsPage from '@/renderer/pages/SettingsPage';

// Function to create theme based on mode
const getAppTheme = (mode: PaletteMode) => createTheme({
  palette: {
    mode,
    ...(mode === 'light'
      ? {
          // Light theme specific palette
          primary: { main: '#0073aa' }, // WordPress blue
          secondary: { main: '#d54e21' }, // WordPress orange-ish
          background: { default: '#f0f0f1', paper: '#ffffff' },
          text: { primary: '#1d2327', secondary: '#555d66'}
        }
      : {
          // Dark theme specific palette
          primary: { main: '#008ec2' }, // Lighter WordPress blue for dark mode
          secondary: { main: '#e6602e' }, // Lighter WordPress orange for dark mode
          background: { default: '#1e1e1e', paper: '#2d2d2d' }, // Dark backgrounds
          text: { primary: '#e0e0e0', secondary: '#aaaaaa'}
        }),
  },
  typography: {
    fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif',
    h4: { fontWeight: 600 },
    h5: { fontWeight: 600 },
    h6: { fontWeight: 600 },
  },
  components: {
    MuiButton: {
      styleOverrides: {
        root: { textTransform: 'none', borderRadius: '3px' },
      },
    },
    MuiAppBar: {
      styleOverrides: {
        colorPrimary: {
          backgroundColor: mode === 'light' ? '#1d2327' : '#3c434a', // Darker for light, slightly lighter for dark
          color: '#fff',
        }
      }
    },
    MuiDrawer: {
        styleOverrides: {
            paper: {
                backgroundColor: mode === 'light' ? '#ffffff' : '#2d2d2d',
                color: mode === 'light' ? '#1d2327' : '#e0e0e0',
            }
        }
    }
  },
});


const App: React.FC = () => {
  // TODO: Persist theme mode preference (e.g., using electron-store)
  const [themeMode, setThemeMode] = useState<PaletteMode>('light');

  const toggleTheme = () => {
    setThemeMode((prevMode) => (prevMode === 'light' ? 'dark' : 'light'));
  };

  // useMemo is important here to prevent theme regeneration on every render
  const theme = useMemo(() => getAppTheme(themeMode), [themeMode]);

  return (
    <ThemeProvider theme={theme}>
      <CssBaseline />
      <Router>
        <MainLayout toggleTheme={toggleTheme} currentThemeMode={themeMode}>
          <ErrorBoundary fallbackMessage="A critical error occurred in the application content. Please try reloading.">
            <Routes>
              <Route path="/" element={<DashboardPage />} />
              <Route path="/sites" element={<SitesPage />} />
            <Route path="/posts" element={<PostsPage />} />
            <Route path="/pages" element={<PagesPage />} />
            <Route path="/woocommerce" element={<WooCommercePage />} />
            <Route path="/plugins" element={<PluginsPage />} />
            <Route path="/themes" element={<ThemesPage />} />
            <Route path="/settings" element={<SettingsPage />} />
            {/* Redirect any unknown paths to dashboard */}
            <Route path="*" element={<Navigate to="/" replace />} />
          </Routes>
        </ErrorBoundary>
        </MainLayout>
      </Router>
    </ThemeProvider>
  );
};

export default App;
