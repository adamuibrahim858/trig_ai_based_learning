import { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { assessmentService } from '../services/assessmentService';
import { useToast } from '../contexts/ToastContext';
import { getErrorMessage } from '../services/api';
import type { Test } from '../types/api';
import { Star, Clock, ArrowRight, Loader2 } from 'lucide-react';

export default function AssessmentsPage() {
  const navigate = useNavigate();
  const toast = useToast();
  const [tests, setTests] = useState<Test[]>([]);
  const [loading, setLoading] = useState(true);
  const [starting, setStarting] = useState<number | null>(null);

  const load = useCallback(async () => {
    try {
      setLoading(true);
      const t = await assessmentService.getTests();
      setTests(t);
    } catch (err) {
      toast.error(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    load();
  }, [load]);

  const handleStart = async (testId: number) => {
    setStarting(testId);
    try {
      const attempt = await assessmentService.startAttempt(testId);
      navigate(`/attempt/${attempt.id}`);
    } catch (err) {
      toast.error(getErrorMessage(err));
      setStarting(null);
    }
  };

  const typeLabel: Record<string, string> = {
    topic_assessment: 'Topic Assessment',
    lesson_practice: 'Practice Quiz',
    final_exam: 'Final Exam',
  };
  const typeBadge: Record<string, string> = {
    topic_assessment: 'badge-primary',
    lesson_practice: 'badge-accent',
    final_exam: 'badge-warning',
  };

  if (loading) {
    return <div className="loading-center"><div className="spinner" /></div>;
  }

  return (
    <div>
      <div className="page-header">
        <div>
          <h1 className="page-title">Assessments</h1>
          <p className="page-subtitle">Test your knowledge with topic assessments and practice quizzes</p>
        </div>
      </div>

      {tests.length === 0 ? (
        <div className="empty-state">
          <div className="empty-state-icon">📝</div>
          <h3>No assessments available</h3>
          <p>Complete some lessons first to unlock assessments.</p>
          <button className="btn btn-primary mt-16" onClick={() => navigate('/curriculum')}>
            Go to Curriculum
          </button>
        </div>
      ) : (
        <div className="grid-2">
          {tests.map(test => (
            <div key={test.id} className="card card-hover" style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
              {/* Header */}
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                <div style={{
                  width: 44, height: 44,
                  borderRadius: 'var(--radius-md)',
                  background: 'rgba(99,102,241,0.12)',
                  display: 'flex', alignItems: 'center', justifyContent: 'center',
                  fontSize: 22,
                }}>
                  {test.type === 'final_exam' ? '🏆' : test.type === 'topic_assessment' ? '📊' : '📝'}
                </div>
                <span className={`badge ${typeBadge[test.type] ?? 'badge-muted'}`}>
                  {typeLabel[test.type] ?? test.type}
                </span>
              </div>

              <div>
                <h3 style={{ marginBottom: 6, color: 'var(--text-primary)' }}>{test.title}</h3>
                {test.description && (
                  <p style={{ fontSize: 13, color: 'var(--text-muted)' }}>{test.description}</p>
                )}
              </div>

              {/* Stats row */}
              <div style={{ display: 'flex', gap: 20, flexWrap: 'wrap' }}>
                <span style={{ display: 'flex', alignItems: 'center', gap: 5, fontSize: 13, color: 'var(--text-muted)' }}>
                  <Star size={13} style={{ color: 'var(--warning)' }} />
                  Pass: {test.pass_mark}/{test.total_marks}
                </span>
                {test.duration_minutes && (
                  <span style={{ display: 'flex', alignItems: 'center', gap: 5, fontSize: 13, color: 'var(--text-muted)' }}>
                    <Clock size={13} />
                    {test.duration_minutes} min
                  </span>
                )}
                {test.attempts_allowed && (
                  <span style={{ fontSize: 13, color: 'var(--text-muted)' }}>
                    {test.attempts_allowed} attempt{test.attempts_allowed !== 1 ? 's' : ''}
                  </span>
                )}
              </div>

              <button
                id={`start-test-${test.id}`}
                className="btn btn-primary btn-full"
                onClick={() => handleStart(test.id)}
                disabled={starting === test.id}
              >
                {starting === test.id ? (
                  <><Loader2 size={14} className="spin-icon" /> Starting…</>
                ) : (
                  <>Start Test <ArrowRight size={14} /></>
                )}
              </button>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
