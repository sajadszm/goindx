// src/renderer/components/SiteManagement/SiteForm.tsx
import React, { useState, useEffect } from 'react';
import { SiteConfig } from '@/common/types';
import { Box, Button, TextField, Typography, CircularProgress, Alert, Paper } from '@mui/material';

// SiteConfig is now just { id, url, name? }
interface SiteFormProps {
  // onSubmit now takes simpler data. For edit, only name can be changed. For add, url & name.
  onSubmit: (data: Pick<SiteConfig, 'url' | 'name'> | Pick<SiteConfig, 'name'>) => Promise<{success: boolean, message: string}>;
  initialData?: Partial<SiteConfig>; // For editing (id, url, name)
  isEditMode?: boolean; // Explicitly indicate if we are in edit mode
  isLoading?: boolean;
}

const SiteForm: React.FC<SiteFormProps> = ({ onSubmit, initialData, isEditMode = false, isLoading }) => {
  const [name, setName] = useState('');
  const [url, setUrl] = useState('');

  const [message, setMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

  useEffect(() => {
    if (initialData) {
      setName(initialData.name || '');
      setUrl(initialData.url || ''); // URL will be disabled in edit mode
    } else {
      // Reset for new site form
      setName('');
      setUrl('');
    }
  }, [initialData]);

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    setMessage(null);

    if (!isEditMode && !url.trim()) {
      setMessage({ type: 'error', text: 'Site URL is required.' });
      return;
    }
    // Basic URL validation (very simple, can be improved)
    if (!isEditMode && !url.match(/^(https?:\/\/)/i)) {
        setMessage({ type: 'error', text: 'Please enter a valid URL (e.g., https://example.com).' });
        return;
    }

    let siteDataToSubmit;
    if (isEditMode) {
        // In edit mode, we only submit the name for update
        siteDataToSubmit = { name: name.trim() || undefined }; // Send undefined if name is cleared, so store can keep old name or handle it.
                                                              // Or ensure name is required for edit if it was previously set.
                                                              // For simplicity, allow clearing the name.
    } else {
        // In add mode, submit URL and name
        siteDataToSubmit = { url: url.trim(), name: name.trim() || undefined };
    }

    const result = await onSubmit(siteDataToSubmit);
    setMessage({ type: result.success ? 'success' : 'error', text: result.message });

    if(result.success && !isEditMode) {
        // Clear form only if adding a new site was successful
        setName('');
        setUrl('');
    }
  };

  return (
    <Paper elevation={3} sx={{ p: 3, mt: 2, mb: 4 }}>
      <Typography variant="h6" gutterBottom>
        {isEditMode ? 'Edit Site Name' : 'Add New WordPress Site'}
      </Typography>
      <Box component="form" onSubmit={handleSubmit} noValidate sx={{ mt: 1 }}>
        <TextField
          margin="normal"
          required={!isEditMode} // URL is required only when adding
          fullWidth
          label="WordPress Site URL (e.g., https://example.com)"
          value={url}
          onChange={(e) => setUrl(e.target.value)}
          disabled={isLoading || isEditMode} // URL is disabled in edit mode
          helperText={isEditMode ? "URL cannot be changed after adding. To change URL, delete and re-add the site." : "Full URL of your WordPress site."}
        />
        <TextField
          margin="normal"
          fullWidth
          label="Friendly Name (Optional)"
          value={name}
          onChange={(e) => setName(e.target.value)}
          disabled={isLoading}
          helperText="A name to easily identify this site in the app."
        />

        {message && (
          <Alert severity={message.type} sx={{ mt: 2, mb: 1, whiteSpace: 'pre-wrap' }}>
            {message.text}
          </Alert>
        )}

        <Box sx={{ mt: 2, display: 'flex', justifyContent: 'flex-end', gap: 2 }}>
          {/* "Test Connection" button removed as per new flow */}
          <Button
            type="submit"
            variant="contained"
            disabled={isLoading}
            startIcon={isLoading ? <CircularProgress size={20} /> : null}
          >
            {isLoading ? 'Saving...' : (isEditMode ? 'Save Name' : 'Add Site')}
          </Button>
        </Box>
      </Box>
    </Paper>
  );
};

export default SiteForm;
