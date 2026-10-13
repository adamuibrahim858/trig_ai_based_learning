import { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { dashboardService } from '../services/dashboardService';
import { useAuth } from '../contexts/AuthContext';
import { useToast } from '../contexts/ToastContext';
import { curriculumService } from '../services/curriculumService';
import type { DashboardData, Course } from '../types/api';
import { getErrorMessage } from '../services/api';
import {
  BookOpen, CheckCircle2, TrendingUp, Star, ArrowRight,
  Award, AlertTriangle, Loader2, Zap, Target,
} from 'lucide-react';

export default function DashboardPage() {
  const navigate = useNavigate();
  const { user } = useAuth();
  const toast = useToast();
  const [data, setData] = useState<DashboardData | null>(null);
  const [loading, setLoading] = useState(true);
  const [enrolling, setEnrolling] = useState(false);
  const [courses, setCourses] = useState<Course[]>([]);

  const fetchDashboard = useCallback(async () => {
    try {
      setLoading(true);
      const res = await dashboardService.getDashboard();
      setData(res);
    } catch (err) {
      const msg = getErrorMessage(err);
      // If 404/not enrolled, fetch courses so user can enroll
      if (msg.includes('not enrolled') || msg.includes('No enrollment')) {
        try {
          const c = await curriculumService.getCourses();
          setCourses(c);
        } catch { /* ignore */ }
      }
      toast.error(msg);
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    fetchDashboard();
  }, [fetchDashboard]);

  const handleEnroll = async (courseId: number) => {
    setEnrolling(true);
    try {
      await curriculumService.enrollInCourse(courseId);
      toast.success('Enrolled successfully! Let\'s begin!');
      await fetchDashboard();
    } catch (err) {
      toast.error(getErrorMessage(err));
    } finally {
      setEnrolling(false);
    }
  };

  if (loading) {
    return (
      <div className="loading-center" style={{ height: '60vh' }}>
        <div className="spinner" />
      </div>
    );
  }

  // Not enrolled state
  if (!data || !data.course?.is_enrolled) {
    return (
      <div>
        <div className="page-header">
          <div>
            <h1 className="page-title">Welcome, {user?.name?.split(' ')[0]} 👋</h1>
            <p className="page-subtitle">Enroll in a course to start your AI-powered learning journey</p>
          </div>
        </div>

        {/* Hero Banner */}
        <div style={{
          background: 'linear-gradient(135deg, rgba(99,102,241,0.15) 0%, rgba(168,85,247,0.1) 100%)',
          border: '1px solid rgba(99,102,241,0.3)',
          borderRadius: 'var(--radius-xl)',
          padding: 40,
          textAlign: 'center',
          marginBottom: 32,
        }}>
          <div style={{ fontSize: 56, marginBottom: 16 }}>📐</div>
          <h2 style={{ marginBottom: 12, color: 'var(--text-primary)' }}>
            Master Trigonometry in 8 Weeks
          </h2>
          <p style={{ maxWidth: 500, margin: '0 auto 24px', color: 'var(--text-secondary)' }}>
            Personalized AI-powered lessons, adaptive assessments, and your own AI tutor — all in one structured curriculum.
          </p>
        </div>

        <div className="grid-3">
          {courses.length > 0 ? courses.map(c => (
            <div key={c.id} className="card card-hover">
              <div style={{ fontSize: 36, marginBottom: 12 }}>🎯</div>
              <h3 style={{ marginBottom: 8, color: 'var(--text-primary)' }}>{c.title}</h3>
              <p style={{ fontSize: 14, marginBottom: 20 }}>{c.description}</p>
              <button
                id={`enroll-btn-${c.id}`}
                className="btn btn-primary btn-full"
                onClick={() => handleEnroll(c.id)}
                disabled={enrolling}
              >
                {enrolling ? <Loader2 size={14} /> : null}
                Enroll Now
              </button>
            </div>
          )) : (
            <div className="empty-state" style={{ gridColumn: '1/-1' }}>
              <div className="empty-state-icon">📚</div>
              <h3>No courses available</h3>
              <p>Check back later for available courses.</p>
            </div>
          )}
        </div>
      </div>
    );
  }

  const p = data.progress_summary;
  const pct = Math.round(p.completion_percentage ?? 0);

  return (
    <div>
      <div className="page-header">
        <div>
          <h1 className="page-title">
            Good to see you, {user?.name?.split(' ')[0]} 👋
          </h1>
          <p className="page-subtitle">
            Week {data.current_week} of 8 · {p.completed_lessons}/{p.total_lessons} lessons completed
          </p>
        </div>
        {data.next_recommended_lesson && (
          <button
            className="btn btn-primary"
            onClick={() => navigate(`/curriculum`)}
          >
            <Zap size={15} /> Continue Learning
          </button>
        )}
      </div>

      {/* Progress Overview */}
      <div className="grid-4" style={{ marginBottom: 24 }}>
        <StatTile icon="📚" iconBg="rgba(99,102,241,0.15)" value={`${pct}%`} label="Overall Progress" color="var(--primary)" />
        <StatTile icon="✅" iconBg="rgba(16,185,129,0.15)" value={p.completed_lessons} label="Lessons Done" color="var(--success)" />
        <StatTile icon="📖" iconBg="rgba(34,211,238,0.15)" value={p.total_lessons - p.completed_lessons} label="Remaining" color="var(--accent)" />
        <StatTile icon="🏆" iconBg="rgba(245,158,11,0.15)" value={`W${data.current_week}`} label="Current Week" color="var(--warning)" />
      </div>

      {/* Progress Bar */}
      <div className="card" style={{ marginBottom: 24 }}>
        <div className="flex items-center justify-between mb-8">
          <span style={{ fontSize: 14, fontWeight: 600, color: 'var(--text-secondary)' }}>
            Course Progress — {data.course?.title}
          </span>
          <span style={{ fontWeight: 700, color: 'var(--primary)' }}>{pct}%</span>
        </div>
        <div className="progress-bar" style={{ height: 10 }}>
          <div className="progress-fill" style={{ width: `${pct}%` }} />
        </div>
        <div className="flex justify-between mt-8" style={{ color: 'var(--text-muted)', fontSize: 12 }}>
          <span>{p.completed_topics}/{p.total_topics} topics</span>
          <span>{p.completed_lessons}/{p.total_lessons} lessons</span>
        </div>
      </div>

      <div className="grid-2" style={{ marginBottom: 24 }}>
        {/* Next Lesson */}
        <div className="card">
          <div className="flex items-center gap-8 mb-16">
            <Target size={18} style={{ color: 'var(--primary)' }} />
            <h3 style={{ color: 'var(--text-primary)' }}>Next Lesson</h3>
          </div>
          {data.next_recommended_lesson ? (
            <div
              style={{
                padding: 16,
                background: 'var(--bg-elevated)',
                borderRadius: 'var(--radius-md)',
                border: '1px solid rgba(99,102,241,0.2)',
                cursor: 'pointer',
              }}
              onClick={() => navigate('/curriculum')}
            >
              <div style={{ fontSize: 12, color: 'var(--accent)', fontWeight: 600, marginBottom: 4 }}>
                Week {data.next_recommended_lesson.week_number} · {data.next_recommended_lesson.topic_title}
              </div>
              <div style={{ fontWeight: 600, color: 'var(--text-primary)', marginBottom: 8 }}>
                {data.next_recommended_lesson.title}
              </div>
              {data.next_recommended_lesson.estimated_duration_minutes && (
                <div style={{ fontSize: 12, color: 'var(--text-muted)' }}>
                  ⏱ ~{data.next_recommended_lesson.estimated_duration_minutes} min
                </div>
              )}
              <div className="flex items-center gap-8 mt-16" style={{ color: 'var(--primary)', fontSize: 13, fontWeight: 600 }}>
                Start Now <ArrowRight size={14} />
              </div>
            </div>
          ) : (
            <div className="empty-state" style={{ padding: 24 }}>
              <CheckCircle2 size={32} style={{ color: 'var(--success)' }} />
              <p>All lessons completed! 🎉</p>
            </div>
          )}
        </div>

        {/* Recent Attempts */}
        <div className="card">
          <div className="flex items-center gap-8 mb-16">
            <Star size={18} style={{ color: 'var(--warning)' }} />
            <h3 style={{ color: 'var(--text-primary)' }}>Recent Assessments</h3>
          </div>
          {data.recent_attempts.length > 0 ? (
            <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
              {data.recent_attempts.slice(0, 4).map(a => (
                <div key={a.id} style={{
                  padding: '10px 14px',
                  background: 'var(--bg-elevated)',
                  borderRadius: 'var(--radius-md)',
                  display: 'flex',
                  justifyContent: 'space-between',
                  alignItems: 'center',
                  gap: 12,
                }}>
                  <div>
                    <div style={{ fontSize: 13, fontWeight: 600, color: 'var(--text-primary)' }}>
                      {a.test_title}
                    </div>
                    <div style={{ fontSize: 11, color: 'var(--text-muted)' }}>
                      {new Date(a.submitted_at).toLocaleDateString()}
                    </div>
                  </div>
                  <div style={{ textAlign: 'right' }}>
                    <div style={{ fontWeight: 700, color: a.passed ? 'var(--success)' : 'var(--danger)', fontSize: 16 }}>
                      {Math.round(a.percentage)}%
                    </div>
                    <span className={`badge ${a.passed ? 'badge-success' : 'badge-danger'}`} style={{ fontSize: 10 }}>
                      {a.passed ? 'Passed' : 'Failed'}
                    </span>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="empty-state" style={{ padding: 24 }}>
              <BookOpen size={28} style={{ color: 'var(--text-muted)' }} />
              <p>No assessments yet. Start learning!</p>
            </div>
          )}
        </div>
      </div>

      {/* Weak Topics & Cert Status */}
      <div className="grid-2">
        {/* Weak Topics */}
        {data.weak_topics.length > 0 && (
          <div className="card">
            <div className="flex items-center gap-8 mb-16">
              <AlertTriangle size={18} style={{ color: 'var(--warning)' }} />
              <h3 style={{ color: 'var(--text-primary)' }}>Topics to Review</h3>
            </div>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
              {data.weak_topics.map(t => (
                <div key={t.topic_id} style={{
                  padding: '10px 14px',
                  background: 'rgba(245,158,11,0.05)',
                  border: '1px solid rgba(245,158,11,0.2)',
                  borderRadius: 'var(--radius-md)',
                  display: 'flex',
                  justifyContent: 'space-between',
                  alignItems: 'center',
                }}>
                  <div>
                    <div style={{ fontSize: 13, fontWeight: 600, color: 'var(--text-primary)' }}>
                      {t.topic_title}
                    </div>
                    <div style={{ fontSize: 11, color: 'var(--text-muted)' }}>Week {t.week_number}</div>
                  </div>
                  <div style={{ fontWeight: 700, color: 'var(--warning)' }}>
                    {Math.round(t.latest_percentage)}%
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Certificate / Final Exam Status */}
        <div className="card">
          <div className="flex items-center gap-8 mb-16">
            <Award size={18} style={{ color: 'var(--accent)' }} />
            <h3 style={{ color: 'var(--text-primary)' }}>Certification Status</h3>
          </div>
          {data.certificate?.status === 'issued' ? (
            <div style={{ textAlign: 'center', padding: 16 }}>
              <div style={{ fontSize: 40, marginBottom: 8 }}>🎓</div>
              <div style={{ fontWeight: 700, color: 'var(--success)', marginBottom: 4 }}>Certificate Issued!</div>
              <div style={{ fontSize: 13, color: 'var(--text-muted)', marginBottom: 16 }}>
                Score: {data.certificate.final_score}%
              </div>
              <button className="btn btn-secondary btn-sm" onClick={() => navigate('/certificate')}>
                View Certificate
              </button>
            </div>
          ) : data.final_exam_eligibility?.eligible ? (
            <div style={{ textAlign: 'center', padding: 16 }}>
              <div style={{ fontSize: 40, marginBottom: 8 }}>🚀</div>
              <div style={{ fontWeight: 600, color: 'var(--text-primary)', marginBottom: 8 }}>
                Ready for the Final Exam!
              </div>
              <button className="btn btn-primary" onClick={() => navigate('/final-exam')}>
                Take Final Exam <ArrowRight size={14} />
              </button>
            </div>
          ) : (
            <div style={{ padding: 16 }}>
              <div style={{ marginBottom: 16 }}>
                <div style={{ fontSize: 13, color: 'var(--text-muted)', marginBottom: 8 }}>
                  Topics completed
                </div>
                <div className="progress-bar">
                  <div className="progress-fill" style={{
                    width: `${((data.final_exam_eligibility?.topics_completed ?? 0) / (data.final_exam_eligibility?.total_topics ?? 1)) * 100}%`,
                  }} />
                </div>
                <div style={{ fontSize: 12, color: 'var(--text-muted)', marginTop: 6 }}>
                  {data.final_exam_eligibility?.topics_completed ?? 0}/{data.final_exam_eligibility?.total_topics ?? 0} topics
                </div>
              </div>
              <p style={{ fontSize: 13, color: 'var(--text-secondary)' }}>
                {data.final_exam_eligibility?.reason ?? 'Complete all topics to unlock the final exam.'}
              </p>
              <button className="btn btn-secondary btn-sm mt-16" onClick={() => navigate('/curriculum')}>
                Continue Learning <TrendingUp size={13} />
              </button>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

function StatTile({
  icon, iconBg, value, label, color,
}: { icon: string; iconBg: string; value: string | number; label: string; color: string }) {
  return (
    <div className="stat-tile">
      <div className="stat-tile-icon" style={{ background: iconBg }}>
        <span style={{ fontSize: 20 }}>{icon}</span>
      </div>
      <div className="stat-tile-value" style={{ color }}>{value}</div>
      <div className="stat-tile-label">{label}</div>
    </div>
  );
}
