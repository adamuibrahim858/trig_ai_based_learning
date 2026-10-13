import { useState, useEffect, useCallback, useRef } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { assessmentService } from '../services/assessmentService';
import { useToast } from '../contexts/ToastContext';
import { getErrorMessage } from '../services/api';
import type { Question, SubmitAnswerPayload } from '../types/api';
import { Clock, ChevronLeft, ChevronRight, Send, Loader2 } from 'lucide-react';

export default function AttemptPage() {
  const { attemptId } = useParams<{ attemptId: string }>();
  const navigate = useNavigate();
  const toast = useToast();
  const [questions, setQuestions] = useState<Question[]>([]);
  const [currentIdx, setCurrentIdx] = useState(0);
  const [answers, setAnswers] = useState<Record<number, SubmitAnswerPayload>>({});
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [timeLeft, setTimeLeft] = useState<number | null>(null);
  const [testTitle, setTestTitle] = useState('');

  // Keep latest answers and questions in ref for timer auto-submit
  const answersRef = useRef(answers);
  const questionsRef = useRef(questions);
  const submittingRef = useRef(submitting);

  useEffect(() => {
    answersRef.current = answers;
  }, [answers]);

  useEffect(() => {
    questionsRef.current = questions;
  }, [questions]);

  useEffect(() => {
    submittingRef.current = submitting;
  }, [submitting]);

  const handleSubmit = useCallback(async () => {
    if (!attemptId || submittingRef.current) return;
    setSubmitting(true);

    const currentQuestions = questionsRef.current;
    const currentAnswers = answersRef.current;

    const finalAnswers: SubmitAnswerPayload[] = currentQuestions.map(q => ({
      question_id: q.id,
      selected_option_id: currentAnswers[q.id]?.selected_option_id ?? null,
      answer_text: currentAnswers[q.id]?.answer_text ?? null,
    }));

    try {
      const result = await assessmentService.submitAttempt(parseInt(attemptId), finalAnswers);
      navigate(`/result/${attemptId}`, { state: result });
    } catch (err) {
      toast.error(getErrorMessage(err));
      setSubmitting(false);
    }
  }, [attemptId, navigate, toast]);

  const loadAttempt = useCallback(async (id: number) => {
    try {
      setLoading(true);
      const data = await assessmentService.getAttempt(id);
      setQuestions(data.questions);
      setTestTitle(data.attempt.test?.title ?? 'Assessment');
      if (data.attempt.test?.duration_minutes) {
        setTimeLeft(data.attempt.test.duration_minutes * 60);
      }
    } catch (err) {
      toast.error(getErrorMessage(err));
      navigate('/assessments');
    } finally {
      setLoading(false);
    }
  }, [navigate, toast]);

  useEffect(() => {
    if (attemptId) {
      loadAttempt(parseInt(attemptId));
    }
  }, [attemptId, loadAttempt]);

  // Countdown timer
  useEffect(() => {
    if (timeLeft === null || timeLeft <= 0) return;
    const id = setInterval(() => {
      setTimeLeft(t => {
        if (t !== null && t <= 1) {
          clearInterval(id);
          handleSubmit();
          return 0;
        }
        return (t ?? 0) - 1;
      });
    }, 1000);
    return () => clearInterval(id);
  }, [timeLeft, handleSubmit]);

  const selectAnswer = (q: Question, optionId: number) => {
    setAnswers(prev => ({
      ...prev,
      [q.id]: { question_id: q.id, selected_option_id: optionId },
    }));
  };

  if (loading) {
    return <div className="loading-center" style={{ height: '60vh' }}><div className="spinner" /></div>;
  }

  const q = questions[currentIdx];
  const answeredCount = Object.keys(answers).length;

  return (
    <div style={{ maxWidth: 720, margin: '0 auto' }}>
      {/* Header */}
      <div className="card" style={{ marginBottom: 20, padding: '16px 22px' }}>
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: 12 }}>
          <div>
            <div style={{ fontWeight: 700, color: 'var(--text-primary)', fontSize: 15 }}>{testTitle}</div>
            <div style={{ fontSize: 13, color: 'var(--text-muted)' }}>
              Question {currentIdx + 1} of {questions.length} · {answeredCount} answered
            </div>
          </div>
          {timeLeft !== null && (
            <div style={{ display: 'flex', alignItems: 'center', gap: 6, color: timeLeft < 120 ? 'var(--danger)' : 'var(--text-secondary)', fontWeight: 700 }}>
              <Clock size={15} />
              {Math.floor(timeLeft / 60)}:{String(timeLeft % 60).padStart(2, '0')}
            </div>
          )}
        </div>

        {/* Progress dots */}
        <div style={{ display: 'flex', gap: 5, marginTop: 14, flexWrap: 'wrap' }}>
          {questions.map((qq, i) => (
            <button
              key={qq.id}
              onClick={() => setCurrentIdx(i)}
              style={{
                width: 28, height: 28, borderRadius: 6,
                border: `2px solid ${i === currentIdx ? 'var(--primary)' : 'var(--border)'}`,
                background: answers[qq.id] ? 'rgba(99,102,241,0.2)' : 'var(--bg-elevated)',
                color: i === currentIdx ? 'var(--primary)' : answers[qq.id] ? 'var(--text-primary)' : 'var(--text-muted)',
                fontSize: 11, fontWeight: 700,
                cursor: 'pointer',
              }}
            >
              {i + 1}
            </button>
          ))}
        </div>
      </div>

      {/* Question Card */}
      {q && (
        <div className="card" style={{ marginBottom: 20 }}>
          <div style={{ marginBottom: 8 }}>
            <span className="badge badge-primary" style={{ marginBottom: 12 }}>
              {q.question_type === 'multiple_choice' ? 'Multiple Choice'
               : q.question_type === 'true_false' ? 'True / False'
               : 'Numerical'} · {q.marks} mark{q.marks !== 1 ? 's' : ''}
            </span>
          </div>
          <h3 style={{ marginBottom: 24, color: 'var(--text-primary)', lineHeight: 1.5, fontWeight: 600, fontSize: 16 }}>
            {q.question_text}
          </h3>

          <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
            {q.options.map(opt => {
              const selected = answers[q.id]?.selected_option_id === opt.id;
              return (
                <button
                  key={opt.id}
                  id={`option-${opt.id}`}
                  className={`quiz-option ${selected ? 'selected' : ''}`}
                  onClick={() => selectAnswer(q, opt.id)}
                >
                  <div className="quiz-option-indicator" />
                  <span style={{ fontSize: 14, color: 'var(--text-primary)' }}>{opt.option_text}</span>
                </button>
              );
            })}
          </div>
        </div>
      )}

      {/* Navigation */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 12 }}>
        <button
          className="btn btn-secondary"
          onClick={() => setCurrentIdx(i => Math.max(0, i - 1))}
          disabled={currentIdx === 0}
        >
          <ChevronLeft size={15} /> Previous
        </button>

        <div style={{ textAlign: 'center' }}>
          <div style={{ fontSize: 12, color: 'var(--text-muted)' }}>
            {answeredCount}/{questions.length} answered
          </div>
        </div>

        {currentIdx < questions.length - 1 ? (
          <button
            className="btn btn-primary"
            onClick={() => setCurrentIdx(i => Math.min(questions.length - 1, i + 1))}
          >
            Next <ChevronRight size={15} />
          </button>
        ) : (
          <button
            id="submit-attempt-btn"
            className="btn btn-primary"
            onClick={handleSubmit}
            disabled={submitting}
            style={{ background: 'var(--success)', boxShadow: '0 4px 16px var(--success-glow)' }}
          >
            {submitting ? <Loader2 size={15} className="spin-icon" /> : <Send size={15} />}
            Submit Test
          </button>
        )}
      </div>
    </div>
  );
}
