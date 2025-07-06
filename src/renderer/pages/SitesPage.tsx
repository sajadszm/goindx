// src/renderer/components/SiteManagement/SitesPage.tsx
import React, { useState, useEffect, useCallback } from 'react';
import { SiteConfig } from '@/common/types';
import SiteForm from '@/renderer/components/SiteManagement/SiteForm';
import SiteList from '@/renderer/components/SiteManagement/SiteList';
import { Container, Typography, CircularProgress, Alert, Box } from '@mui/material';

const SitesPage: React.FC = () => {
  const [sites, setSites] = useState<SiteConfig[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [editingSite, setEditingSite] = useState<SiteConfig | null>(null);
  const [formMessage, setFormMessage] = useState<{ type: 'success' | 'error', text: string } | null>(null);

  const fetchSites = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      if (window.electronAPI) {
        const result = await window.electronAPI.invoke('sites:get-all');
        if (result.success) {
          setSites(result.sites);
        } else {
          setError(result.message || 'Failed to fetch sites.');
        }
      } else {
        setError('Electron API not available. Cannot fetch sites.');
      }
    } catch (err: any) {
      setError(err.message || 'An unexpected error occurred while fetching sites.');
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchSites();
  }, [fetchSites]);

  const handleFormSubmit = async (siteData: Omit<SiteConfig, 'id'>): Promise<{success: boolean, message: string}> => {
    setIsLoading(true);
    setFormMessage(null);
    let resultMessage = { success: false, message: "Operation failed" };
    try {
      if (window.electronAPI) {
        const ipcChannel = editingSite ? 'sites:update' : 'sites:add';
        const payload = editingSite ? [editingSite.id, siteData] : [siteData];
        const result = await window.electronAPI.invoke(ipcChannel, ...payload);

        setFormMessage({ type: result.success ? 'success' : 'error', text: result.message });
        resultMessage = { success: result.success, message: result.message };

        if (result.success) {
          fetchSites(); // Refresh list
          setEditingSite(null); // Clear editing state
          // Clear form by resetting initialData of SiteForm (indirectly via key change or prop update)
        }
      } else {
        resultMessage = { success: false, message: 'Electron API not available.' };
        setFormMessage({ type: 'error', text: resultMessage.message });
      }
    } catch (err: any) {
      resultMessage = { success: false, message: err.message || 'An unexpected error occurred.' };
      setFormMessage({ type: 'error', text: resultMessage.message });
    } finally {
      setIsLoading(false);
    }
    return resultMessage;
  };

  const handleTestConnection = async (siteData: Omit<SiteConfig, 'id'>): Promise<{success: boolean, message: string, data?: any}> => {
    // This function is passed to SiteForm, which calls it.
    // SiteForm will display the message from this result.
    if (window.electronAPI) {
      // Need to provide a temporary ID for the test function if it's a new site
      const testData: SiteConfig = { ...siteData, id: editingSite?.id || 'temp-test-id' };
      return window.electronAPI.invoke('sites:test-connection', testData);
    }
    return { success: false, message: 'Electron API not available.' };
  };

  const handleEditSite = (site: SiteConfig) => {
    setEditingSite(site);
    setFormMessage(null); // Clear previous form messages
    // Scroll to form or highlight it
  };

  const handleDeleteSite = async (siteId: string) => {
    setIsLoading(true); // Could use a specific loading state for delete
    setError(null);
    setFormMessage(null);
    if (window.confirm('Are you sure you want to delete this site connection?')) {
      try {
        if (window.electronAPI) {
          const result = await window.electronAPI.invoke('sites:delete', siteId);
          if (result.success) {
            setFormMessage({ type: 'success', text: result.message || 'Site deleted successfully.' });
            fetchSites(); // Refresh list
          } else {
            setFormMessage({ type: 'error', text: result.message || 'Failed to delete site.' });
          }
        } else {
          setFormMessage({ type: 'error', text: 'Electron API not available.' });
        }
      } catch (err: any) {
        setFormMessage({ type: 'error', text: err.message || 'An unexpected error occurred.' });
      } finally {
        setIsLoading(false);
      }
    } else {
        setIsLoading(false);
    }
  };

  return (
    <Container maxWidth="lg" sx={{ mt: 4, mb: 4 }}>
      <Typography variant="h4" component="h1" gutterBottom>
        Manage WordPress Sites
      </Typography>

      {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}

      <SiteForm
        key={editingSite ? editingSite.id : 'new-site-form'} // Change key to reset form when editingSite changes
        onSubmit={handleFormSubmit}
        onTestConnection={handleTestConnection}
        initialData={editingSite || {}} // Pass empty object for new, or editingSite data
        isLoading={isLoading}
      />

      {formMessage && !editingSite && /* Show general form messages if not in edit mode where form has its own */ (
        <Alert severity={formMessage.type} sx={{ mt: 2, whiteSpace: 'pre-wrap' }}>
          {formMessage.text}
        </Alert>
      )}

      {isLoading && !sites.length && <CircularProgress sx={{ display: 'block', margin: '20px auto' }} />}

      {!isLoading && <SiteList sites={sites} onEdit={handleEditSite} onDelete={handleDeleteSite} />}
    </Container>
  );
};

export default SitesPage;
