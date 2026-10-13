import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { finalExamService, type FinalExamEligibility, type FinalExamData } from '../services/finalExamService';
import { getErrorMessage } from '../services/api';
import type { Question, QuestionOption, TestAttempt } from '../types/api';
import { useToast } from '../contexts/ToastContext';
import {
  GraduationCap, Award, CheckCircle, ArrowRight,
  HelpCircle, ShieldAlert, ChevronLeft, ChevronRight, Send
} from 'lucide-react';

interface ActiveAttempt extends Partial<TestAttempt> {
  id: number;
  test_id: number;
  questions?: Question[];
}

export default function FinalExamPage() {
  const navigate = useNavigate();
  const toast = useToast();

  const [loading, setLoading] = useState(true);
  const [eligibility, setEligibility] = useState<FinalExamEligibility | null>(null);
  const [examData, setExamData] = useState<FinalExamData | null>(null);

  // Active taking state
  const [activeAttempt, setActiveAttempt] = useState<ActiveAttempt | null>(null);
  const [selectedAnswers, setSelectedAnswers] = useState<Record<number, number>>({});
  const [currentIndex, setCurrentIndex] = useState(0);
  const [submitting, setSubmitting] = useState(false);
  const [startingAttempt, setStartingAttempt] = useState(false);

  const fetchExamStatus = async () => {
    try {
      setLoading(true);
      const [eligRes, examRes] = await Promise.allSettled([
        finalExamService.getEligibility(),
        finalExamService.getFinalExam(),
      ]);

      if (eligRes.status === 'fulfilled') {
        setEligibility(eligRes.value);
      }

      if (examRes.status === 'fulfilled') {
        setExamData(examRes.value);
      }
    } catch {
      toast.error('Could not verify final exam status');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchExamStatus();
  }, []);

  const handleStartExam = async () => {
    try {
      setStartingAttempt(true);
      const attempt = await finalExamService.startAttempt();
      const active: ActiveAttempt = { ...attempt };

      // If attempt questions are loaded on the test
      if (examData?.test.questions && examData.test.questions.length > 0) {
        active.questions = examData.test.questions;
      }
      setActiveAttempt(active);
      setCurrentIndex(0);
      setSelectedAnswers({});
      toast.info('Final Examination started. Good luck!');
    } catch (err: unknown) {
      toast.error(getErrorMessage(err));
    } finally {
      setStartingAttempt(false);
    }
  };

  const handleSelectOption = (questionId: number, optionId: number) => {
    setSelectedAnswers(prev => ({
      ...prev,
      [questionId]: optionId,
    }));
  };

  const handleSubmitExam = async () => {
    if (!activeAttempt) return;

    const questions = activeAttempt.questions || examData?.test.questions || [];
    const answeredCount = Object.keys(selectedAnswers).length;

    if (answeredCount < questions.length) {
      const confirmIncomplete = window.confirm(
        `You have answered ${answeredCount} of ${questions.length} questions. Are you sure you want to submit?`
      );
      if (!confirmIncomplete) return;
    }

    try {
      setSubmitting(true);
      const answersPayload = Object.entries(selectedAnswers).map(([qId, optId]) => ({
        question_id: Number(qId),
        selected_option_id: optId,
      }));

      const res = await finalExamService.submitAttempt(activeAttempt.id, answersPayload);

      toast.success('Final Examination submitted successfully!');
      navigate(`/result/${res.attempt.id || activeAttempt.id}`);
    } catch (err: unknown) {
      toast.error(getErrorMessage(err));
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) {
    return (
      <div className="page-container">
        <div className="loading-center">
          <div className="spinner" id="exam-loading-spinner" />
        </div>
      </div>
    );
  }

  // ACTIVE EXAM TAKING VIEW
  const questions = activeAttempt?.questions || examData?.test.questions || [];
  if (activeAttempt && questions.length > 0) {
    const currentQuestion = questions[currentIndex];
    const isLast = currentIndex === questions.length - 1;

    return (
      <div className="page-container" id="active-exam-container" style={{ maxWidth: 880, margin: '0 auto' }}>
        {/* Top Sticky Test Bar */}
        <div
          className="card"
          style={{
            padding: '16px 24px',
            marginBottom: 24,
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'center',
          }}
        >
          <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
            <span className="badge badge-warning" style={{ fontWeight: 700 }}>FINAL EXAM</span>
            <span style={{ fontWeight: 600 }}>Question {currentIndex + 1} of {questions.length}</span>
          </div>

          <div style={{ display: 'flex', alignItems: 'center', gap: 14 }}>
            <span style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>
              Answered: {Object.keys(selectedAnswers).length}/{questions.length}
            </span>
            <button
              className="btn btn-primary"
              onClick={handleSubmitExam}
              disabled={submitting}
              id="submit-exam-btn"
              style={{ display: 'flex', alignItems: 'center', gap: 6 }}
            >
              <Send size={15} />
              {submitting ? 'Submitting...' : 'Finish & Submit'}
            </button>
          </div>
        </div>

        {/* Question dots indicator */}
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, marginBottom: 24 }} id="question-navigator">
          {questions.map((q, idx) => {
            const hasAns = selectedAnswers[q.id] !== undefined;
            const isCurr = idx === currentIndex;
            return (
              <button
                key={q.id}
                onClick={() => setCurrentIndex(idx)}
                style={{
                  width: 34,
                  height: 34,
                  borderRadius: 8,
                  border: isCurr ? '2px solid var(--primary)' : '1px solid var(--border)',
                  background: isCurr ? 'var(--primary)' : hasAns ? 'rgba(16,185,129,0.2)' : 'var(--card-bg)',
                  color: isCurr ? '#fff' : hasAns ? 'var(--success)' : 'var(--text-muted)',
                  cursor: 'pointer',
                  fontWeight: 600,
                  fontSize: '0.85rem',
                }}
                id={`dot-nav-${idx + 1}`}
              >
                {idx + 1}
              </button>
            );
          })}
        </div>

        {/* Current Question Card */}
        <div className="card" style={{ padding: 32, marginBottom: 24 }} id="current-question-card">
          <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 16 }}>
            <span className="badge badge-secondary">Question {currentIndex + 1}</span>
            <span style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>
              Weight: {currentQuestion.marks ?? 1} Point(s)
            </span>
          </div>

          <p style={{ fontSize: '1.15rem', fontWeight: 500, lineHeight: 1.6, marginBottom: 28 }}>
            {currentQuestion.question_text}
          </p>

          {/* Options */}
          <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
            {currentQuestion.options?.map((opt: QuestionOption) => {
              const selected = selectedAnswers[currentQuestion.id] === opt.id;
              return (
                <div
                  key={opt.id}
                  onClick={() => handleSelectOption(currentQuestion.id, opt.id)}
                  style={{
                    padding: '16px 20px',
                    borderRadius: 'var(--radius-md)',
                    border: selected ? '2px solid var(--primary)' : '1px solid var(--border)',
                    background: selected ? 'rgba(99,102,241,0.12)' : 'var(--card-hover)',
                    cursor: 'pointer',
                    display: 'flex',
                    alignItems: 'center',
                    gap: 14,
                    transition: 'all 0.2s',
                  }}
                  id={`option-${opt.id}`}
                >
                  <div
                    style={{
                      width: 20,
                      height: 20,
                      borderRadius: '50%',
                      border: selected ? '6px solid var(--primary)' : '2px solid var(--border)',
                      background: selected ? '#fff' : 'transparent',
                      flexShrink: 0,
                    }}
                  />
                  <span style={{ fontSize: '1rem', color: selected ? 'var(--text)' : 'var(--text-muted)' }}>
                    {opt.option_text}
                  </span>
                </div>
              );
            })}
          </div>
        </div>

        {/* Previous / Next Controls */}
        <div style={{ display: 'flex', justifyContent: 'space-between' }}>
          <button
            className="btn btn-secondary"
            disabled={currentIndex === 0}
            onClick={() => setCurrentIndex(prev => prev - 1)}
            id="prev-question-btn"
          >
            <ChevronLeft size={16} /> Previous
          </button>

          {isLast ? (
            <button
              className="btn btn-primary"
              onClick={handleSubmitExam}
              disabled={submitting}
              id="final-submit-btn"
            >
              {submitting ? 'Submitting Exam...' : 'Submit Final Exam'}
            </button>
          ) : (
            <button
              className="btn btn-secondary"
              onClick={() => setCurrentIndex(prev => prev + 1)}
              id="next-question-btn"
            >
              Next <ChevronRight size={16} />
            </button>
          )}
        </div>
      </div>
    );
  }

  // OVERVIEW & ELIGIBILITY LANDING VIEW
  const isEligible = eligibility?.eligible ?? false;
  const topicsCompleted = eligibility?.topics_completed ?? 0;
  const totalTopics = eligibility?.total_topics ?? 8;
  const bestScore = examData?.best_score;

  return (
    <div className="page-container" id="final-exam-page">
      {/* Header */}
      <div className="page-header">
        <h1 className="page-title" id="exam-page-title" style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
          <GraduationCap size={30} style={{ color: 'var(--primary)' }} />
          Trigonometry Final Comprehensive Exam
        </h1>
        <p className="page-subtitle">
          The capstone assessment testing mastery across all 8 modules of the curriculum
        </p>
      </div>

      <div className="grid-2" style={{ alignItems: 'start', gap: 24 }}>
        {/* Left: Requirements & Status */}
        <div className="card" style={{ padding: 28 }} id="eligibility-card">
          <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 18 }}>
            <div
              style={{
                width: 44,
                height: 44,
                borderRadius: 12,
                background: isEligible ? 'rgba(16,185,129,0.15)' : 'rgba(239,68,68,0.15)',
                color: isEligible ? 'var(--success)' : 'var(--danger)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
              }}
            >
              {isEligible ? <CheckCircle size={24} /> : <ShieldAlert size={24} />}
            </div>
            <div>
              <h3 style={{ fontSize: '1.2rem', fontWeight: 600 }}>
                {isEligible ? 'Prerequisites Satisfied' : 'Prerequisites Incomplete'}
              </h3>
              <p style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>
                {isEligible
                  ? 'You have completed all curriculum topics and are qualified to take the exam.'
                  : (eligibility?.reason || 'You must complete all 8 weeks before unlocking the exam.')}
              </p>
            </div>
          </div>

          <div style={{ marginBottom: 20 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.85rem', marginBottom: 6 }}>
              <span>Curriculum Completion</span>
              <span style={{ fontWeight: 600 }}>{topicsCompleted} / {totalTopics} Topics</span>
            </div>
            <div className="progress-bar" style={{ height: 8 }}>
              <div
                className="progress-fill"
                style={{ width: `${Math.min(100, (topicsCompleted / totalTopics) * 100)}%` }}
              />
            </div>
          </div>

          {!isEligible && (
            <button
              className="btn btn-primary"
              style={{ width: '100%', display: 'flex', justifyContent: 'center', alignItems: 'center', gap: 8 }}
              onClick={() => navigate('/curriculum')}
              id="continue-curriculum-btn"
            >
              Continue Curriculum <ArrowRight size={16} />
            </button>
          )}

          {isEligible && (
            <button
              className="btn btn-primary"
              style={{ width: '100%', padding: '14px', fontSize: '1.05rem', fontWeight: 600 }}
              onClick={handleStartExam}
              disabled={startingAttempt}
              id="start-final-exam-btn"
            >
              {startingAttempt ? 'Launching Exam...' : bestScore ? 'Retake Final Exam' : 'Begin Final Examination'}
            </button>
          )}
        </div>

        {/* Right: Exam Guidelines & Best Score */}
        <div style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
          {bestScore && (
            <div
              className="card"
              style={{
                padding: 24,
                border: bestScore.passed ? '1px solid rgba(16,185,129,0.4)' : '1px solid rgba(239,68,68,0.4)',
                background: bestScore.passed ? 'rgba(16,185,129,0.05)' : 'rgba(239,68,68,0.05)',
              }}
              id="best-score-card"
            >
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                <span style={{ fontWeight: 600, fontSize: '1rem' }}>Best Exam Result</span>
                <span className={`badge ${bestScore.passed ? 'badge-success' : 'badge-danger'}`}>
                  {bestScore.passed ? 'Passing Score' : 'Did Not Pass'}
                </span>
              </div>
              <div style={{ display: 'flex', alignItems: 'baseline', gap: 8 }}>
                <span style={{ fontSize: '2.5rem', fontWeight: 800, color: bestScore.passed ? 'var(--success)' : 'var(--danger)' }}>
                  {bestScore.percentage}%
                </span>
                <span style={{ color: 'var(--text-muted)', fontSize: '0.9rem' }}>
                  {bestScore.submitted_at ? `Recorded on ${new Date(bestScore.submitted_at).toLocaleDateString()}` : ''}
                </span>
              </div>
              {bestScore.passed && (
                <div style={{ marginTop: 14 }}>
                  <button
                    className="btn btn-secondary"
                    onClick={() => navigate('/certificate')}
                    id="claim-certificate-shortcut-btn"
                  >
                    <Award size={16} /> View Certificate
                  </button>
                </div>
              )}
            </div>
          )}

          <div className="card" style={{ padding: 24 }} id="exam-rules-card">
            <h4 style={{ fontWeight: 600, marginBottom: 14, display: 'flex', alignItems: 'center', gap: 8 }}>
              <HelpCircle size={18} style={{ color: 'var(--primary)' }} />
              Examination Rules & Structure
            </h4>
            <ul style={{ listStyle: 'none', padding: 0, margin: 0, display: 'flex', flexDirection: 'column', gap: 10, fontSize: '0.9rem', color: 'var(--text-muted)' }}>
              <li style={{ display: 'flex', gap: 8 }}>
                <CheckCircle size={16} style={{ color: 'var(--primary)', flexShrink: 0, marginTop: 2 }} />
                <span><strong>Coverage:</strong> Tests core competencies from Weeks 1 through 8.</span>
              </li>
              <li style={{ display: 'flex', gap: 8 }}>
                <CheckCircle size={16} style={{ color: 'var(--primary)', flexShrink: 0, marginTop: 2 }} />
                <span><strong>Pass Threshold:</strong> 70% or higher is required to unlock your certificate.</span>
              </li>
              <li style={{ display: 'flex', gap: 8 }}>
                <CheckCircle size={16} style={{ color: 'var(--primary)', flexShrink: 0, marginTop: 2 }} />
                <span><strong>Retakes:</strong> Multiple attempts are allowed; your highest score will be recorded.</span>
              </li>
              <li style={{ display: 'flex', gap: 8 }}>
                <CheckCircle size={16} style={{ color: 'var(--primary)', flexShrink: 0, marginTop: 2 }} />
                <span><strong>Feedback:</strong> Detailed step-by-step diagnostic feedback is provided upon submission.</span>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  );
}
