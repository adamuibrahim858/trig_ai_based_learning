import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import type { User } from '../types/api';
import { authService } from '../services/authService';

interface AuthContextValue {
  user: User | null;
  token: string | null;
  isLoading: boolean;
  isAuthenticated: boolean;
  login: (email: string, password: string) => Promise<void>;
  register: (name: string, email: string, password: string, password_confirmation: string) => Promise<void>;
  logout: () => Promise<void>;
  setUser: (u: User) => void;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUserState] = useState<User | null>(null);
  const [token, setToken] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    let isMounted = true;

    const restoreSession = async () => {
      const storedToken = localStorage.getItem('trig_token');
      if (!storedToken) {
        if (isMounted) setIsLoading(false);
        return;
      }

      try {
        if (isMounted) setToken(storedToken);

        // Verify token with backend to avoid trusting stale/revoked localStorage
        const freshUser = await authService.getCurrentUser();
        if (isMounted) {
          setUserState(freshUser);
          localStorage.setItem('trig_user', JSON.stringify(freshUser));
        }
      } catch {
        // Stored token is invalid, revoked, or expired
        localStorage.removeItem('trig_token');
        localStorage.removeItem('trig_user');
        if (isMounted) {
          setToken(null);
          setUserState(null);
        }
      } finally {
        if (isMounted) {
          setIsLoading(false);
        }
      }
    };

    restoreSession();

    return () => {
      isMounted = false;
    };
  }, []);

  // Listen for unauthorized events
  useEffect(() => {
    const handler = () => {
      setUserState(null);
      setToken(null);
    };
    window.addEventListener('auth:unauthorized', handler);
    return () => window.removeEventListener('auth:unauthorized', handler);
  }, []);

  const login = useCallback(async (email: string, password: string) => {
    const data = await authService.login({ email, password });
    localStorage.setItem('trig_token', data.token);
    localStorage.setItem('trig_user', JSON.stringify(data.user));
    setToken(data.token);
    setUserState(data.user);
  }, []);

  const register = useCallback(async (name: string, email: string, password: string, password_confirmation: string) => {
    const data = await authService.register({ name, email, password, password_confirmation });
    localStorage.setItem('trig_token', data.token);
    localStorage.setItem('trig_user', JSON.stringify(data.user));
    setToken(data.token);
    setUserState(data.user);
  }, []);

  const logout = useCallback(async () => {
    try { await authService.logout(); } catch { /* ignore */ }
    localStorage.removeItem('trig_token');
    localStorage.removeItem('trig_user');
    setToken(null);
    setUserState(null);
  }, []);

  const setUser = useCallback((u: User) => {
    setUserState(u);
    localStorage.setItem('trig_user', JSON.stringify(u));
  }, []);

  return (
    <AuthContext.Provider value={{
      user, token, isLoading,
      isAuthenticated: !!token && !!user,
      login, register, logout, setUser,
    }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
}
