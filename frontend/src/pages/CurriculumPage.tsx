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
      const t = await curriculumService.getCourseTopics(1);
      setTopics(t);
      // Auto-expand first in-progress or available topic
      const first = t.find(tp => tp.status === 'in_progress' || tp.status === 'available');
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
    if (!accessible) return <Lock size={15} style={{ color: 'var(--text-muted)' }} />;
    if (status === 'completed') return <CheckCircle2 size={15} style={{ color: 'var(--success)' }} />;
    if (status === 'in_progress') return <PlayCircle size={15} style={{ color: 'var(--primary)' }} />;
    return <div style={{ width: 15, height: 15, borderRadius: '50%', border: '2px solid var(--border-bright)' }} />;
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

  if (loading) {
    return <div className="loading-center"><div className="spinner" /></div>;
  }

  return (
    <div>
      <div className="page-header">
        <div>
          <h1 className="page-title">Curriculum</h1>
          <p className="page-subtitle">8-week structured trigonometry program</p>
        </div>
      </div>

      {/* Week grid overview */}
      <div className="grid-4" style={{ marginBottom: 32 }}>
        {topics.map(t => (
          <div
            key={t.id}
            className="week-row"
            onClick={() => toggle(t.id)}
            style={{ cursor: 'pointer' }}
          >
            <div className={`week-number ${t.status === 'completed' ? 'done' : t.status === 'in_progress' || t.status === 'available' ? 'active' : ''}`}>
              W{t.week_number}
            </div>
            <div style={{ minWidth: 0 }}>
              <div style={{ fontSize: 12, fontWeight: 600, color: 'var(--text-primary)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                {t.title}
              </div>
              {topicStatusBadge(t.status)}
            </div>
          </div>
        ))}
      </div>

      {/* Topic accordion */}
      <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
        {topics.map(topic => (
          <div key={topic.id} className="card" style={{ padding: 0, overflow: 'hidden' }}>
            {/* Topic Header */}
            <button
              id={`topic-${topic.id}`}
              onClick={() => toggle(topic.id)}
              style={{
                width: '100%', display: 'flex', alignItems: 'center', gap: 14,
                padding: '18px 22px', background: 'none', border: 'none',
                cursor: topic.is_accessible ? 'pointer' : 'default',
                textAlign: 'left',
              }}
            >
              <div style={{
                width: 40, height: 40, borderRadius: 'var(--radius-md)', flexShrink: 0,
                display: 'flex', alignItems: 'center', justifyContent: 'center',
                background: topic.status === 'completed' ? 'rgba(16,185,129,0.15)' :
                            topic.status === 'in_progress' ? 'rgba(99,102,241,0.15)' :
                            topic.is_accessible ? 'rgba(34,211,238,0.1)' : 'var(--bg-elevated)',
                fontSize: 18,
              }}>
                {topic.status === 'completed' ? '✅' : topic.is_accessible ? `W${topic.week_number}` : '🔒'}
              </div>

              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 2 }}>
                  <span style={{ fontWeight: 700, color: 'var(--text-primary)', fontSize: 15 }}>
                    Week {topic.week_number}: {topic.title}
                  </span>
                  {topicStatusBadge(topic.status)}
                </div>
                {topic.description && (
                  <p style={{ fontSize: 13, color: 'var(--text-muted)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                    {topic.description}
                  </p>
                )}
              </div>

              <div style={{ color: 'var(--text-muted)', flexShrink: 0 }}>
                {expanded[topic.id] ? <ChevronDown size={18} /> : <ChevronRight size={18} />}
              </div>
            </button>

            {/* Lessons & Topic Assessment */}
            {expanded[topic.id] && (
              <div style={{
                borderTop: '1px solid var(--border)',
                padding: '14px 22px 18px',
                display: 'flex',
                flexDirection: 'column',
                gap: 10,
              }}>
                {!topic.is_accessible && (
                  <div style={{
                    padding: '10px 14px',
                    background: 'rgba(239,68,68,0.05)',
                    border: '1px solid rgba(239,68,68,0.15)',
                    borderRadius: 'var(--radius-md)',
                    fontSize: 13,
                    color: 'var(--text-muted)',
                    marginBottom: 4,
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

                {/* Topic Assessment Card/Action */}
                {topic.assessment_id && topic.is_accessible && (
                  <div style={{
                    marginTop: 8,
                    padding: '14px 16px',
                    borderRadius: 'var(--radius-md)',
                    background: 'rgba(99,102,241,0.06)',
                    border: '1px solid rgba(99,102,241,0.2)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    gap: 12,
                    flexWrap: 'wrap',
                  }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                      <Award size={18} style={{ color: 'var(--primary)' }} />
                      <div>
                        <div style={{ fontWeight: 600, fontSize: 14, color: 'var(--text-primary)' }}>
                          Week {topic.week_number} Mastery Assessment
                        </div>
                        <div style={{ fontSize: 12, color: 'var(--text-muted)' }}>
                          Complete after lessons to validate your understanding
                        </div>
                      </div>
                    </div>

                    <button
                      className="btn btn-primary btn-sm"
                      onClick={() => handleStartTopicAssessment(topic.assessment_id!)}
                      disabled={startingAssessmentId === topic.assessment_id}
                    >
                      {startingAssessmentId === topic.assessment_id ? (
                        <><Loader2 size={13} className="spin-icon" /> Starting…</>
                      ) : (
                        <>Take Assessment <ArrowRight size={13} /></>
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
        padding: '12px 14px',
        borderRadius: 'var(--radius-md)',
        border: '1px solid var(--border)',
        background: lesson.status === 'in_progress' ? 'rgba(99,102,241,0.05)'
                  : lesson.status === 'completed' ? 'rgba(16,185,129,0.03)'
                  : 'var(--bg-elevated)',
        cursor: lesson.is_accessible ? 'pointer' : 'default',
        display: 'flex',
        alignItems: 'center',
        gap: 12,
        transition: 'all 0.15s',
        opacity: lesson.is_accessible ? 1 : 0.55,
      }}
    >
      <div style={{ flexShrink: 0 }}>{statusIcon}</div>

      <div style={{ flex: 1, minWidth: 0 }}>
        <div style={{ fontWeight: 600, fontSize: 14, color: 'var(--text-primary)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
          {lesson.title}
        </div>
        <div style={{ fontSize: 12, color: 'var(--text-muted)', display: 'flex', gap: 12, marginTop: 2 }}>
          {lesson.estimated_minutes && (
            <span style={{ display: 'flex', alignItems: 'center', gap: 3 }}>
              <Clock size={11} /> {lesson.estimated_minutes} min
            </span>
          )}
          {lesson.lock_reason && !lesson.is_accessible && (
            <span>{lesson.lock_reason}</span>
          )}
        </div>
      </div>

      {lesson.is_accessible && (
        <ArrowRight size={15} style={{ color: 'var(--text-muted)', flexShrink: 0 }} />
      )}
    </div>
  );
}
