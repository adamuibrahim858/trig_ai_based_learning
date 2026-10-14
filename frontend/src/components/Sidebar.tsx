import { useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { useToast } from '../contexts/ToastContext';
import {
  LayoutDashboard, BookOpen, Brain, BarChart2,
  Star, Award, User, LogOut, GraduationCap, Lightbulb, X
} from 'lucide-react';

interface NavItem {
  label: string;
  icon: React.ReactNode;
  path: string;
}

const navItems: NavItem[] = [
  { label: 'Dashboard',       icon: <LayoutDashboard size={18} />, path: '/dashboard' },
  { label: 'Curriculum',      icon: <BookOpen size={18} />,        path: '/curriculum' },
  { label: 'Assessments',     icon: <Star size={18} />,            path: '/assessments' },
  { label: 'AI Tutor',        icon: <Brain size={18} />,           path: '/ai-tutor' },
  { label: 'Progress',        icon: <BarChart2 size={18} />,       path: '/progress' },
  { label: 'Recommendations', icon: <Lightbulb size={18} />,       path: '/recommendations' },
  { label: 'Final Exam',      icon: <GraduationCap size={18} />,   path: '/final-exam' },
  { label: 'Certificate',     icon: <Award size={18} />,           path: '/certificate' },
  { label: 'Profile',         icon: <User size={18} />,            path: '/profile' },
];

interface SidebarProps {
  isOpen?: boolean;
  onClose?: () => void;
}

export default function Sidebar({ isOpen = false, onClose }: SidebarProps) {
  const navigate = useNavigate();
  const location = useLocation();
  const { user, logout } = useAuth();
  const toast = useToast();

  const handleLogout = async () => {
    if (onClose) onClose();
    await logout();
    toast.success('Logged out successfully');
    navigate('/login');
  };

  const handleNav = (path: string) => {
    navigate(path);
    if (onClose) onClose();
  };

  const initials = user?.name
    ? user.name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
    : '??';

  return (
    <aside className={`sidebar ${isOpen ? 'sidebar-open' : ''}`}>
      {/* Logo */}
      <div className="sidebar-logo">
        <div className="sidebar-logo-brand" onClick={() => handleNav('/dashboard')} role="button" tabIndex={0}>
          <div className="sidebar-logo-icon">📐</div>
          <div>
            <div className="sidebar-logo-text">TrigMaster</div>
            <div className="sidebar-logo-sub">AI Powered Learning</div>
          </div>
        </div>

        {/* Mobile Close Button */}
        <button
          type="button"
          className="sidebar-close-btn"
          onClick={onClose}
          aria-label="Close navigation sidebar"
        >
          <X size={20} />
        </button>
      </div>

      {/* Navigation */}
      <nav className="sidebar-nav">
        <div className="sidebar-section-label">Navigation</div>
        {navItems.map(item => (
          <button
            key={item.path}
            type="button"
            className={`sidebar-link ${location.pathname === item.path ? 'active' : ''}`}
            onClick={() => handleNav(item.path)}
          >
            <span className="sidebar-link-icon">{item.icon}</span>
            <span>{item.label}</span>
          </button>
        ))}
      </nav>

      {/* Footer / User */}
      <div className="sidebar-footer">
        <div className="sidebar-user" onClick={() => handleNav('/profile')} role="button" tabIndex={0}>
          <div className="sidebar-avatar">{initials}</div>
          <div style={{ minWidth: 0, flex: 1 }}>
            <div className="sidebar-user-name">{user?.name ?? 'Student'}</div>
            <div className="sidebar-user-role">Student Account</div>
          </div>
        </div>
        <button
          type="button"
          className="sidebar-link sidebar-logout-btn"
          onClick={handleLogout}
        >
          <LogOut size={16} />
          <span>Logout</span>
        </button>
      </div>
    </aside>
  );
}
