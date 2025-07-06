// src/renderer/components/ErrorBoundary/ErrorBoundary.tsx
import React, { Component, ErrorInfo, ReactNode } from 'react';
import { Box, Typography, Paper, Button } from '@mui/material';
import ReportProblemIcon from '@mui/icons-material/ReportProblem';

interface Props {
  children: ReactNode;
  fallbackMessage?: string;
}

interface State {
  hasError: boolean;
  error?: Error;
  errorInfo?: ErrorInfo;
}

class ErrorBoundary extends Component<Props, State> {
  public state: State = {
    hasError: false,
  };

  public static getDerivedStateFromError(error: Error): State {
    // Update state so the next render will show the fallback UI.
    return { hasError: true, error };
  }

  public componentDidCatch(error: Error, errorInfo: ErrorInfo) {
    console.error("[ErrorBoundary] Uncaught error:", error, errorInfo);
    this.setState({ errorInfo });
    // You can also log the error to an error reporting service here
    // Example: logErrorToMyService(error, errorInfo);
  }

  private handleReload = () => {
    // Attempt to reload the page, or a more specific component reload if possible
    window.location.reload();
  };

  public render() {
    if (this.state.hasError) {
      return (
        <Paper sx={{ m: {xs: 2, md: 4}, p: {xs: 2, md: 3}, textAlign: 'center', border: '1px solid red' }}>
          <Box sx={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 2 }}>
            <ReportProblemIcon color="error" sx={{ fontSize: 50 }} />
            <Typography variant="h5" component="h2" color="error">
              Something went wrong.
            </Typography>
            <Typography variant="body1" sx={{ color: 'text.secondary' }}>
              {this.props.fallbackMessage || "We're sorry, an unexpected error occurred in this section of the application."}
            </Typography>
            {process.env.NODE_ENV === 'development' && this.state.error && (
              <Box sx={{ mt: 2, textAlign: 'left', maxHeight: 200, overflowY: 'auto', p:1, backgroundColor: 'grey.100', width: '100%' }}>
                <Typography variant="caption" component="pre" sx={{ whiteSpace: 'pre-wrap', wordBreak: 'break-all' }}>
                  {this.state.error.toString()}
                  {this.state.errorInfo && `\nComponent Stack:\n${this.state.errorInfo.componentStack}`}
                </Typography>
              </Box>
            )}
            <Button variant="outlined" color="primary" onClick={this.handleReload} sx={{mt: 2}}>
              Reload Application
            </Button>
          </Box>
        </Paper>
      );
    }

    return this.props.children;
  }
}

export default ErrorBoundary;
