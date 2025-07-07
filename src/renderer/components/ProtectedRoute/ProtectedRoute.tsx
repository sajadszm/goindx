// src/renderer/components/ProtectedRoute/ProtectedRoute.tsx
import React, { ReactElement } from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { useSession } from '@/renderer/context/SessionContext';
import { Box, CircularProgress, Typography } from '@mui/material';

interface ProtectedRouteProps {
  children: ReactElement;
}

const ProtectedRoute: React.FC<ProtectedRouteProps> = ({ children }) => {
  const { activeSite, sessionAuth, isLoadingSession } = useSession();
  const location = useLocation();

  if (isLoadingSession) {
    // Show a loading spinner or a blank page while session is being checked or restored
    // This is important if you implement session persistence and auto-login attempts
    return (
      <Box sx={{ display: 'flex', justifyContent: 'center', alignItems: 'center', height: 'calc(100vh - 64px)' }}> {/* Adjust height based on AppBar */}
        <CircularProgress />
        <Typography sx={{ml: 2}}>Loading session...</Typography>
      </Box>
    );
  }

  if (!activeSite || !sessionAuth) {
    // User not logged in, redirect to login page
    // Pass the current location to redirect back after login
    return <Navigate to="/login" state={{ from: location }} replace />;
  }

  // User is logged in, render the requested component
  return children;
};

export default ProtectedRoute;
