// src/renderer/pages/LoginPage.tsx
import React, { useState, useEffect, FormEvent } from 'react';
import { useNavigate } from 'react-router-dom';
import { SiteConfig, SiteSessionAuth } from '@/common/types';
import { useSession } from '@/renderer/context/SessionContext';
import GuidanceModal from '@/renderer/components/GuidanceModal/GuidanceModal';
import {
  Container,
  Paper,
  Typography,
  Box,
  FormControl,
  InputLabel,
  Select,
  MenuItem,
  TextField,
  Button,
  CircularProgress,
  Alert,
  Link as MuiLink, // Renamed to avoid conflict with Router Link if used
  SelectChangeEvent,
} from '@mui/material';

const LoginPage: React.FC = () => {
  const navigate = useNavigate();
  const {
    loginToSite,
    isLoadingSession,
    sessionError,
    activeSite,
    siteForLoginAttempt, // The site chosen in dropdown, pending credential entry
    setSiteForLoginAttempt // Function to set the site chosen in dropdown
  } = useSession();

  const [configuredSites, setConfiguredSites] = useState<SiteConfig[]>([]);
  const [isLoadingSites, setIsLoadingSites] = useState(false);
  const [fetchSitesError, setFetchSitesError] = useState<string | null>(null);

  const [username, setUsername] = useState('');
  const [passwordOrAppPass, setPasswordOrAppPass] = useState('');

  const [isGuidanceModalOpen, setIsGuidanceModalOpen] = useState(false);

  // Fetch configured sites on mount
  useEffect(() => {
    const fetchSites = async () => {
      setIsLoadingSites(true);
      setFetchSitesError(null);
      try {
        if (window.electronAPI) {
          const result = await window.electronAPI.invoke('sites:get-all');
          if (result.success) {
            setConfiguredSites(result.sites || []);
            if (result.sites && result.sites.length > 0 && !siteForLoginAttempt) {
              // Pre-select first site if none is selected for login attempt yet
              // setSiteForLoginAttempt(result.sites[0]); // Let user explicitly select
            } else if (result.sites.length === 0) {
                setFetchSitesError("No sites configured. Please add a site via 'Manage Sites' first.");
            }
          } else {
            setFetchSitesError(result.message || 'Failed to fetch configured sites.');
          }
        } else {
          setFetchSitesError('Electron API not available.');
        }
      } catch (err: any) {
        setFetchSitesError(err.message || 'Error fetching sites.');
      } finally {
        setIsLoadingSites(false);
      }
    };
    fetchSites();
  }, [setSiteForLoginAttempt]); // siteForLoginAttempt removed from deps to avoid loop if pre-selecting

  // If already logged into a site, redirect to dashboard
  useEffect(() => {
    if (activeSite) {
      navigate('/'); // Navigate to dashboard or last visited page
    }
  }, [activeSite, navigate]);

  const handleSiteSelectionChange = (event: SelectChangeEvent<string>) => {
    const siteId = event.target.value;
    const selected = configuredSites.find(s => s.id === siteId);
    if (selected) {
      setSiteForLoginAttempt(selected);
      setUsername(''); // Clear username/password when site changes
      setPasswordOrAppPass('');
    } else {
      setSiteForLoginAttempt(null);
    }
  };

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault();
    if (!siteForLoginAttempt || !username || !passwordOrAppPass) {
      // SessionContext will show its own error if loginToSite is called,
      // but local validation can be more immediate.
      // For now, relying on loginToSite's error handling.
      return;
    }
    const authDetails: SiteSessionAuth = { username, passwordOrAppPass };
    // loginToSite from context will handle setting activeSite, sessionAuth, errors, and loading state
    await loginToSite(siteForLoginAttempt, authDetails);
    // Navigation on success is handled by the useEffect watching activeSite
  };

  return (
    <Container component="main" maxWidth="xs" sx={{ mt: 8 }}>
      <Paper elevation={6} sx={{ padding: 4, display: 'flex', flexDirection: 'column', alignItems: 'center' }}>
        <Typography component="h1" variant="h5">
          Site Login
        </Typography>

        <Button
            onClick={() => setIsGuidanceModalOpen(true)}
            variant="text"
            size="small"
            sx={{ mt: 1, mb: 1}}
        >
            Connection Guidance
        </Button>

        {fetchSitesError && <Alert severity="error" sx={{ width: '100%', mt: 1 }}>{fetchSitesError}</Alert>}

        <Box component="form" onSubmit={handleSubmit} noValidate sx={{ mt: 1, width: '100%' }}>
          <FormControl fullWidth margin="normal" disabled={isLoadingSites || configuredSites.length === 0}>
            <InputLabel id="site-select-label">Select Configured Site</InputLabel>
            <Select
              labelId="site-select-label"
              value={siteForLoginAttempt?.id || ''}
              label="Select Configured Site"
              onChange={handleSiteSelectionChange}
            >
              {isLoadingSites ? (
                <MenuItem value="" disabled><CircularProgress size={20} /> Loading sites...</MenuItem>
              ) : configuredSites.length === 0 ? (
                <MenuItem value="" disabled>No sites configured.</MenuItem>
              ) : (
                configuredSites.map((site) => (
                  <MenuItem key={site.id} value={site.id}>
                    {site.name || site.url}
                  </MenuItem>
                ))
              )}
            </Select>
          </FormControl>

          {siteForLoginAttempt && (
            <>
              <TextField
                margin="normal"
                required
                fullWidth
                id="username"
                label="WordPress Username"
                name="username"
                autoComplete="username"
                autoFocus
                value={username}
                onChange={(e) => setUsername(e.target.value)}
                disabled={isLoadingSession}
              />
              <TextField
                margin="normal"
                required
                fullWidth
                name="password"
                label="Password / Application Password"
                type="password"
                id="password"
                autoComplete="current-password"
                value={passwordOrAppPass}
                onChange={(e) => setPasswordOrAppPass(e.target.value)}
                disabled={isLoadingSession}
                helperText="Use your WP password or an Application Password (recommended)."
              />
              {sessionError && <Alert severity="error" sx={{ width: '100%', mt: 1 }}>{sessionError}</Alert>}
              <Button
                type="submit"
                fullWidth
                variant="contained"
                sx={{ mt: 3, mb: 2 }}
                disabled={isLoadingSession || !username || !passwordOrAppPass}
              >
                {isLoadingSession ? <CircularProgress size={24} color="inherit" /> : 'Login to Site'}
              </Button>
            </>
          )}
          <Typography variant="body2" align="center" sx={{ mt: 2 }}>
            Need to add or change site configurations?{' '}
            <MuiLink component="button" variant="body2" onClick={() => navigate('/sites')}>
              Manage Sites
            </MuiLink>
          </Typography>
        </Box>
      </Paper>
      <GuidanceModal open={isGuidanceModalOpen} onClose={() => setIsGuidanceModalOpen(false)} />
    </Container>
  );
};

export default LoginPage;
