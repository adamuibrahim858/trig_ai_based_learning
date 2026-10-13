import { useState } from 'react';
import { useParams, useLocation, useNavigate } from 'react-router-dom';
import { assessmentService } from '../services/assessmentService';
import { useToast } from '../contexts/ToastContext';
import { getErrorMessage } from '../services/api';
import type { ReviewData } from '../types/api';
import { CheckCircle2, XCircle, ArrowLeft, BookOpen, Trophy } from 'lucide-react';

interface ResultState {
  score: number;
  total_marks: number;
  percentage: number;
  passed: boolean;
  pass_mark: number;
}

export default function ResultPage() {
  const { attemptId } = useParams<{ attemptId: string }>();
  const location = useLocation();
  const navigate = useNavigate();
  const toast = useToast();
  const result = location.state as ResultState | null;
  const [review, setReview] = useState<ReviewData | null>(null);
  const [showReview, setShowReview] = useState(false);
  const [loadingReview, setLoadingReview] = useState(false);
  const [tab, setTab] = useState<'result' | 'review'>('result');

  const loadReview = async () => {
    if (!attemptId) return;
    setLoadingReview(true);
    try {
      const r = await assessmentService.getAttemptReview(parseInt(attemptId));
      setReview(r);
      setShowReview(true);
      setTab('review');
    } catch (err) {
      toast.error(getErrorMessage(err));
    } finally {
      setLoadingReview(false);
    }
  };

  if (!result) {
    return (
      <div className="loading-center">
        <div>
          <p>Result not found.</p>
          <button className="btn btn-primary mt-16" onClick={() => navigate('/assessments')}>
            Back to Assessments
          </button>
        </div>
      </div>
    );
  }

  const pct = Math.round(result.percentage);
  const passed = result.passed;
  const color = passed ? 'var(--success)' : 'var(--danger)';
  const glow = passed ? 'var(--success-glow)' : 'var(--danger-glow)';

  return (
    <div style={{ maxWidth: 720, margin: '0 auto' }}>
      <button className="btn btn-ghost btn-sm" onClick={() => navigate('/assessments')} style={{ marginBottom: 20 }}>
        <ArrowLeft size={15} /> Back to Assessments
      </button>

      {/* Tabs */}
      <div className="tabs">
        <button className={`tab-btn ${tab === 'result' ? 'active' : ''}`} onClick={() => setTab('result')}>
          Results
        </button>
        {showReview && (
          <button className={`tab-btn ${tab === 'review' ? 'active' : ''}`} onClick={() => setTab('review')}>
            Review Answers
          </button>
        )}
      </div>

      {tab === 'result' && (
        <>
          {/* Score Card */}
          <div className="card" style={{ textAlign: 'center', marginBottom: 24, padding: 40 }}>
            <div style={{
              width: 120, height: 120, borderRadius: '50%', margin: '0 auto 20px',
              background: `conic-gradient(${color} ${pct * 3.6}deg, var(--bg-elevated) 0)`,
              display: 'flex', alignItems: 'center', justifyContent: 'center',
              boxShadow: `0 0 40px ${glow}`,
              position: 'relative',
            }}>
              <div style={{
                width: 90, height: 90, borderRadius: '50%',
                background: 'var(--bg-card)',
                display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center',
              }}>
                <div style={{ fontFamily: 'var(--font-display)', fontSize: '1.8rem', fontWeight: 800, color, lineHeight: 1 }}>
                  {pct}%
                </div>
              </div>
            </div>

            <div style={{ fontSize: 24, marginBottom: 8 }}>
              {passed ? '🎉' : '💪'}
            </div>
            <h2 style={{ marginBottom: 8, color: color }}>
              {passed ? 'Well done! You passed!' : 'Keep practicing!'}
            </h2>
            <p style={{ fontSize: 14, marginBottom: 20 }}>
              You scored <strong style={{ color: 'var(--text-primary)' }}>{result.score}/{result.total_marks}</strong> marks.
              Pass mark was <strong style={{ color: 'var(--text-primary)' }}>{result.pass_mark}/{result.total_marks}</strong>.
            </p>
            <span className={`badge ${passed ? 'badge-success' : 'badge-danger'}`} style={{ fontSize: 14, padding: '6px 16px' }}>
              {passed ? '✓ Passed' : '✕ Failed'}
            </span>
          </div>

          {/* Actions */}
          <div style={{ display: 'flex', gap: 12, justifyContent: 'center', flexWrap: 'wrap' }}>
            <button className="btn btn-secondary" onClick={loadReview} disabled={loadingReview}>
              <BookOpen size={15} />
              {loadingReview ? 'Loading…' : 'Review Answers'}
            </button>
            <button className="btn btn-primary" onClick={() => navigate('/curriculum')}>
              <ArrowLeft size={15} /> Back to Curriculum
            </button>
            {passed && (
              <button className="btn btn-secondary" onClick={() => navigate('/certificate')} style={{ color: 'var(--warning)', borderColor: 'rgba(245,158,11,0.3)' }}>
                <Trophy size={15} /> Check Certificate
              </button>
            )}
          </div>
        </>
      )}

      {tab === 'review' && review && (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
          <div style={{ fontWeight: 600, color: 'var(--text-secondary)', marginBottom: 4 }}>
            {review.test_title} · {Math.round(review.percentage)}% · {review.passed ? '✓ Passed' : '✕ Failed'}
          </div>
          {review.questions.map((q, i) => (
            <div key={q.question_id} className="card" style={{ borderColor: q.is_correct ? 'rgba(16,185,129,0.2)' : 'rgba(239,68,68,0.2)' }}>
              <div style={{ display: 'flex', gap: 10, marginBottom: 12 }}>
                {q.is_correct
                  ? <CheckCircle2 size={18} style={{ color: 'var(--success)', flexShrink: 0 }} />
                  : <XCircle size={18} style={{ color: 'var(--danger)', flexShrink: 0 }} />
                }
                <div style={{ flex: 1 }}>
                  <div style={{ fontSize: 13, color: 'var(--text-muted)', marginBottom: 4 }}>
                    Q{i + 1} · {q.marks_awarded}/{q.marks} marks
                  </div>
                  <div style={{ fontWeight: 600, color: 'var(--text-primary)', fontSize: 15 }}>
                    {q.question_text}
                  </div>
                </div>
              </div>

              <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                {q.options.map(opt => {
                  const isSelected = opt.id === q.selected_option_id;
                  const cls = opt.is_correct ? 'correct' : isSelected && !opt.is_correct ? 'incorrect' : '';
                  return (
                    <div key={opt.id} className={`quiz-option ${cls}`} style={{ cursor: 'default', pointerEvents: 'none' }}>
                      <div className="quiz-option-indicator" />
                      <span style={{ fontSize: 14 }}>{opt.option_text}</span>
                      {opt.is_correct && (
                        <span style={{ marginLeft: 'auto', color: 'var(--success)', fontSize: 12, fontWeight: 700 }}>✓ Correct</span>
                      )}
                    </div>
                  );
                })}
              </div>

              {q.explanation && (
                <div style={{
                  marginTop: 12,
                  padding: '10px 14px',
                  background: 'rgba(99,102,241,0.06)',
                  border: '1px solid rgba(99,102,241,0.15)',
                  borderRadius: 'var(--radius-md)',
                  fontSize: 13,
                  color: 'var(--text-secondary)',
                }}>
                  <strong style={{ color: 'var(--primary)' }}>Explanation: </strong>
                  {q.explanation}
                </div>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
