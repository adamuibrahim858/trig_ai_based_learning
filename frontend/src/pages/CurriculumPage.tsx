import { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { curriculumService } from '../services/curriculumService';
import { assessmentService } from '../services/assessmentService';
import { useToast } from '../contexts/ToastContext';
import { getErrorMessage } from '../services/api';
import type { Topic, Lesson } from '../types/api';
import {
  ChevronDown, ChevronRight, CheckCircle2, Lock,
  PlayCircle, Clock, ArrowRight, Award, Loader2,
} from 'lucide-react';

export default function CurriculumPage() {
  const navigate = useNavigate();
  const toast = useToast();
  const [topics, setTopics] = useState<Topic[]>([]);
  const [loading, setLoading] = useState(true);
  const [expanded, setExpanded] = useState<Record<number, boolean>>({});
  const [startingAssessmentId, setStartingAssessmentId] = useState<number | null>(null);

  const load = useCallback(async () => {
    try {
      setLoading(true);
      let t = await curriculumService.getCourseTopics(1);
      
      // If Week 1 is locked because the user has not yet enrolled, enroll automatically
      if (t.length > 0 && !t[0].is_accessible) {
        try {
          await curriculumService.enrollInCourse(1);
          t = await curriculumService.getCourseTopics(1);
        } catch {
          // ignore error if already enrolled
        }
      }

      setTopics(t);
      // Auto-expand first in-progress or available topic
      const first = t.find(tp => tp.status === 'in_progress' || tp.status === 'available') || t[0];
      if (first) setExpanded({ [first.id]: true });
    } catch (err) {
      toast.error(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    load();
  }, [load]);

  const toggle = (id: number) =>
    setExpanded(prev => ({ ...prev, [id]: !prev[id] }));

  const scrollToTopic = (id: number) => {
    toggle(id);
    setTimeout(() => {
      document.getElementById(`topic-${id}`)?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }, 50);
  };

  const handleStartTopicAssessment = async (assessmentId: number) => {
    try {
      setStartingAssessmentId(assessmentId);
      const attempt = await assessmentService.startAttempt(assessmentId);
      navigate(`/attempt/${attempt.id}`);
    } catch (err) {
      toast.error(getErrorMessage(err));
      setStartingAssessmentId(null);
    }
  };

  const statusIcon = (status: Lesson['status'], accessible: boolean) => {
    if (!accessible) return <Lock size={14} style={{ color: 'var(--text-muted)' }} />;
    if (status === 'completed') return <CheckCircle2 size={14} style={{ color: 'var(--success)' }} />;
    if (status === 'in_progress') return <PlayCircle size={14} style={{ color: 'var(--primary)' }} />;
    return <div style={{ width: 14, height: 14, borderRadius: '50%', border: '2px solid var(--border-bright)' }} />;
  };

  const topicStatusBadge = (status: Topic['status']) => {
    const map: Record<string, string> = {
      completed: 'badge-success',
      in_progress: 'badge-primary',
      available: 'badge-accent',
      locked: 'badge-muted',
    };
    const labels: Record<string, string> = {
      completed: 'Completed', in_progress: 'In Progress', available: 'Available', locked: 'Locked',
    };
    return <span className={`badge ${map[status] ?? 'badge-muted'}`}>{labels[status] ?? status}</span>;
  };

  // Count progress
  const completed = topics.filter(t => t.status === 'completed').length;
  const pct = topics.length ? Math.round((completed / topics.length) * 100) : 0;

  if (loading) {
    return <div className="loading-center"><div className="spinner" /></div>;
  }

  return (
    <div>
      {/* Compact Page Header with inline progress */}
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 16, marginBottom: 20, flexWrap: 'wrap' }}>
        <div>
          <h1 className="page-title" style={{ marginBottom: 2 }}>Curriculum</h1>
          <p className="page-subtitle" style={{ margin: 0 }}>8-week structured trigonometry program</p>
        </div>
        <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
          <div style={{ width: 100, height: 6, borderRadius: 999, background: 'var(--bg-elevated)', overflow: 'hidden' }}>
            <div style={{ width: `${pct}%`, height: '100%', borderRadius: 999, background: 'linear-gradient(90deg, var(--primary), var(--accent))', transition: 'width 0.4s ease' }} />
          </div>
          <span style={{ fontSize: 13, fontWeight: 600, color: 'var(--text-secondary)', whiteSpace: 'nowrap' }}>{completed}/{topics.length} weeks</span>
        </div>
      </div>

      {/* Horizontal Week Pill Strip */}
      <div style={{
        display: 'flex',
        gap: 6,
        marginBottom: 20,
        overflowX: 'auto',
        WebkitOverflowScrolling: 'touch',
        paddingBottom: 4,
      }}>
        {topics.map(t => {
          const isActive = t.status === 'in_progress' || t.status === 'available';
          const isDone = t.status === 'completed';
          return (
            <button
              key={t.id}
              type="button"
              onClick={() => scrollToTopic(t.id)}
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: 6,
                padding: '6px 12px',
                borderRadius: 999,
                border: `1px solid ${isDone ? 'rgba(22,163,74,0.25)' : isActive ? 'rgba(79,70,229,0.25)' : 'var(--border)'}`,
                background: isDone ? 'rgba(22,163,74,0.08)' : isActive ? 'rgba(79,70,229,0.06)' : 'var(--bg-card)',
                color: isDone ? 'var(--success)' : isActive ? 'var(--primary)' : 'var(--text-muted)',
                fontSize: 12,
                fontWeight: 600,
                cursor: 'pointer',
                whiteSpace: 'nowrap',
                flexShrink: 0,
                transition: 'all 0.15s ease',
              }}
            >
              {isDone ? <CheckCircle2 size={13} /> : isActive ? <PlayCircle size={13} /> : <Lock size={11} />}
              W{t.week_number}
            </button>
          );
        })}
      </div>

      {/* Topic Accordion — compact gap and padding */}
      <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
        {topics.map(topic => (
          <div key={topic.id} className="card" style={{ padding: 0, overflow: 'hidden' }}>
            {/* Topic Header — reduced padding */}
            <button
              id={`topic-${topic.id}`}
              onClick={() => toggle(topic.id)}
              style={{
                width: '100%', display: 'flex', alignItems: 'center', gap: 12,
                padding: '12px 16px', background: 'none', border: 'none',
                cursor: 'pointer',
                textAlign: 'left',
              }}
            >
              <div style={{
                width: 34, height: 34, borderRadius: 'var(--radius-sm)', flexShrink: 0,
                display: 'flex', alignItems: 'center', justifyContent: 'center',
                background: topic.status === 'completed' ? 'rgba(22,163,74,0.1)'
                           : topic.status === 'in_progress' ? 'rgba(79,70,229,0.1)'
                           : topic.is_accessible ? 'rgba(2,132,199,0.08)' : 'var(--bg-elevated)',
                fontSize: 14, fontWeight: 700,
                color: topic.status === 'completed' ? 'var(--success)'
                     : topic.status === 'in_progress' ? 'var(--primary)'
                     : topic.is_accessible ? 'var(--accent)' : 'var(--text-muted)',
              }}>
                {topic.status === 'completed' ? <CheckCircle2 size={16} /> : topic.is_accessible ? `W${topic.week_number}` : <Lock size={14} />}
              </div>

              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                  <span style={{ fontWeight: 600, color: 'var(--text-primary)', fontSize: 14 }}>
                    Week {topic.week_number}: {topic.title}
                  </span>
                  {topicStatusBadge(topic.status)}
                </div>
              </div>

              <div style={{ color: 'var(--text-muted)', flexShrink: 0 }}>
                {expanded[topic.id] ? <ChevronDown size={16} /> : <ChevronRight size={16} />}
              </div>
            </button>

            {/* Lessons & Topic Assessment */}
            {expanded[topic.id] && (
              <div style={{
                borderTop: '1px solid var(--border)',
                padding: '10px 16px 14px',
                display: 'flex',
                flexDirection: 'column',
                gap: 6,
              }}>
                {/* Description inline if available */}
                {topic.description && (
                  <p style={{
                    fontSize: 12.5, color: 'var(--text-muted)', lineHeight: 1.5,
                    margin: '0 0 4px', padding: 0,
                  }}>
                    {topic.description}
                  </p>
                )}

                {!topic.is_accessible && (
                  <div style={{
                    padding: '8px 12px',
                    background: 'rgba(220,38,38,0.04)',
                    border: '1px solid rgba(220,38,38,0.12)',
                    borderRadius: 'var(--radius-sm)',
                    fontSize: 12.5,
                    color: 'var(--text-muted)',
                  }}>
                    🔒 {topic.lock_reason ?? 'Complete previous topics to unlock this week.'}
                  </div>
                )}

                {topic.lessons?.map((lesson) => (
                  <LessonRow
                    key={lesson.id}
                    lesson={lesson}
                    onNavigate={() => {
                      if (lesson.is_accessible) navigate(`/lesson/${lesson.id}`);
                    }}
                    statusIcon={statusIcon(lesson.status, lesson.is_accessible)}
                  />
                ))}

                {/* Topic Assessment */}
                {topic.assessment_id && topic.is_accessible && (
                  <div style={{
                    marginTop: 4,
                    padding: '10px 14px',
                    borderRadius: 'var(--radius-sm)',
                    background: 'rgba(79,70,229,0.04)',
                    border: '1px solid rgba(79,70,229,0.15)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    gap: 10,
                    flexWrap: 'wrap',
                  }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                      <Award size={16} style={{ color: 'var(--primary)', flexShrink: 0 }} />
                      <div>
                        <div style={{ fontWeight: 600, fontSize: 13, color: 'var(--text-primary)' }}>
                          Week {topic.week_number} Assessment
                        </div>
                        <div style={{ fontSize: 11.5, color: 'var(--text-muted)' }}>
                          Validate your understanding
                        </div>
                      </div>
                    </div>

                    <button
                      className="btn btn-primary btn-sm"
                      onClick={() => handleStartTopicAssessment(topic.assessment_id!)}
                      disabled={startingAssessmentId === topic.assessment_id}
                      style={{ fontSize: 12, padding: '5px 12px' }}
                    >
                      {startingAssessmentId === topic.assessment_id ? (
                        <><Loader2 size={12} className="spin-icon" /> Starting…</>
                      ) : (
                        <>Take Quiz <ArrowRight size={12} /></>
                      )}
                    </button>
                  </div>
                )}
              </div>
            )}
          </div>
        ))}
      </div>
    </div>
  );
}

