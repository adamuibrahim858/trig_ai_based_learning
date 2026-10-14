import { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { curriculumService } from '../services/curriculumService';
import { useToast } from '../contexts/ToastContext';
import { getErrorMessage } from '../services/api';
import type { Lesson, Topic } from '../types/api';
import {
  ArrowLeft, CheckCircle2, Clock, Brain, Loader2,
  BookOpen, ExternalLink, ChevronRight, ArrowRight,
} from 'lucide-react';
import { MarkdownContent } from '../components/MarkdownContent';
import AskAiModal from '../components/AskAiModal';

export default function LessonPage() {
  const { lessonId } = useParams<{ lessonId: string }>();
  const navigate = useNavigate();
  const toast = useToast();
  const [lesson, setLesson] = useState<Lesson | null>(null);
  const [loading, setLoading] = useState(true);
  const [completing, setCompleting] = useState(false);
  const [started, setStarted] = useState(false);
  const [askAiOpen, setAskAiOpen] = useState(false);

  // Topic data for "next lesson / next topic" navigation
  const [topicLessons, setTopicLessons] = useState<Lesson[]>([]);
  const [allTopics, setAllTopics] = useState<Topic[]>([]);

  const loadLesson = useCallback(async (id: number) => {
    try {
      setLoading(true);
      const l = await curriculumService.getLesson(id);
      setLesson(l);
      // Auto-start if available
      if (l.status === 'available') {
        await curriculumService.startLesson(id);
        setStarted(true);
      } else if (l.status === 'in_progress' || l.status === 'completed') {
        setStarted(true);
      }

      // Load topic lessons for "Next Lesson" navigation
      try {
        const topics = await curriculumService.getCourseTopics(1);
        setAllTopics(topics);
        const currentTopic = topics.find(t => t.id === l.topic_id);
        if (currentTopic?.lessons) {
          setTopicLessons(currentTopic.lessons.sort((a, b) => a.order - b.order));
        }
      } catch {
        // non-critical
      }
    } catch (err) {
      toast.error(getErrorMessage(err));
      navigate('/curriculum');
    } finally {
      setLoading(false);
    }
  }, [navigate, toast]);

  useEffect(() => {
    if (lessonId) {
      loadLesson(parseInt(lessonId));
    }
  }, [lessonId, loadLesson]);

  const handleComplete = async () => {
    if (!lesson) return;
    setCompleting(true);
    try {
      await curriculumService.completeLesson(lesson.id);
      toast.success('Lesson completed! Great work! 🎉');
      setLesson(prev => prev ? { ...prev, status: 'completed' } : prev);
    } catch (err) {
      toast.error(getErrorMessage(err));
    } finally {
      setCompleting(false);
    }
  };

  // Compute next lesson / next topic
  const getNextNavigation = () => {
    if (!lesson) return null;

    // Find the next lesson in the same topic
    const currentIndex = topicLessons.findIndex(l => l.id === lesson.id);
    if (currentIndex >= 0 && currentIndex < topicLessons.length - 1) {
      const nextLesson = topicLessons[currentIndex + 1];
      return {
        type: 'lesson' as const,
        label: `Next: ${nextLesson.title}`,
        action: () => navigate(`/lesson/${nextLesson.id}`),
        accessible: nextLesson.is_accessible,
      };
    }

    // Last lesson in topic — offer to go to the next topic
    if (allTopics.length > 0 && lesson.topic) {
      const currentTopicIdx = allTopics.findIndex(t => t.id === lesson.topic_id);
      if (currentTopicIdx >= 0 && currentTopicIdx < allTopics.length - 1) {
        const nextTopic = allTopics[currentTopicIdx + 1];
        // If the current topic has an assessment, prompt to take it first
        const currentTopic = allTopics[currentTopicIdx];
        if (currentTopic.assessment_id) {
          return {
            type: 'assessment' as const,
            label: `Take Week ${currentTopic.week_number} Assessment`,
            action: () => navigate('/assessments'),
            accessible: true,
          };
        }
        if (nextTopic.is_accessible && nextTopic.lessons && nextTopic.lessons.length > 0) {
          return {
            type: 'topic' as const,
            label: `Next Topic: Week ${nextTopic.week_number} — ${nextTopic.title}`,
            action: () => navigate(`/lesson/${nextTopic.lessons![0].id}`),
            accessible: true,
          };
        }
      }
    }

    return null;
  };

  if (loading) {
    return <div className="loading-center" style={{ height: '60vh' }}><div className="spinner" /></div>;
  }

  if (!lesson) return null;

  const nextNav = getNextNavigation();

  return (
    <div style={{ maxWidth: 800, margin: '0 auto' }}>
      {/* Back nav */}
      <button
        className="btn btn-ghost btn-sm"
        onClick={() => navigate('/curriculum')}
        style={{ marginBottom: 20 }}
      >
        <ArrowLeft size={15} /> Back to Curriculum
      </button>

      {/* Topic breadcrumb */}
      {lesson.topic && (
        <div style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 13, color: 'var(--text-muted)', marginBottom: 12 }}>
          <BookOpen size={13} />
          Week {lesson.topic.week_number} · {lesson.topic.title}
          <ChevronRight size={12} />
          <span style={{ color: 'var(--text-secondary)' }}>Lesson {lesson.order}</span>
        </div>
      )}

      {/* Header */}
      <div className="card" style={{ marginBottom: 20 }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: 16, flexWrap: 'wrap' }}>
          <div>
            <h1 style={{ marginBottom: 8 }}>{lesson.title}</h1>
            <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap' }}>
              {lesson.estimated_minutes && (
                <span style={{ display: 'flex', alignItems: 'center', gap: 5, fontSize: 13, color: 'var(--text-muted)' }}>
                  <Clock size={13} /> {lesson.estimated_minutes} min
                </span>
              )}
              <span className={`badge ${
                lesson.status === 'completed' ? 'badge-success' :
                lesson.status === 'in_progress' ? 'badge-primary' :
                'badge-muted'
              }`}>
                {lesson.status === 'completed' ? '✓ Completed' :
                 lesson.status === 'in_progress' ? '▶ In Progress' :
                 'Available'}
              </span>
            </div>
          </div>
          <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
            <button
              className="btn btn-secondary btn-sm"
              onClick={() => setAskAiOpen(true)}
              style={{
                background: 'linear-gradient(135deg, rgba(79,70,229,0.1), rgba(124,58,237,0.08))',
                border: '1px solid rgba(79,70,229,0.2)',
                color: 'var(--primary)',
                fontWeight: 600,
              }}
            >
              <Brain size={14} />
              Ask AI
            </button>
            {lesson.status !== 'completed' && started && (
              <button
                id="complete-lesson-btn"
                className="btn btn-primary btn-sm"
                onClick={handleComplete}
                disabled={completing}
              >
                {completing ? <Loader2 size={14} className="spin-icon" /> : <CheckCircle2 size={14} />}
                Mark Complete
              </button>
            )}
          </div>
        </div>
      </div>

      {/* Lesson Body */}
      <div className="card" style={{ marginBottom: 20 }}>
        {lesson.summary && (
          <div style={{
            padding: 16,
            background: 'rgba(34,211,238,0.06)',
            border: '1px solid rgba(34,211,238,0.2)',
            borderRadius: 'var(--radius-md)',
            marginBottom: 20,
            fontSize: 14,
            color: 'var(--text-secondary)',
            lineHeight: 1.7,
          }}>
            {lesson.summary}
          </div>
        )}

        {lesson.learning_objectives && lesson.learning_objectives.length > 0 && (
          <div style={{ marginBottom: 24 }}>
            <h3 style={{ marginBottom: 12, fontSize: 15, color: 'var(--text-primary)' }}>Learning Objectives</h3>
            <ul style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
              {lesson.learning_objectives.map((obj, i) => (
                <li key={i} style={{ display: 'flex', gap: 10, fontSize: 14, color: 'var(--text-secondary)' }}>
                  <CheckCircle2 size={15} style={{ color: 'var(--success)', flexShrink: 0, marginTop: 2 }} />
                  {obj}
                </li>
              ))}
            </ul>
          </div>
        )}

        {lesson.content ? (
          <div className="lesson-content">
            <MarkdownContent content={lesson.content} />
          </div>
        ) : (
          <div className="empty-state" style={{ padding: 40 }}>
            <div className="empty-state-icon">📝</div>
            <h3>Content Coming Soon</h3>
            <p>The lesson content will be available shortly.</p>
          </div>
        )}
      </div>

      {/* Resources */}
      {lesson.resources && lesson.resources.length > 0 && (
        <div className="card" style={{ marginBottom: 20 }}>
          <h3 style={{ marginBottom: 16, color: 'var(--text-primary)' }}>Additional Resources</h3>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
            {lesson.resources.map(r => (
              <a
                key={r.id}
                href={r.content_url}
                target="_blank"
                rel="noopener noreferrer"
                style={{
                  display: 'flex', alignItems: 'center', gap: 10,
                  padding: '10px 14px',
                  background: 'var(--bg-elevated)',
                  border: '1px solid var(--border)',
                  borderRadius: 'var(--radius-md)',
                  color: 'var(--text-primary)',
                  fontSize: 14,
                  transition: 'all 0.15s',
                }}
              >
                <span style={{ fontSize: 18 }}>
                  {r.type === 'video' ? '🎥' : r.type === 'pdf' ? '📄' : '🔗'}
                </span>
                <span style={{ flex: 1 }}>{r.title}</span>
                <ExternalLink size={13} style={{ color: 'var(--text-muted)' }} />
              </a>
            ))}
          </div>
        </div>
      )}

      {/* Complete CTA */}
      {lesson.status !== 'completed' && started && (
        <div style={{ textAlign: 'center', padding: '24px 0' }}>
          <button
            className="btn btn-primary btn-lg"
            onClick={handleComplete}
            disabled={completing}
          >
            {completing ? <Loader2 size={16} className="spin-icon" /> : <CheckCircle2 size={16} />}
            Mark Lesson as Complete
          </button>
        </div>
      )}

      {/* Completed — show next navigation */}
      {lesson.status === 'completed' && (
        <div style={{
          textAlign: 'center',
          padding: 24,
          background: 'rgba(16,185,129,0.06)',
          border: '1px solid rgba(16,185,129,0.2)',
          borderRadius: 'var(--radius-lg)',
        }}>
          <CheckCircle2 size={32} style={{ color: 'var(--success)', margin: '0 auto 8px' }} />
          <div style={{ fontWeight: 700, color: 'var(--success)', marginBottom: 16 }}>
            Lesson Completed!
          </div>
          <div style={{ display: 'flex', gap: 10, justifyContent: 'center', flexWrap: 'wrap' }}>
            <button className="btn btn-secondary" onClick={() => navigate('/curriculum')}>
              Back to Curriculum
            </button>
            {nextNav && nextNav.accessible && (
              <button
                className="btn btn-primary"
                onClick={nextNav.action}
                style={{ display: 'flex', alignItems: 'center', gap: 6 }}
              >
                {nextNav.type === 'assessment' ? '📝' : <ArrowRight size={15} />}
                {nextNav.label}
              </button>
            )}
          </div>
        </div>
      )}

      {/* Ask AI Modal */}
      <AskAiModal
        isOpen={askAiOpen}
        onClose={() => setAskAiOpen(false)}
        lessonId={lesson.id}
        lessonTitle={lesson.title}
        topicTitle={lesson.topic?.title}
      />
    </div>
  );
}
