import { useState, useRef, useEffect } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { useToast } from '../contexts/ToastContext';
import {
  Menu, Sparkles, User, LogOut, ChevronDown, BookOpen, Award
} from 'lucide-react';

interface HeaderProps {
  onToggleSidebar: () => void;
}

const pageTitles: Record<string, { title: string; subtitle?: string; icon?: React.ReactNode }> = {
  '/dashboard': { title: 'Dashboard', subtitle: 'Overview & Learning Progress' },
  '/curriculum': { title: 'Curriculum', subtitle: '8-Week Trigonometry Program' },
  '/assessments': { title: 'Assessments', subtitle: 'Module & Final Quizzes' },
  '/ai-tutor': { title: 'AI Tutor', subtitle: 'Interactive Trigonometry Assistant' },
  '/progress': { title: 'Progress Analytics', subtitle: 'Mastery & Performance Tracking' },
  '/recommendations': { title: 'Adaptive Recommendations', subtitle: 'Targeted Practice & Reinforcement' },
  '/final-exam': { title: 'Comprehensive Exam', subtitle: 'Final Certification Test' },
  '/certificate': { title: 'Mastery Certificate', subtitle: 'Verified Academic Credential' },
  '/profile': { title: 'Account Settings', subtitle: 'Student Profile & Preferences' },
};

export default function Header({ onToggleSidebar }: HeaderProps) {
  const location = useLocation();
  const navigate = useNavigate();
  const { user, logout } = useAuth();
  const toast = useToast();
  const [dropdownOpen, setDropdownOpen] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);

  // Close dropdown on outside click
  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setDropdownOpen(false);
      }
    }
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const handleLogout = async () => {
    setDropdownOpen(false);
    await logout();
    toast.success('Logged out successfully');
    navigate('/login');
  };

  // Determine active title
  let currentInfo = pageTitles[location.pathname];
  if (!currentInfo) {
    if (location.pathname.startsWith('/lesson/')) {
      currentInfo = { title: 'Lesson View', subtitle: 'Interactive Study Module' };
    } else if (location.pathname.startsWith('/attempt/')) {
      currentInfo = { title: 'Assessment in Progress', subtitle: 'Complete all questions' };
    } else if (location.pathname.startsWith('/result/')) {
      currentInfo = { title: 'Assessment Results', subtitle: 'Score Breakdown & Review' };
    } else {
      currentInfo = { title: 'TrigMaster AI', subtitle: 'Personalized Trigonometry Learning' };
    }
  }

  const initials = user?.name
    ? user.name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
    : '??';

  return (
    <header className="app-header">
      <div className="header-left">
        {/* Mobile Hamburger Toggle */}
        <button
          type="button"
          className="header-icon-btn mobile-menu-toggle"
          onClick={onToggleSidebar}
          aria-label="Toggle Navigation Menu"
          id="mobile-menu-button"
        >
          <Menu size={20} />
        </button>

        {/* Mobile Brand */}
        <div
          className="header-mobile-brand"
          onClick={() => navigate('/dashboard')}
          role="button"
          tabIndex={0}
        >
          <div className="header-logo-icon">📐</div>
          <span className="header-brand-title">TrigMaster</span>
        </div>

        {/* Desktop Breadcrumbs / Page Title */}
        <div className="header-desktop-title">
          <div className="header-title-text">{currentInfo.title}</div>
          {currentInfo.subtitle && (
            <span className="header-title-sub">• {currentInfo.subtitle}</span>
          )}
        </div>
      </div>

      <div className="header-right">
        {/* Quick AI Tutor Shortcut */}
        <button
          type="button"
          className="header-quick-action-btn"
          onClick={() => navigate('/ai-tutor')}
          title="Open AI Tutor"
          id="header-ai-tutor-btn"
        >
          <Sparkles size={16} className="text-primary-c" />
          <span className="header-btn-text">AI Tutor</span>
        </button>

        {/* Quick Curriculum Shortcut */}
        <button
          type="button"
          className="header-quick-action-btn header-curriculum-btn"
          onClick={() => navigate('/curriculum')}
          title="Go to Curriculum"
          id="header-curriculum-btn"
        >
          <BookOpen size={16} />
          <span className="header-btn-text">Curriculum</span>
        </button>

        {/* User Profile Menu */}
        <div className="header-user-menu" ref={dropdownRef}>
          <button
            type="button"
            className="header-user-btn"
            onClick={() => setDropdownOpen(prev => !prev)}
            aria-expanded={dropdownOpen}
            id="user-profile-menu-btn"
          >
            <div className="header-avatar">{initials}</div>
            <div className="header-user-info">
              <span className="header-user-name">{user?.name ?? 'Student'}</span>
              <span className="header-user-badge">Student</span>
            </div>
            <ChevronDown size={14} className="header-user-chevron" />
          </button>

          {dropdownOpen && (
            <div className="header-dropdown-menu">
              <div className="header-dropdown-header">
                <div className="header-dropdown-name">{user?.name}</div>
                <div className="header-dropdown-email">{user?.email}</div>
              </div>
              <div className="header-dropdown-divider" />
              <button
                type="button"
                className="header-dropdown-item"
                onClick={() => {
                  setDropdownOpen(false);
                  navigate('/profile');
                }}
              >
                <User size={15} />
                <span>My Profile</span>
              </button>
              <button
                type="button"
                className="header-dropdown-item"
                onClick={() => {
                  setDropdownOpen(false);
                  navigate('/certificate');
                }}
              >
                <Award size={15} />
                <span>Certificate</span>
              </button>
              <div className="header-dropdown-divider" />
              <button
                type="button"
                className="header-dropdown-item header-dropdown-logout"
                onClick={handleLogout}
              >
                <LogOut size={15} />
                <span>Logout</span>
              </button>
            </div>
          )}
        </div>
      </div>
    </header>
  );
}