function LessonRow({
  lesson, onNavigate, statusIcon,
}: { lesson: Lesson; onNavigate: () => void; statusIcon: React.ReactNode }) {
  return (
    <div
      onClick={lesson.is_accessible ? onNavigate : undefined}
      style={{
        padding: '8px 12px',
        borderRadius: 'var(--radius-sm)',
        border: '1px solid var(--border)',
        background: lesson.status === 'in_progress' ? 'rgba(79,70,229,0.03)'
                  : lesson.status === 'completed' ? 'rgba(22,163,74,0.02)'
                  : 'var(--bg-card)',
        cursor: lesson.is_accessible ? 'pointer' : 'default',
        display: 'flex',
        alignItems: 'center',
        gap: 10,
        transition: 'all 0.15s',
        opacity: lesson.is_accessible ? 1 : 0.5,
      }}
    >
      <div style={{ flexShrink: 0 }}>{statusIcon}</div>

      <div style={{ flex: 1, minWidth: 0 }}>
        <div style={{ fontWeight: 600, fontSize: 13, color: 'var(--text-primary)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
          {lesson.title}
        </div>
        {(lesson.estimated_minutes || (lesson.lock_reason && !lesson.is_accessible)) && (
          <div style={{ fontSize: 11.5, color: 'var(--text-muted)', display: 'flex', gap: 10, marginTop: 1 }}>
            {lesson.estimated_minutes && (
              <span style={{ display: 'flex', alignItems: 'center', gap: 3 }}>
                <Clock size={10} /> {lesson.estimated_minutes} min
              </span>
            )}
            {lesson.lock_reason && !lesson.is_accessible && (
              <span>{lesson.lock_reason}</span>
            )}
          </div>
        )}
      </div>

      {lesson.is_accessible && (
        <ArrowRight size={14} style={{ color: 'var(--text-muted)', flexShrink: 0 }} />
      )}
    </div>
  );
}
