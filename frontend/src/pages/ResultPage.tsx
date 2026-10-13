import { useState, useEffect, useCallback } from 'react';
import { useParams, useLocation, useNavigate } from 'react-router-dom';
import { assessmentService } from '../services/assessmentService';
import { useToast } from '../contexts/ToastContext';
import { getErrorMessage } from '../services/api';
import type { ReviewData } from '../types/api';
import {
  CheckCircle2, XCircle, ArrowLeft, BookOpen, Trophy,
  RotateCcw, Sparkles
} from 'lucide-react';

interface ResultData {
  test_title?: string;
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

  const stateResult = location.state as ResultData | null;
  const [result, setResult] = useState<ResultData | null>(stateResult);
  const [review, setReview] = useState<ReviewData | null>(null);
  const [loading, setLoading] = useState(!stateResult);
  const [loadingReview, setLoadingReview] = useState(false);
  const [tab, setTab] = useState<'result' | 'review'>('result');

  const loadResultAndReview = useCallback(async (id: number) => {
    try {
      setLoading(true);
      // Fetch both attempt result and review in parallel
      const [resData, reviewData] = await Promise.allSettled([
        assessmentService.getAttemptResult(id),
        assessmentService.getAttemptReview(id),
      ]);

      if (resData.status === 'fulfilled') {
        const att = resData.value;
        const passMark = reviewData.status === 'fulfilled' ? reviewData.value.pass_mark : 70;
        setResult({
          test_title: att.test?.title,
          score: att.score,
          total_marks: att.total_marks,
          percentage: att.percentage,
          passed: att.passed,
          pass_mark: passMark,
        });
      }

      if (reviewData.status === 'fulfilled') {
        setReview(reviewData.value);
      }
    } catch (err) {
      toast.error(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    if (!attemptId) return;
    const id = parseInt(attemptId);
    if (!result) {
      loadResultAndReview(id);
    }
  }, [attemptId, result, loadResultAndReview]);

  const loadReview = async () => {
    if (review) {
      setTab('review');
      return;
    }
    if (!attemptId) return;
    setLoadingReview(true);
    try {
      const r = await assessmentService.getAttemptReview(parseInt(attemptId));
      setReview(r);
      setTab('review');
    } catch (err) {
      toast.error(getErrorMessage(err));
    } finally {
      setLoadingReview(false);
    }
  };

  if (loading) {
    return (
      <div className="loading-center" style={{ minHeight: '50vh' }}>
        <div className="spinner" />
      </div>
    );
  }

  if (!result) {
    return (
      <div className="card" style={{ textAlign: 'center', padding: '60px 24px', maxWidth: 540, margin: '40px auto' }}>
        <div style={{ fontSize: 48, marginBottom: 16 }}>🔍</div>
        <h2 style={{ marginBottom: 12 }}>Result not found</h2>
        <p style={{ color: 'var(--text-muted)', marginBottom: 24 }}>
          Could not locate the assessment attempt record. It may not exist or has been removed.
        </p>
        <button className="btn btn-primary" onClick={() => navigate('/assessments')}>
          Back to Assessments
        </button>
      </div>
    );
  }

  const pct = Math.round(result.percentage);
  const passed = result.passed;
  const color = passed ? 'var(--success)' : 'var(--danger)';
  const glow = passed ? 'var(--success-glow)' : 'var(--danger-glow)';

  return (
    <div style={{ maxWidth: 760, margin: '0 auto' }}>
      <button
        className="btn btn-ghost btn-sm"
        onClick={() => navigate('/assessments')}
        style={{ marginBottom: 20 }}
      >
        <ArrowLeft size={15} /> Back to Assessments
      </button>

      {/* Tabs */}
      <div className="tabs" style={{ marginBottom: 20 }}>
        <button
          className={`tab-btn ${tab === 'result' ? 'active' : ''}`}
          onClick={() => setTab('result')}
        >
          Results Summary
        </button>
        <button
          className={`tab-btn ${tab === 'review' ? 'active' : ''}`}
          onClick={loadReview}
        >
          Detailed Review {review && `(${review.questions.length} questions)`}
        </button>
      </div>

      {tab === 'result' && (
        <>
          {/* Score Card */}
          <div className="card" style={{ textAlign: 'center', marginBottom: 24, padding: 40 }}>
            {result.test_title && (
              <div style={{ fontSize: 14, fontWeight: 600, color: 'var(--text-muted)', marginBottom: 16 }}>
                {result.test_title}
              </div>
            )}

            <div style={{
              width: 130, height: 130, borderRadius: '50%', margin: '0 auto 20px',
              background: `conic-gradient(${color} ${pct * 3.6}deg, var(--bg-elevated) 0)`,
              display: 'flex', alignItems: 'center', justifyContent: 'center',
              boxShadow: `0 0 40px ${glow}`,
              position: 'relative',
            }}>
              <div style={{
                width: 98, height: 98, borderRadius: '50%',
                background: 'var(--bg-card)',
                display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center',
              }}>
                <div style={{ fontFamily: 'var(--font-display)', fontSize: '2rem', fontWeight: 800, color, lineHeight: 1 }}>
                  {pct}%
                </div>
              </div>
            </div>

            <div style={{ fontSize: 32, marginBottom: 8 }}>
              {passed ? '🎉' : '💪'}
            </div>
            <h2 style={{ marginBottom: 8, color }}>
              {passed ? 'Congratulations! You Passed!' : 'Keep Practicing — You Can Do It!'}
            </h2>
            <p style={{ fontSize: 15, marginBottom: 20, color: 'var(--text-secondary)' }}>
              You scored <strong style={{ color: 'var(--text-primary)' }}>{result.score}/{result.total_marks}</strong> marks.
              The pass mark requirement is <strong style={{ color: 'var(--text-primary)' }}>{result.pass_mark}/{result.total_marks}</strong>.
            </p>
            <span
              className={`badge ${passed ? 'badge-success' : 'badge-danger'}`}
              style={{ fontSize: 14, padding: '6px 20px', marginBottom: 28 }}
            >
              {passed ? '✓ PASSED' : '✕ DID NOT PASS'}
            </span>

            {/* Actions */}
            <div style={{ display: 'flex', gap: 12, justifyContent: 'center', flexWrap: 'wrap', marginTop: 12 }}>
              <button className="btn btn-secondary" onClick={loadReview} disabled={loadingReview}>
                <BookOpen size={15} />
                {loadingReview ? 'Loading Review…' : 'Review Questions & Explanations'}
              </button>
              <button className="btn btn-primary" onClick={() => navigate('/curriculum')}>
                <ArrowLeft size={15} /> Continue Curriculum
              </button>
              {passed && (
                <button
                  className="btn btn-secondary"
                  onClick={() => navigate('/certificate')}
                  style={{ color: 'var(--warning)', borderColor: 'rgba(245,158,11,0.3)' }}
                >
                  <Trophy size={15} /> Check Certificate
                </button>
              )}
              {!passed && (
                <button
                  className="btn btn-secondary"
                  onClick={() => navigate('/assessments')}
                >
                  <RotateCcw size={15} /> Try Another Attempt
                </button>
              )}
            </div>
          </div>
        </>
      )}

      {tab === 'review' && review && (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
          <div style={{
            display: 'flex', justifyContent: 'space-between', alignItems: 'center',
            padding: '12px 18px', background: 'var(--bg-elevated)', borderRadius: 'var(--radius-md)',
            border: '1px solid var(--border)', marginBottom: 8,
          }}>
            <div style={{ fontWeight: 600, color: 'var(--text-primary)', fontSize: 14 }}>
              {review.test_title}
            </div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
              <span style={{ fontWeight: 700, color }}>{Math.round(review.percentage)}%</span>
              <span className={`badge ${review.passed ? 'badge-success' : 'badge-danger'}`}>
                {review.passed ? 'Passed' : 'Failed'}
              </span>
            </div>
          </div>

          {review.questions.map((q, i) => (
            <div
              key={q.question_id}
              className="card"
              style={{
                borderColor: q.is_correct ? 'rgba(16,185,129,0.3)' : 'rgba(239,68,68,0.3)',
                background: q.is_correct ? 'rgba(16,185,129,0.02)' : 'rgba(239,68,68,0.02)',
              }}
            >
              <div style={{ display: 'flex', gap: 12, marginBottom: 14 }}>
                {q.is_correct
                  ? <CheckCircle2 size={20} style={{ color: 'var(--success)', flexShrink: 0, marginTop: 2 }} />
                  : <XCircle size={20} style={{ color: 'var(--danger)', flexShrink: 0, marginTop: 2 }} />
                }
                <div style={{ flex: 1 }}>
                  <div style={{ fontSize: 13, color: 'var(--text-muted)', marginBottom: 4 }}>
                    Question {i + 1} · {q.marks_awarded}/{q.marks} Mark{q.marks !== 1 ? 's' : ''}
                  </div>
                  <div style={{ fontWeight: 600, color: 'var(--text-primary)', fontSize: 15, lineHeight: 1.5 }}>
                    {q.question_text}
                  </div>
                </div>
              </div>

              {/* Options */}
              <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                {q.options.map(opt => {
                  const isSelected = opt.id === q.selected_option_id;
                  const isCorrectOpt = opt.is_correct;
                  const cls = isCorrectOpt ? 'correct' : isSelected && !isCorrectOpt ? 'incorrect' : '';

                  return (
                    <div
                      key={opt.id}
                      className={`quiz-option ${cls}`}
                      style={{
                        cursor: 'default',
                        pointerEvents: 'none',
                        background: isCorrectOpt
                          ? 'rgba(16,185,129,0.1)'
                          : isSelected && !isCorrectOpt
                          ? 'rgba(239,68,68,0.1)'
                          : 'var(--bg-elevated)',
                        borderColor: isCorrectOpt
                          ? 'rgba(16,185,129,0.4)'
                          : isSelected && !isCorrectOpt
                          ? 'rgba(239,68,68,0.4)'
                          : 'var(--border)',
                      }}
                    >
                      <div
                        className="quiz-option-indicator"
                        style={{
                          borderColor: isCorrectOpt ? 'var(--success)' : isSelected ? 'var(--danger)' : undefined,
                          background: isCorrectOpt ? 'var(--success)' : isSelected ? 'var(--danger)' : undefined,
                        }}
                      />
                      <span style={{ fontSize: 14, color: isCorrectOpt ? 'var(--success)' : isSelected ? 'var(--danger)' : 'var(--text-primary)' }}>
                        {opt.option_text}
                      </span>
                      {isCorrectOpt && (
                        <span style={{ marginLeft: 'auto', color: 'var(--success)', fontSize: 12, fontWeight: 700 }}>
                          ✓ Correct Answer
                        </span>
                      )}
                      {isSelected && !isCorrectOpt && (
                        <span style={{ marginLeft: 'auto', color: 'var(--danger)', fontSize: 12, fontWeight: 700 }}>
                          ✕ Your Answer
                        </span>
                      )}
                    </div>
                  );
                })}
              </div>

              {/* Diagnostic explanation */}
              {q.explanation && (
                <div style={{
                  marginTop: 14,
                  padding: '12px 16px',
                  background: 'rgba(99,102,241,0.08)',
                  border: '1px solid rgba(99,102,241,0.2)',
                  borderRadius: 'var(--radius-md)',
                  fontSize: 13,
                  color: 'var(--text-secondary)',
                  lineHeight: 1.6,
                }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: 6, color: 'var(--primary)', fontWeight: 700, marginBottom: 4 }}>
                    <Sparkles size={14} /> Diagnostic Explanation
                  </div>
                  {q.explanation}
                </div>
              )}
            </div>
          ))}

          <div style={{ textAlign: 'center', padding: '20px 0' }}>
            <button className="btn btn-primary" onClick={() => navigate('/curriculum')}>
              Back to Curriculum
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
