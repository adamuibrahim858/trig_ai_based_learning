import { useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { useToast } from '../contexts/ToastContext';
import {
  LayoutDashboard, BookOpen, Brain, BarChart2,
  Star, Award, User, LogOut, GraduationCap, Lightbulb,
} from 'lucide-react';

interface NavItem {
  label: string;
  icon: React.ReactNode;
  path: string;
}

const navItems: NavItem[] = [
  { label: 'Dashboard',    icon: <LayoutDashboard size={18} />, path: '/dashboard' },
  { label: 'Curriculum',   icon: <BookOpen size={18} />,        path: '/curriculum' },
  { label: 'Assessments',  icon: <Star size={18} />,            path: '/assessments' },
  { label: 'AI Tutor',     icon: <Brain size={18} />,           path: '/ai-tutor' },
  { label: 'Progress',     icon: <BarChart2 size={18} />,       path: '/progress' },
  { label: 'Recommendations', icon: <Lightbulb size={18} />,   path: '/recommendations' },
  { label: 'Final Exam',   icon: <GraduationCap size={18} />,  path: '/final-exam' },
  { label: 'Certificate',  icon: <Award size={18} />,           path: '/certificate' },
  { label: 'Profile',      icon: <User size={18} />,            path: '/profile' },
];

export default function Sidebar() {
  const navigate = useNavigate();
  const location = useLocation();
  const { user, logout } = useAuth();
  const toast = useToast();

  const handleLogout = async () => {
    await logout();
    toast.success('Logged out successfully');
    navigate('/login');
  };

  const initials = user?.name
    ? user.name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
    : '??';

  return (
    <aside className="sidebar">
      {/* Logo */}
      <div className="sidebar-logo">
        <div className="sidebar-logo-icon">📐</div>
        <div>
          <div className="sidebar-logo-text">TrigMaster</div>
          <div className="sidebar-logo-sub">AI Powered Learning</div>
        </div>
      </div>

      {/* Navigation */}
      <nav className="sidebar-nav">
        <div className="sidebar-section-label">Navigation</div>
        {navItems.map(item => (
          <button
            key={item.path}
            className={`sidebar-link ${location.pathname === item.path ? 'active' : ''}`}
            onClick={() => navigate(item.path)}
          >
            <span className="sidebar-link-icon">{item.icon}</span>
            {item.label}
          </button>
        ))}
      </nav>

      {/* Footer / User */}
      <div className="sidebar-footer">
        <div className="sidebar-user" style={{ marginBottom: 8 }}>
          <div className="sidebar-avatar">{initials}</div>
          <div style={{ minWidth: 0 }}>
            <div className="sidebar-user-name">{user?.name ?? 'Student'}</div>
            <div className="sidebar-user-role">Student</div>
          </div>
        </div>
        <button
          className="sidebar-link"
          onClick={handleLogout}
          style={{ color: 'var(--danger)', width: '100%' }}
        >
          <LogOut size={16} />
          Logout
        </button>
      </div>
    </aside>
  );
}
