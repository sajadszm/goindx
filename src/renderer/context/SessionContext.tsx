// src/renderer/context/SessionContext.tsx
import React, { createContext, useState, useContext, ReactNode, useCallback } from 'react';
import { SiteConfig, SiteSessionAuth, WPUser } from '@/common/types';

interface SessionContextType {
  activeSite: SiteConfig | null; // The site actively being interacted with (post-login)
  sessionAuth: SiteSessionAuth | null; // Credentials for the activeSite session

  // Represents a site selected by the user for a login attempt, before successful login
  siteForLoginAttempt: SiteConfig | null;
  setSiteForLoginAttempt: (site: SiteConfig | null) => void;

  isLoadingSession: boolean;
  sessionError: string | null;

  loginToSite: (site: Pick<SiteConfig, 'id' | 'url' | 'name'>, auth: SiteSessionAuth) => Promise<{ success: boolean; message?: string; data?: WPUser }>;
  logoutFromSite: () => void;
}

const SessionContext = createContext<SessionContextType | undefined>(undefined);

export const SessionProvider: React.FC<{ children: ReactNode }> = ({ children }) => {
  const [activeSite, setActiveSite] = useState<SiteConfig | null>(null);
  const [sessionAuth, setSessionAuth] = useState<SiteSessionAuth | null>(null);
  const [siteForLoginAttempt, setSiteForLoginAttemptState] = useState<SiteConfig | null>(null);
  const [isLoadingSession, setIsLoadingSession] = useState(false);
  const [sessionError, setSessionError] = useState<string | null>(null);

  const loginToSite = useCallback(async (
    site: Pick<SiteConfig, 'id' | 'url' | 'name'>,
    auth: SiteSessionAuth
  ): Promise<{ success: boolean; message?: string; data?: WPUser }> => {
    setIsLoadingSession(true);
    setSessionError(null);

    try {
      if (!window.electronAPI) {
        throw new Error("Electron API is not available.");
      }
      // The IPC handler 'auth:login-to-site' will call wordpressConnector.testConnection
      const result = await window.electronAPI.invoke('auth:login-to-site', { url: site.url }, auth);

      if (result.success) {
        setActiveSite(site as SiteConfig); // Store the full site config passed in
        setSessionAuth(auth);
        setSiteForLoginAttemptState(null); // Clear pending login attempt site
        setIsLoadingSession(false);
        return { success: true, message: result.message, data: result.data };
      } else {
        setSessionError(result.message || 'Login failed. Please check credentials and site status.');
        setActiveSite(null); // Ensure no active site if login fails
        setSessionAuth(null);
        setIsLoadingSession(false);
        return { success: false, message: result.message || 'Login failed.' };
      }
    } catch (error: any) {
      console.error("Error during loginToSite:", error);
      setSessionError(error.message || 'An unexpected error occurred during login.');
      setActiveSite(null);
      setSessionAuth(null);
      setIsLoadingSession(false);
      return { success: false, message: error.message || 'Login failed due to an unexpected error.' };
    }
  }, []);

  const logoutFromSite = useCallback(() => {
    setActiveSite(null);
    setSessionAuth(null);
    setSessionError(null);
    setSiteForLoginAttemptState(null); // Also clear this on a full logout
    // No IPC call needed for logout unless we need to clear server-side sessions,
    // which is not typical for REST API basic/application password auth.
    console.log("User logged out from site session.");
  }, []);

  const setSiteForLoginAttempt = useCallback((site: SiteConfig | null) => {
    setSiteForLoginAttemptState(site);
    // If a new site is chosen for login attempt, clear any previous active session
    // to avoid confusion, unless the new site is the same as active site.
    if (activeSite && site?.id !== activeSite.id) {
        logoutFromSite();
    }
    setSessionError(null); // Clear previous errors when selecting a new site for login
  }, [activeSite, logoutFromSite]);

  return (
    <SessionContext.Provider
      value={{
        activeSite,
        sessionAuth,
        siteForLoginAttempt,
        setSiteForLoginAttempt,
        isLoadingSession,
        sessionError,
        loginToSite,
        logoutFromSite,
      }}
    >
      {children}
    </SessionContext.Provider>
  );
};

export const useSession = (): SessionContextType => {
  const context = useContext(SessionContext);
  if (context === undefined) {
    throw new Error('useSession must be used within a SessionProvider');
  }
  return context;
};
