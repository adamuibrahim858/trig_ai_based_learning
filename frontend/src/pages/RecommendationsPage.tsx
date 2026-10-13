import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { recommendationService } from '../services/recommendationService';
import type { LearningRecommendation } from '../types/api';
import { useToast } from '../contexts/ToastContext';
import {
  Lightbulb, CheckCircle2, ArrowRight, Brain, BookOpen,
  X, RefreshCw, Sparkles,
} from 'lucide-react';

export default function RecommendationsPage() {
  const navigate = useNavigate();
  const toast = useToast();
  const [loading, setLoading] = useState(true);
  const [dismissingId, setDismissingId] = useState<number | null>(null);
  const [recommendations, setRecommendations] = useState<LearningRecommendation[]>([]);

  const fetchRecommendations = async () => {
    try {
      setLoading(true);
      const data = await recommendationService.getRecommendations();
      setRecommendations(data);
    } catch {
      toast.error('Failed to load personalized recommendations');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchRecommendations();
  }, []);

  const handleDismiss = async (id: number) => {
    try {
      setDismissingId(id);
      await recommendationService.dismissRecommendation(id);
      setRecommendations(prev => prev.filter(r => r.id !== id));
      toast.success('Recommendation dismissed');
    } catch {
      toast.error('Failed to dismiss recommendation');
    } finally {
      setDismissingId(null);
    }
  };

  const getTypeBadge = (type: string) => {
    switch (type) {
      case 'review_topic':
        return <span className="badge badge-warning">Review Needed</span>;
      case 'practice':
        return <span className="badge badge-primary">Practice Suggested</span>;
      case 'remediation':
        return <span className="badge badge-danger">Core Concept Remediation</span>;
      default:
        return <span className="badge badge-secondary">{type.replace('_', ' ')}</span>;
    }
  };

  return (
    <div className="page-container" id="recommendations-page">
      {/* Header */}
      <div className="page-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
        <div>
          <h1 className="page-title" id="recommendations-title" style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
            <Sparkles size={28} style={{ color: 'var(--primary)' }} />
            AI Learning Recommendations
          </h1>
          <p className="page-subtitle">
            Personalized guidance curated by the AI engine based on your test diagnostics and learning pace
          </p>
        </div>
        <button
          className="btn btn-secondary"
          onClick={fetchRecommendations}
          id="refresh-recommendations-btn"
          style={{ display: 'flex', alignItems: 'center', gap: 6 }}
        >
          <RefreshCw size={15} />
          Refresh
        </button>
      </div>

      {loading ? (
        <div className="loading-center">
          <div className="spinner" id="recommendations-spinner" />
        </div>
      ) : recommendations.length === 0 ? (
        <div className="card" style={{ textAlign: 'center', padding: '60px 24px', maxWidth: 640, margin: '40px auto' }} id="no-recommendations-card">
          <div
            style={{
              width: 64,
              height: 64,
              borderRadius: '50%',
              background: 'rgba(16,185,129,0.15)',
              color: 'var(--success)',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              margin: '0 auto 20px',
            }}
          >
            <CheckCircle2 size={32} />
          </div>
          <h2 style={{ fontSize: '1.4rem', fontWeight: 600, marginBottom: 8 }}>You're in great shape!</h2>
          <p style={{ color: 'var(--text-muted)', lineHeight: 1.6, marginBottom: 24 }}>
            No pending remediations or urgent review items right now. You are maintaining strong mastery across your curriculum modules.
          </p>
          <div style={{ display: 'flex', gap: 12, justifyContent: 'center' }}>
            <button
              className="btn btn-primary"
              onClick={() => navigate('/curriculum')}
              id="goto-curriculum-btn"
            >
              Continue Next Lesson
            </button>
            <button
              className="btn btn-secondary"
              onClick={() => navigate('/ai-tutor')}
              id="chat-tutor-btn"
            >
              Ask AI Tutor
            </button>
          </div>
        </div>
      ) : (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }} id="recommendations-list">
          {recommendations.map(item => (
            <div
              key={item.id}
              className="card"
              style={{
                padding: 24,
                borderLeft: '4px solid var(--primary)',
                position: 'relative',
              }}
              id={`recommendation-card-${item.id}`}
            >
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: 16 }}>
                <div style={{ display: 'flex', gap: 16, alignItems: 'flex-start', flex: 1 }}>
                  <div
                    style={{
                      width: 44,
                      height: 44,
                      borderRadius: 12,
                      background: 'rgba(99,102,241,0.15)',
                      color: 'var(--primary)',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      flexShrink: 0,
                    }}
                  >
                    <Lightbulb size={24} />
                  </div>

                  <div style={{ flex: 1 }}>
                    <div style={{ display: 'flex', gap: 10, alignItems: 'center', marginBottom: 6 }}>
                      {getTypeBadge(item.recommendation_type || 'review')}
                      {item.topic && (
                        <span style={{ fontSize: '0.85rem', color: 'var(--text-muted)' }}>
                          Week {item.topic.week_number} • {item.topic.title}
                        </span>
                      )}
                    </div>

                    <p style={{ fontSize: '1rem', color: 'var(--text)', marginBottom: 14, lineHeight: 1.5 }}>
                      {item.reason}
                    </p>

                    {/* Linked Action details */}
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 10, alignItems: 'center' }}>
                      {item.lesson && (
                        <button
                          className="btn btn-primary"
                          style={{ padding: '8px 14px', fontSize: '0.85rem' }}
                          onClick={() => navigate(`/lesson/${item.lesson!.id}`)}
                          id={`action-lesson-${item.id}-btn`}
                        >
                          <BookOpen size={15} />
                          Go to Lesson: {item.lesson.title}
                          <ArrowRight size={14} />
                        </button>
                      )}

                      <button
                        className="btn btn-secondary"
                        style={{ padding: '8px 14px', fontSize: '0.85rem' }}
                        onClick={() => navigate('/ai-tutor')}
                        id={`ask-tutor-${item.id}-btn`}
                      >
                        <Brain size={15} />
                        Discuss with AI Tutor
                      </button>
                    </div>
                  </div>
                </div>

                {/* Dismiss Button */}
                <button
                  className="btn btn-ghost"
                  style={{
                    padding: '6px 8px',
                    color: 'var(--text-muted)',
                    borderRadius: 'var(--radius-sm)',
                  }}
                  onClick={() => handleDismiss(item.id)}
                  disabled={dismissingId === item.id}
                  title="Dismiss Recommendation"
                  id={`dismiss-recommendation-${item.id}-btn`}
                >
                  <X size={18} />
                </button>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
