// src/renderer/components/GuidanceModal/GuidanceModal.tsx
import React from 'react';
import {
  Modal,
  Box,
  Typography,
  Button,
  Paper,
  IconButton,
  Link,
} from '@mui/material';
import CloseIcon from '@mui/icons-material/Close';

interface GuidanceModalProps {
  open: boolean;
  onClose: () => void;
}

const modalStyle = {
  position: 'absolute' as 'absolute',
  top: '50%',
  left: '50%',
  transform: 'translate(-50%, -50%)',
  width: { xs: '90%', sm: '70%', md: '500px' },
  maxHeight: '80vh',
  overflowY: 'auto',
  bgcolor: 'background.paper',
  border: '1px solid #ddd', // Softer border
  boxShadow: 24,
  p: { xs: 2, sm: 3, md: 4 },
  borderRadius: '8px', // Rounded corners
};

const GuidanceModal: React.FC<GuidanceModalProps> = ({ open, onClose }) => {
  return (
    <Modal
      open={open}
      onClose={onClose}
      aria-labelledby="connection-guidance-title"
      aria-describedby="connection-guidance-description"
    >
      <Paper sx={modalStyle}>
        <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
          <Typography id="connection-guidance-title" variant="h6" component="h2">
            Connection Guidance
          </Typography>
          <IconButton onClick={onClose} aria-label="close">
            <CloseIcon />
          </IconButton>
        </Box>
        <Box id="connection-guidance-description">
          <Typography variant="body1" gutterBottom>
            To connect this application to your WordPress site securely and reliably, please follow these recommendations:
          </Typography>

          <Typography variant="h6" component="h3" sx={{ mt: 2, mb: 1, fontSize: '1.1rem' }}>
            1. Use Application Passwords (Recommended)
          </Typography>
          <Typography variant="body2" paragraph>
            Application Passwords are the most secure method. They are unique passwords generated for specific applications, and you can revoke them at any time without affecting your main WordPress login.
          </Typography>
          <Typography variant="body2" paragraph>
            <strong>How to generate:</strong>
            <ol>
              <li>Log in to your WordPress admin dashboard.</li>
              <li>Go to <strong>Users &gt; Profile</strong>.</li>
              <li>Scroll to the "Application Passwords" section. (If not visible, ensure WordPress is version 5.6+ or install the "Application Passwords" plugin).</li>
              <li>Enter a name for the password (e.g., "Desktop App") and click "Add New Application Password".</li>
              <li>Copy the generated password (e.g., <code>xxxx xxxx xxxx xxxx</code>) immediately. <strong>You will only see it once.</strong></li>
              <li>Use your normal WordPress username and this generated Application Password in this app's login form for the site.</li>
            </ol>
          </Typography>

          <Typography variant="h6" component="h3" sx={{ mt: 2, mb: 1, fontSize: '1.1rem' }}>
            2. Using Your Main WordPress Password (Less Secure)
          </Typography>
          <Typography variant="body2" paragraph sx={{color: 'warning.main'}}>
            <strong>Warning:</strong> Using your main WordPress password directly in third-party applications is less secure. If this application were compromised, your main site credentials could be at risk. We strongly recommend using Application Passwords.
          </Typography>
          <Typography variant="body2" paragraph>
            If you choose to use your main password, ensure your site is served over HTTPS.
          </Typography>

          <Typography variant="h6" component="h3" sx={{ mt: 2, mb: 1, fontSize: '1.1rem' }}>
            3. Ensure REST API is Accessible
          </Typography>
          <Typography variant="body2" paragraph>
            The WordPress REST API (usually at <code>yourdomain.com/wp-json/</code>) must be enabled and accessible.
            <ul>
              <li>Set Permalinks to anything other than "Plain" (e.g., "Post name") in WordPress Settings &gt; Permalinks.</li>
              <li>Check security plugins or firewall settings if you encounter connection errors, as they might restrict REST API access.</li>
            </ul>
          </Typography>
          <Typography variant="body2" paragraph>
            For more detailed setup information, refer to the <code>WORDPRESS_SETUP.md</code> file in the application's documentation.
          </Typography>
        </Box>
        <Box sx={{ mt: 3, textAlign: 'right' }}>
          <Button onClick={onClose} variant="contained">
            Close
          </Button>
        </Box>
      </Paper>
    </Modal>
  );
};

export default GuidanceModal;
