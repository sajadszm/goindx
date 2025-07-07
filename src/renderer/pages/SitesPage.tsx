// src/renderer/components/SiteManagement/SitesPage.tsx
import React, { useState, useEffect, useCallback } from 'react';
import { SiteConfig } from '@/common/types';
import SiteForm from '@/renderer/components/SiteManagement/SiteForm';
import SiteList from '@/renderer/components/SiteManagement/SiteList';
import {
    Container,
    Typography,
    CircularProgress,
    Alert,
    Box,
    FormControl,        // Added
    InputLabel,       // Added
    Select,           // Added
    MenuItem,         // Added
    SelectChangeEvent,// Added
    Paper,            // Added
    Button            // Added Button
} from '@mui/material';

const SitesPage: React.FC = () => {
  const [sites, setSites] = useState<SiteConfig[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [editingSite, setEditingSite] = useState<Partial<SiteConfig> | null>(null); // For editing, holds site being edited
  const [isEditMode, setIsEditMode] = useState(false);
  // General page messages, e.g., after delete. Form has its own message state.
  const [pageMessage, setPageMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);


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

  const handleFormSubmit = async (data: Pick<SiteConfig, 'url' | 'name'> | Pick<SiteConfig, 'name'>): Promise<{success: boolean, message: string}> => {
    setIsLoading(true);
    setPageMessage(null); // Clear page-level messages
    let resultMessage = { success: false, message: "Operation failed" };

    try {
      if (!window.electronAPI) {
        throw new Error('Electron API not available.');
      }

      let result;
      if (isEditMode && editingSite && editingSite.id) {
        // We are editing, only name can be changed. 'data' will be Pick<SiteConfig, 'name'>
        result = await window.electronAPI.invoke('sites:update', editingSite.id, data as Pick<SiteConfig, 'name'>);
      } else {
        // We are adding a new site. 'data' will be Pick<SiteConfig, 'url' | 'name'>
        result = await window.electronAPI.invoke('sites:add', data as Pick<SiteConfig, 'url' | 'name'>);
      }

      resultMessage = { success: result.success, message: result.message };

      if (result.success) {
        fetchSites(); // Refresh list
        if (isEditMode) {
          setPageMessage({ type: 'success', text: result.message || 'Site name updated!' });
          setIsEditMode(false);
          setEditingSite(null);
        } else {
          // For add, SiteForm clears itself and shows message. No page level message needed here.
        }
      }
      // If not successful, SiteForm will display the error message from result.message
    } catch (err: any) {
      resultMessage = { success: false, message: err.message || 'An unexpected error occurred.' };
      // This error will be returned to SiteForm to display
    } finally {
      setIsLoading(false);
    }
    return resultMessage; // This result is used by SiteForm to display its own message
  };

  const handleEditSite = (site: SiteConfig) => {
    setEditingSite(site); // Set the full site object for initialData
    setIsEditMode(true);
    setPageMessage(null); // Clear previous page messages
    window.scrollTo(0, 0); // Scroll to top to see form
  };

  const handleAddNew = () => {
    setIsEditMode(false);
    setEditingSite(null); // Clear any existing editing state
    setPageMessage(null);
    window.scrollTo(0, 0); // Scroll to top to see form
  };


  const handleDeleteSite = async (siteId: string) => {
    setIsLoading(true);
    setPageMessage(null);
    if (window.confirm('Are you sure you want to delete this site configuration?')) {
      try {
        if (window.electronAPI) {
          const result = await window.electronAPI.invoke('sites:delete', siteId);
          if (result.success) {
            setPageMessage({ type: 'success', text: result.message || 'Site deleted successfully.' });
            fetchSites();
            if (editingSite?.id === siteId) { // If the deleted site was being edited
              setIsEditMode(false);
              setEditingSite(null);
            }
          } else {
            setPageMessage({ type: 'error', text: result.message || 'Failed to delete site.' });
          }
        } else {
          setPageMessage({ type: 'error', text: 'Electron API not available.' });
        }
      } catch (err: any) {
        setPageMessage({ type: 'error', text: err.message || 'An unexpected error occurred.' });
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
        Configure WordPress Sites (Max: 5)
      </Typography>
      <Typography variant="body2" color="textSecondary" sx={{mb: 2}}>
        Add the URL and a friendly name for your WordPress sites. You will log in to them in a separate step.
      </Typography>


      {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}
      {pageMessage && (
        <Alert severity={pageMessage.type} sx={{ mt: 1, mb: 2, whiteSpace: 'pre-wrap' }} onClose={() => setPageMessage(null)}>
          {pageMessage.text}
        </Alert>
      )}

      <SiteForm
        key={editingSite ? editingSite.id : 'new-site-form'}
        onSubmit={handleFormSubmit}
        initialData={editingSite || {}}
        isEditMode={isEditMode}
        isLoading={isLoading}
      />

      {isEditMode && (
        <Button onClick={() => { setIsEditMode(false); setEditingSite(null); }} sx={{mb:2}}>
          Cancel Edit / Add New
        </Button>
      )}


      {isLoading && !sites.length && <CircularProgress sx={{ display: 'block', margin: '20px auto' }} />}

      {!isLoading && (
        <SiteList
            sites={sites}
            onEdit={handleEditSite}
            onDelete={handleDeleteSite}
        />
      )}
      {/* Button to explicitly switch to "Add New" mode if form is not for editing */}
      {!isEditMode && sites.length >=5 && (
          <Alert severity="info" sx={{mt: 2}}>Maximum number of sites (5) reached.</Alert>
      )}
      {!isEditMode && sites.length < 5 && (
           <Button onClick={handleAddNew} variant="outlined" sx={{mt: 2, display: editingSite ? 'none': 'flex' }}>
             Add Another Site
           </Button>
      )}

    </Container>
  );
};

export default SitesPage;
