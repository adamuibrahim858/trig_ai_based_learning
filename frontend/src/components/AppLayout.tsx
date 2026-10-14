import { useState, useEffect } from 'react';
import { useLocation } from 'react-router-dom';
import Sidebar from './Sidebar';
import Header from './Header';

interface AppLayoutProps {
  children: React.ReactNode;
}

export default function AppLayout({ children }: AppLayoutProps) {
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const location = useLocation();

  // Auto-close mobile drawer on route change
  useEffect(() => {
    setMobileMenuOpen(false);
  }, [location.pathname]);

  // Close on ESC key
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setMobileMenuOpen(false);
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, []);

  return (
    <div className="app-shell">
      {/* Mobile Backdrop */}
      {mobileMenuOpen && (
        <div
          className="sidebar-backdrop"
          onClick={() => setMobileMenuOpen(false)}
          aria-hidden="true"
        />
      )}

      {/* Main Sidebar (Desktop fixed, Mobile off-canvas drawer) */}
      <Sidebar
        isOpen={mobileMenuOpen}
        onClose={() => setMobileMenuOpen(false)}
      />

      {/* Main Body */}
      <div className="main-content">
        <Header onToggleSidebar={() => setMobileMenuOpen(prev => !prev)} />
        <main className="page-container">
          {children}
        </main>
      </div>
    </div>
  );
}
