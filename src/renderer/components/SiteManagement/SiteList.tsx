// src/renderer/components/SiteManagement/SiteList.tsx
import React from 'react';
import { SiteConfig } from '@/common/types';
import {
  List,
  ListItem,
  ListItemText,
  ListItemSecondaryAction,
  IconButton,
  Typography,
  Paper,
  Divider,
  Box,
} from '@mui/material';
import DeleteIcon from '@mui/icons-material/Delete';
import EditIcon from '@mui/icons-material/Edit';
// import LinkIcon from '@mui/icons-material/Link'; // For a "connect" or "view" button

interface SiteListProps {
  sites: SiteConfig[];
  onEdit: (site: SiteConfig) => void;
  onDelete: (siteId: string) => Promise<void>;
  // onSelectSite?: (siteId: string) => void; // For navigating to a site's dashboard
}

const SiteList: React.FC<SiteListProps> = ({ sites, onEdit, onDelete /*, onSelectSite*/ }) => {
  if (!sites.length) {
    return (
      <Typography variant="subtitle1" sx={{ mt: 2, textAlign: 'center' }}>
        No WordPress sites configured yet. Add one using the form above.
      </Typography>
    );
  }

  return (
    <Paper elevation={3} sx={{ mt: 3 }}>
      <Typography variant="h6" sx={{ p: 2 }}>
        Connected Sites
      </Typography>
      <List disablePadding>
        {sites.map((site, index) => (
          <React.Fragment key={site.id}>
            <ListItem
            // button // Make item clickable if onSelectSite is implemented
            // onClick={() => onSelectSite && onSelectSite(site.id)}
            >
              {/* <ListItemIcon sx={{mr: 1}}>
                <LinkIcon />
              </ListItemIcon> */}
              <ListItemText
                primary={site.name || site.url}
                secondary={site.name ? site.url : `Username: ${site.username}`}
              />
              <ListItemSecondaryAction>
                <IconButton edge="end" aria-label="edit" onClick={() => onEdit(site)} sx={{mr: 0.5}}>
                  <EditIcon />
                </IconButton>
                <IconButton edge="end" aria-label="delete" onClick={() => onDelete(site.id)}>
                  <DeleteIcon />
                </IconButton>
              </ListItemSecondaryAction>
            </ListItem>
            {index < sites.length - 1 && <Divider component="li" />}
          </React.Fragment>
        ))}
      </List>
    </Paper>
  );
};

export default SiteList;
