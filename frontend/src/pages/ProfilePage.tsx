import { useState } from 'react';
import { useAuth } from '../contexts/AuthContext';
import { useToast } from '../contexts/ToastContext';
import { profileService } from '../services/profileService';
import { getErrorMessage } from '../services/api';
import {
  User, Lock, CheckCircle2,
  KeyRound, Save
} from 'lucide-react';

export default function ProfilePage() {
  const { user, setUser } = useAuth();
  const toast = useToast();

  // Profile Form state
  const [name, setName] = useState(user?.name || '');
  const [email, setEmail] = useState(user?.email || '');
  const [updatingProfile, setUpdatingProfile] = useState(false);

  // Password Form state
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [updatingPassword, setUpdatingPassword] = useState(false);

  const handleUpdateProfile = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim() || !email.trim()) {
      toast.error('Name and email cannot be empty');
      return;
    }

    try {
      setUpdatingProfile(true);
      const updated = await profileService.updateProfile({ name, email });
      setUser(updated);
      toast.success('Profile details updated successfully');
    } catch (err: unknown) {
      toast.error(getErrorMessage(err));
    } finally {
      setUpdatingProfile(false);
    }
  };

  const handleUpdatePassword = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!currentPassword) {
      toast.error('Current password is required');
      return;
    }
    if (newPassword.length < 8) {
      toast.error('New password must be at least 8 characters');
      return;
    }
    if (newPassword !== confirmPassword) {
      toast.error('New passwords do not match');
      return;
    }

    try {
      setUpdatingPassword(true);
      await profileService.updatePassword({
        current_password: currentPassword,
        password: newPassword,
        password_confirmation: confirmPassword,
      });
      toast.success('Password changed successfully');
      setCurrentPassword('');
      setNewPassword('');
      setConfirmPassword('');
    } catch (err: unknown) {
      toast.error(getErrorMessage(err));
    } finally {
      setUpdatingPassword(false);
    }
  };

  const initials = (name || user?.name || 'S')
    .split(' ')
    .map(n => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2);

  return (
    <div id="profile-page" style={{ maxWidth: 900, margin: '0 auto' }}>
      {/* Header */}
      <div className="page-header">
        <h1 className="page-title" id="profile-title">Student Profile & Settings</h1>
        <p className="page-subtitle">Manage your account information, credentials, and learning preferences</p>
      </div>

      {/* Profile Overview Card */}
      <div className="card" style={{ padding: 28, marginBottom: 28 }} id="profile-banner-card">
        <div style={{ display: 'flex', alignItems: 'center', gap: 20, flexWrap: 'wrap' }}>
          <div
            style={{
              width: 72,
              height: 72,
              borderRadius: '50%',
              background: 'linear-gradient(135deg, var(--primary), var(--purple))',
              color: '#fff',
              fontSize: '1.75rem',
              fontWeight: 700,
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              boxShadow: '0 0 25px var(--primary-glow)',
            }}
          >
            {initials}
          </div>

          <div style={{ flex: 1, minWidth: 200 }}>
            <h2 style={{ fontSize: '1.4rem', fontWeight: 700, marginBottom: 4 }}>{user?.name || 'Student'}</h2>
            <div style={{ color: 'var(--text-muted)', fontSize: '0.9rem', marginBottom: 10 }}>{user?.email}</div>
            <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
              <span className="badge badge-primary">
                Role: {(user?.role || 'student').toUpperCase()}
              </span>
              <span className="badge badge-success" style={{ display: 'flex', alignItems: 'center', gap: 4 }}>
                <CheckCircle2 size={13} /> Active Enrollment
              </span>
            </div>
          </div>
        </div>
      </div>

      <div className="grid-2" style={{ gap: 24 }}>
        {/* Card 1: Update Profile */}
        <div className="card" style={{ padding: 28 }} id="edit-profile-card">
          <h3 style={{ fontSize: '1.15rem', fontWeight: 600, marginBottom: 20, display: 'flex', alignItems: 'center', gap: 8 }}>
            <User size={18} style={{ color: 'var(--primary)' }} />
            Personal Information
          </h3>

          <form onSubmit={handleUpdateProfile} id="profile-form">
            <div className="form-group" style={{ marginBottom: 18 }}>
              <label className="form-label" htmlFor="profile-name-input">Full Name</label>
              <input
                id="profile-name-input"
                type="text"
                className="form-input"
                value={name}
                onChange={e => setName(e.target.value)}
                required
              />
            </div>

            <div className="form-group" style={{ marginBottom: 24 }}>
              <label className="form-label" htmlFor="profile-email-input">Email Address</label>
              <input
                id="profile-email-input"
                type="email"
                className="form-input"
                value={email}
                onChange={e => setEmail(e.target.value)}
                required
              />
            </div>

            <button
              type="submit"
              className="btn btn-primary"
              disabled={updatingProfile}
              id="save-profile-btn"
              style={{ width: '100%', display: 'flex', justifyContent: 'center', alignItems: 'center', gap: 8 }}
            >
              <Save size={16} />
              {updatingProfile ? 'Saving...' : 'Save Profile Changes'}
            </button>
          </form>
        </div>

        {/* Card 2: Security & Password */}
        <div className="card" style={{ padding: 28 }} id="change-password-card">
          <h3 style={{ fontSize: '1.15rem', fontWeight: 600, marginBottom: 20, display: 'flex', alignItems: 'center', gap: 8 }}>
            <KeyRound size={18} style={{ color: 'var(--accent)' }} />
            Change Password
          </h3>

          <form onSubmit={handleUpdatePassword} id="password-form">
            <div className="form-group" style={{ marginBottom: 16 }}>
              <label className="form-label" htmlFor="current-password-input">Current Password</label>
              <input
                id="current-password-input"
                type="password"
                className="form-input"
                placeholder="••••••••"
                value={currentPassword}
                onChange={e => setCurrentPassword(e.target.value)}
                required
              />
            </div>

            <div className="form-group" style={{ marginBottom: 16 }}>
              <label className="form-label" htmlFor="new-password-input">New Password</label>
              <input
                id="new-password-input"
                type="password"
                className="form-input"
                placeholder="At least 8 characters"
                value={newPassword}
                onChange={e => setNewPassword(e.target.value)}
                required
              />
            </div>

            <div className="form-group" style={{ marginBottom: 24 }}>
              <label className="form-label" htmlFor="confirm-password-input">Confirm New Password</label>
              <input
                id="confirm-password-input"
                type="password"
                className="form-input"
                placeholder="Repeat new password"
                value={confirmPassword}
                onChange={e => setConfirmPassword(e.target.value)}
                required
              />
            </div>

            <button
              type="submit"
              className="btn btn-secondary"
              disabled={updatingPassword}
              id="update-password-btn"
              style={{ width: '100%', display: 'flex', justifyContent: 'center', alignItems: 'center', gap: 8 }}
            >
              <Lock size={16} />
              {updatingPassword ? 'Updating Password...' : 'Update Password'}
            </button>
          </form>
        </div>
      </div>
    </div>
  );
}
