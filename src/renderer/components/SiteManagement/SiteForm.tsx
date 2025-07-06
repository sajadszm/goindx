// src/renderer/components/SiteManagement/SiteForm.tsx
import React, { useState, useEffect } from 'react';
import { SiteConfig } from '@/common/types';
import { Box, Button, TextField, Typography, CircularProgress, Alert, Paper } from '@mui/material';

interface SiteFormProps {
  onSubmit: (siteData: Omit<SiteConfig, 'id'>) => Promise<{success: boolean, message: string}>;
  onTestConnection?: (siteData: Omit<SiteConfig, 'id'>) => Promise<{success: boolean, message: string, data?: any}>;
  initialData?: Partial<SiteConfig>; // For editing
  isLoading?: boolean;
}

const SiteForm: React.FC<SiteFormProps> = ({ onSubmit, onTestConnection, initialData, isLoading }) => {
  const [name, setName] = useState(initialData?.name || '');
  const [url, setUrl] = useState(initialData?.url || '');
  const [username, setUsername] = useState(initialData?.username || '');
  const [applicationPassword, setApplicationPassword] = useState(initialData?.applicationPassword || '');
  const [restApiPrefix, setRestApiPrefix] = useState(initialData?.restApiPrefix || 'wp-json');

  const [message, setMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
  const [testingConnection, setTestingConnection] = useState(false);

  useEffect(() => {
    if (initialData) {
      setName(initialData.name || '');
      setUrl(initialData.url || '');
      setUsername(initialData.username || '');
      setApplicationPassword(initialData.applicationPassword || '');
      setRestApiPrefix(initialData.restApiPrefix || 'wp-json');
    }
  }, [initialData]);

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    setMessage(null);
    if (!url || !username || !applicationPassword) {
      setMessage({ type: 'error', text: 'URL, Username, and Application Password are required.' });
      return;
    }

    const siteData = { name, url, username, applicationPassword, restApiPrefix };
    const result = await onSubmit(siteData);
    setMessage({ type: result.success ? 'success' : 'error', text: result.message });
    if(result.success) {
        // Optionally clear form or handle success state
        // setName(''); setUrl(''); setUsername(''); setApplicationPassword(''); setRestApiPrefix('wp-json');
    }
  };

  const handleTestConnection = async () => {
    if (!onTestConnection) return;
    setMessage(null);
    setTestingConnection(true);
    if (!url || !username || !applicationPassword) {
      setMessage({ type: 'error', text: 'URL, Username, and Application Password are required to test.' });
      setTestingConnection(false);
      return;
    }
    const siteData = { name, url, username, applicationPassword, restApiPrefix };
    const result = await onTestConnection(siteData as SiteConfig); // Cast needed for test
    setMessage({ type: result.success ? 'success' : 'error', text: result.message });
    setTestingConnection(false);
  };

  return (
    <Paper elevation={3} sx={{ p: 3, mt: 2, mb: 4 }}>
      <Typography variant="h6" gutterBottom>
        {initialData?.id ? 'Edit Site Connection' : 'Add New WordPress Site'}
      </Typography>
      <Box component="form" onSubmit={handleSubmit} noValidate sx={{ mt: 1 }}>
        <TextField
          margin="normal"
          fullWidth
          label="Site Name (Optional)"
          value={name}
          onChange={(e) => setName(e.target.value)}
          disabled={isLoading || testingConnection}
        />
        <TextField
          margin="normal"
          required
          fullWidth
          label="WordPress Site URL (e.g., https://example.com)"
          value={url}
          onChange={(e) => setUrl(e.target.value)}
          disabled={isLoading || testingConnection}
        />
        <TextField
          margin="normal"
          required
          fullWidth
          label="WordPress Username"
          value={username}
          onChange={(e) => setUsername(e.target.value)}
          disabled={isLoading || testingConnection}
        />
        <TextField
          margin="normal"
          required
          fullWidth
          label="Application Password"
          type="password"
          value={applicationPassword}
          onChange={(e) => setApplicationPassword(e.target.value)}
          helperText="Use an Application Password from your WordPress user profile for security."
          disabled={isLoading || testingConnection}
        />
        <TextField
          margin="normal"
          fullWidth
          label="REST API Prefix"
          value={restApiPrefix}
          onChange={(e) => setRestApiPrefix(e.target.value)}
          helperText="Default is 'wp-json'. Change if your site uses a custom REST API prefix."
          disabled={isLoading || testingConnection}
        />

        {message && (
          <Alert severity={message.type} sx={{ mt: 2, mb: 1, whiteSpace: 'pre-wrap' }}>
            {message.text}
          </Alert>
        )}

        <Box sx={{ mt: 2, display: 'flex', gap: 2 }}>
          {onTestConnection && (
            <Button
              type="button"
              variant="outlined"
              onClick={handleTestConnection}
              disabled={isLoading || testingConnection}
              startIcon={testingConnection ? <CircularProgress size={20} /> : null}
            >
              {testingConnection ? 'Testing...' : 'Test Connection'}
            </Button>
          )}
          <Button
            type="submit"
            variant="contained"
            disabled={isLoading || testingConnection}
            startIcon={isLoading ? <CircularProgress size={20} /> : null}
          >
            {isLoading ? 'Saving...' : (initialData?.id ? 'Save Changes' : 'Add Site')}
          </Button>
        </Box>
      </Box>
    </Paper>
  );
};

export default SiteForm;
