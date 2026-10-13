import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { progressService, type OverallProgressResponse } from '../services/progressService';
import { useToast } from '../contexts/ToastContext';
import {
  CheckCircle, Clock, BookOpen, Award, ArrowRight,
  TrendingUp, Calendar, AlertCircle, RefreshCw
} from 'lucide-react';

export default function ProgressPage() {
  const navigate = useNavigate();
  const toast = useToast();
  const [loading, setLoading] = useState(true);
  const [data, setData] = useState<OverallProgressResponse | null>(null);
  const [activeTab, setActiveTab] = useState<'topics' | 'lessons' | 'assessments'>('topics');

  const fetchProgress = async () => {
    try {
      setLoading(true);
      const res = await progressService.getOverallProgress();
      setData(res);
    } catch {
      toast.error('Failed to load progress data');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchProgress();
  }, []);

  if (loading) {
    return (
      <div className="page-container">
        <div className="loading-center">
          <div className="spinner" id="progress-spinner" />
        </div>
      </div>
    );
  }

  const overall = data?.overall || {
    total_lessons: 0,
    completed_lessons: 0,
    completion_percentage: 0,
  };

  const topics = data?.topics || [];
  const lessons = data?.lessons || [];
  const recentAttempts = data?.recent_attempts || [];

  const completedTopicsCount = topics.filter(t => t.status === 'completed').length;
  const avgScore = recentAttempts.length > 0
    ? Math.round(recentAttempts.reduce((acc, a) => acc + a.percentage, 0) / recentAttempts.length)
    : 0;

  return (
    <div className="page-container" id="progress-page">
      {/* Header */}
      <div className="page-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
        <div>
          <h1 className="page-title" id="progress-title">My Progress & Analytics</h1>
          <p className="page-subtitle">Track your learning journey, curriculum milestones, and test performance</p>
        </div>
        <button
          className="btn btn-secondary"
          onClick={fetchProgress}
          id="refresh-progress-btn"
          style={{ display: 'flex', alignItems: 'center', gap: 6 }}
        >
          <RefreshCw size={15} />
          Refresh
        </button>
      </div>

      {/* Overview Stat Tiles */}
      <div className="grid-4" style={{ marginBottom: 28 }}>
        <div className="stat-tile" id="stat-completion-rate">
          <div className="stat-icon" style={{ background: 'rgba(99,102,241,0.15)', color: 'var(--primary)' }}>
            <TrendingUp size={22} />
          </div>
          <div>
            <div className="stat-value">{overall.completion_percentage}%</div>
            <div className="stat-label">Overall Completion</div>
          </div>
        </div>

        <div className="stat-tile" id="stat-completed-lessons">
          <div className="stat-icon" style={{ background: 'rgba(16,185,129,0.15)', color: 'var(--success)' }}>
            <CheckCircle size={22} />
          </div>
          <div>
            <div className="stat-value">{overall.completed_lessons} / {overall.total_lessons}</div>
            <div className="stat-label">Lessons Finished</div>
          </div>
        </div>

        <div className="stat-tile" id="stat-completed-topics">
          <div className="stat-icon" style={{ background: 'rgba(236,72,153,0.15)', color: 'var(--pink)' }}>
            <BookOpen size={22} />
          </div>
          <div>
            <div className="stat-value">{completedTopicsCount} / {topics.length || 8}</div>
            <div className="stat-label">Topics Mastered</div>
          </div>
        </div>

        <div className="stat-tile" id="stat-avg-score">
          <div className="stat-icon" style={{ background: 'rgba(245,158,11,0.15)', color: 'var(--accent)' }}>
            <Award size={22} />
          </div>
          <div>
            <div className="stat-value">{avgScore}%</div>
            <div className="stat-label">Average Test Score</div>
          </div>
        </div>
      </div>

      {/* Progress Bar Banner */}
      <div className="card" style={{ marginBottom: 32, padding: 24 }} id="overall-progress-card">
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 10 }}>
          <span style={{ fontWeight: 600, fontSize: '0.95rem' }}>Curriculum Completion Progress</span>
          <span style={{ color: 'var(--primary)', fontWeight: 700 }}>{overall.completion_percentage}%</span>
        </div>
        <div className="progress-bar" style={{ height: 10 }}>
          <div className="progress-fill" style={{ width: `${overall.completion_percentage}%` }} />
        </div>
        <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 8, fontSize: '0.8rem', color: 'var(--text-muted)' }}>
          <span>Week 1: Fundamentals</span>
          <span>Week 8: Applications</span>
        </div>
      </div>

      {/* Navigation Tabs */}
      <div style={{ display: 'flex', gap: 10, borderBottom: '1px solid var(--border)', paddingBottom: 12, marginBottom: 24 }}>
        <button
          className={`btn ${activeTab === 'topics' ? 'btn-primary' : 'btn-ghost'}`}
          onClick={() => setActiveTab('topics')}
          id="tab-topics-btn"
        >
          Topics Milestones ({topics.length})
        </button>
        <button
          className={`btn ${activeTab === 'lessons' ? 'btn-primary' : 'btn-ghost'}`}
          onClick={() => setActiveTab('lessons')}
          id="tab-lessons-btn"
        >
          Lesson History ({lessons.length})
        </button>
        <button
          className={`btn ${activeTab === 'assessments' ? 'btn-primary' : 'btn-ghost'}`}
          onClick={() => setActiveTab('assessments')}
          id="tab-assessments-btn"
        >
          Assessment Attempts ({recentAttempts.length})
        </button>
      </div>

      {/* TAB 1: Topics Milestones */}
      {activeTab === 'topics' && (
        <div className="grid-2" id="topics-list-container">
          {topics.length === 0 ? (
            <div className="card" style={{ gridColumn: 'span 2', textAlign: 'center', padding: 40 }}>
              <p style={{ color: 'var(--text-muted)' }}>No topic progress recorded yet. Start exploring the curriculum!</p>
              <button
                className="btn btn-primary"
                style={{ marginTop: 14 }}
                onClick={() => navigate('/curriculum')}
                id="explore-curriculum-btn"
              >
                Go to Curriculum
              </button>
            </div>
          ) : (
            topics.map(t => {
              const isDone = t.status === 'completed';
              return (
                <div key={t.topic_id} className="card" style={{ padding: 20 }} id={`topic-progress-${t.topic_id}`}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 12 }}>
                    <div>
                      <span className="badge badge-primary" style={{ marginBottom: 6 }}>
                        Week {t.week_number}
                      </span>
                      <h3 style={{ fontSize: '1.05rem', fontWeight: 600, marginTop: 4 }}>{t.topic_title}</h3>
                    </div>
                    <span className={`badge ${isDone ? 'badge-success' : t.status === 'in_progress' ? 'badge-primary' : 'badge-neutral'}`}>
                      {isDone ? 'Completed' : t.status === 'in_progress' ? 'In Progress' : 'Not Started'}
                    </span>
                  </div>

                  {t.completed_at && (
                    <div style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: '0.8rem', color: 'var(--text-muted)' }}>
                      <Calendar size={14} />
                      Completed on {new Date(t.completed_at).toLocaleDateString()}
                    </div>
                  )}
                </div>
              );
            })
          )}
        </div>
      )}

      {/* TAB 2: Lesson Breakdown */}
      {activeTab === 'lessons' && (
        <div className="card" style={{ padding: 0, overflow: 'hidden' }} id="lessons-list-container">
          {lessons.length === 0 ? (
            <div style={{ padding: 40, textAlign: 'center', color: 'var(--text-muted)' }}>
              No lesson activity recorded yet.
            </div>
          ) : (
            <div style={{ display: 'flex', flexDirection: 'column' }}>
              {lessons.map((l, index) => {
                const isCompleted = l.status === 'completed';
                const isInProgress = l.status === 'in_progress';

                return (
                  <div
                    key={l.lesson_id}
                    style={{
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'space-between',
                      padding: '16px 20px',
                      borderBottom: index < lessons.length - 1 ? '1px solid var(--border)' : 'none',
                    }}
                    id={`lesson-row-${l.lesson_id}`}
                  >
                    <div style={{ display: 'flex', alignItems: 'center', gap: 14 }}>
                      <div
                        style={{
                          width: 34,
                          height: 34,
                          borderRadius: '50%',
                          display: 'flex',
                          alignItems: 'center',
                          justifyContent: 'center',
                          background: isCompleted ? 'rgba(16,185,129,0.15)' : isInProgress ? 'rgba(99,102,241,0.15)' : 'var(--card-hover)',
                          color: isCompleted ? 'var(--success)' : isInProgress ? 'var(--primary)' : 'var(--text-muted)',
                        }}
                      >
                        {isCompleted ? <CheckCircle size={18} /> : isInProgress ? <Clock size={18} /> : <BookOpen size={18} />}
                      </div>
                      <div>
                        <div style={{ fontWeight: 500, fontSize: '0.95rem' }}>{l.lesson_title}</div>
                        <div style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>
                          {isCompleted && l.completed_at
                            ? `Completed: ${new Date(l.completed_at).toLocaleDateString()}`
                            : isInProgress && l.started_at
                            ? `Started: ${new Date(l.started_at).toLocaleDateString()}`
                            : 'Not yet started'}
                        </div>
                      </div>
                    </div>

                    <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                      <span className={`badge ${isCompleted ? 'badge-success' : isInProgress ? 'badge-primary' : 'badge-neutral'}`}>
                        {isCompleted ? 'Completed' : isInProgress ? 'In Progress' : 'Not Started'}
                      </span>
                      <button
                        className="btn btn-ghost"
                        style={{ padding: '6px 10px', fontSize: '0.85rem' }}
                        onClick={() => navigate(`/lesson/${l.lesson_id}`)}
                        id={`open-lesson-${l.lesson_id}-btn`}
                      >
                        Open <ArrowRight size={14} />
                      </button>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>
      )}

      {/* TAB 3: Assessment Attempts */}
      {activeTab === 'assessments' && (
        <div className="card" style={{ padding: 0, overflow: 'hidden' }} id="assessments-list-container">
          {recentAttempts.length === 0 ? (
            <div style={{ padding: 40, textAlign: 'center', color: 'var(--text-muted)' }}>
              <AlertCircle size={32} style={{ margin: '0 auto 12px', opacity: 0.5 }} />
              <div>No assessments taken yet.</div>
              <button
                className="btn btn-primary"
                style={{ marginTop: 14 }}
                onClick={() => navigate('/assessments')}
                id="take-assessment-btn"
              >
                Go to Assessments
              </button>
            </div>
          ) : (
            <div style={{ display: 'flex', flexDirection: 'column' }}>
              {recentAttempts.map((att, index) => (
                <div
                  key={att.id}
                  style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    padding: '16px 20px',
                    borderBottom: index < recentAttempts.length - 1 ? '1px solid var(--border)' : 'none',
                  }}
                  id={`attempt-row-${att.id}`}
                >
                  <div>
                    <div style={{ fontWeight: 600, fontSize: '0.95rem' }}>{att.test_title}</div>
                    <div style={{ display: 'flex', gap: 12, alignItems: 'center', marginTop: 4, fontSize: '0.8rem', color: 'var(--text-muted)' }}>
                      <span style={{ textTransform: 'capitalize' }}>Type: {att.test_type.replace('_', ' ')}</span>
                      <span>•</span>
                      <span>{new Date(att.submitted_at).toLocaleDateString()}</span>
                    </div>
                  </div>

                  <div style={{ display: 'flex', alignItems: 'center', gap: 14 }}>
                    <div style={{ textAlign: 'right' }}>
                      <div style={{ fontWeight: 700, fontSize: '1.1rem', color: att.passed ? 'var(--success)' : 'var(--danger)' }}>
                        {att.percentage}%
                      </div>
                      <span className={`badge ${att.passed ? 'badge-success' : 'badge-danger'}`} style={{ fontSize: '0.75rem' }}>
                        {att.passed ? 'Passed' : 'Failed'}
                      </span>
                    </div>

                    <button
                      className="btn btn-secondary"
                      style={{ padding: '6px 12px', fontSize: '0.85rem' }}
                      onClick={() => navigate(`/result/${att.id}`)}
                      id={`view-result-${att.id}-btn`}
                    >
                      View Review
                    </button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      )}
    </div>
  );
}
