import { useState, useEffect, useRef, useCallback } from 'react';
import { aiTutorService } from '../services/aiTutorService';
import { curriculumService } from '../services/curriculumService';
import { useToast } from '../contexts/ToastContext';
import { getErrorMessage } from '../services/api';
import type { AiConversation, AiMessage, Topic } from '../types/api';
import { MarkdownContent } from '../components/MarkdownContent';
import {
  Plus, Trash2, Send, Loader2, MessageCircle,
  Sparkles, HelpCircle, X,
} from 'lucide-react';

export default function AITutorPage() {
  const toast = useToast();
  const [conversations, setConversations] = useState<AiConversation[]>([]);
  const [activeConv, setActiveConv] = useState<AiConversation | null>(null);
  const [messages, setMessages] = useState<AiMessage[]>([]);
  const [input, setInput] = useState('');
  const [loading, setLoading] = useState(true);
  const [sending, setSending] = useState(false);
  const [creating, setCreating] = useState(false);
  const messagesEndRef = useRef<HTMLDivElement>(null);

  // Practice Generator Modal
  const [showPracticeModal, setShowPracticeModal] = useState(false);
  const [topics, setTopics] = useState<Topic[]>([]);
  const [selectedTopicId, setSelectedTopicId] = useState<number>(1);
  const [difficulty, setDifficulty] = useState<'easy' | 'medium' | 'hard'>('medium');
  const [generatingPractice, setGeneratingPractice] = useState(false);

  const loadConversations = useCallback(async () => {
    try {
      setLoading(true);
      const list = await aiTutorService.getConversations();
      setConversations(list);
    } catch (err) {
      toast.error(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    loadConversations();
  }, [loadConversations]);

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  const openConversation = async (conv: AiConversation) => {
    try {
      const full = await aiTutorService.getConversation(conv.id);
      setActiveConv(full);
      setMessages(full.messages ?? []);
    } catch (err) {
      toast.error(getErrorMessage(err));
    }
  };

  const createConversation = async (initialTitle?: string): Promise<AiConversation | null> => {
    setCreating(true);
    try {
      const conv = await aiTutorService.createConversation({ title: initialTitle || 'New Conversation' });
      setConversations(prev => [conv, ...prev]);
      setActiveConv(conv);
      setMessages([]);
      return conv;
    } catch (err) {
      toast.error(getErrorMessage(err));
      return null;
    } finally {
      setCreating(false);
    }
  };

  const deleteConversation = async (id: number, e: React.MouseEvent) => {
    e.stopPropagation();
    try {
      await aiTutorService.deleteConversation(id);
      setConversations(prev => prev.filter(c => c.id !== id));
      if (activeConv?.id === id) {
        setActiveConv(null);
        setMessages([]);
      }
      toast.success('Conversation deleted');
    } catch (err) {
      toast.error(getErrorMessage(err));
    }
  };

  const sendMessage = async (overrideText?: string, convId?: number) => {
    const textToSend = overrideText !== undefined ? overrideText : input;
    const targetConvId = convId || activeConv?.id;

    if (!textToSend.trim() || !targetConvId || sending) return;

    const userMsg = textToSend.trim();
    if (overrideText === undefined) setInput('');
    setSending(true);

    // Optimistic update
    const tempUser: AiMessage = { id: Date.now(), role: 'user', message: userMsg };
    setMessages(prev => [...prev, tempUser]);

    try {
      const { user_message, assistant_message } = await aiTutorService.sendMessage(targetConvId, userMsg);
      setMessages(prev => [
        ...prev.filter(m => m.id !== tempUser.id),
        user_message,
        assistant_message,
      ]);
    } catch (err) {
      setMessages(prev => prev.filter(m => m.id !== tempUser.id));
      toast.error(getErrorMessage(err));
      if (overrideText === undefined) setInput(userMsg);
    } finally {
      setSending(false);
    }
  };

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  };

  const handleOpenPracticeModal = async () => {
    setShowPracticeModal(true);
    if (topics.length === 0) {
      try {
        const t = await curriculumService.getCourseTopics(1);
        setTopics(t);
        if (t.length > 0) setSelectedTopicId(t[0].id);
      } catch {
        // Fallback dummy topics
      }
    }
  };

  const handleGeneratePractice = async () => {
    setGeneratingPractice(true);
    try {
      const practice = await aiTutorService.generatePracticeQuestion(selectedTopicId, difficulty);
      setShowPracticeModal(false);

      // Create or use current conversation
      let targetConv = activeConv;
      if (!targetConv) {
        targetConv = await createConversation(`Practice: ${practice.topic_title}`);
      }

      if (targetConv) {
        // Send a message asking tutor to review and guide this problem
        const practicePrompt = `Generate a practice session for me based on this question:\n\n${practice.content}\n\nPlease guide me step-by-step to solve it, giving me hints instead of directly giving away the final answer!`;
        await sendMessage(practicePrompt, targetConv.id);
      }
    } catch (err) {
      toast.error(getErrorMessage(err));
    } finally {
      setGeneratingPractice(false);
    }
  };

  const suggestions = [
    'Explain the unit circle and angle coordinates.',
    'What is sin(30°) and how is it derived?',
    'How do I remember the SOHCAHTOA rule?',
    'Can you explain the Pythagorean trigonometry identities?',
  ];

  return (
    <div>
      <div className="page-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: 12 }}>
        <div>
          <h1 className="page-title">AI Tutor</h1>
          <p className="page-subtitle">Your personal trigonometry mentor powered by Google Gemini AI</p>
        </div>
        <div style={{ display: 'flex', gap: 10 }}>
          <button
            className="btn btn-secondary"
            onClick={handleOpenPracticeModal}
            id="practice-question-btn"
          >
            <HelpCircle size={15} /> Practice Problem
          </button>
          <button
            id="new-conversation-btn"
            className="btn btn-primary"
            onClick={() => createConversation()}
            disabled={creating}
          >
            {creating ? <Loader2 size={15} className="spin-icon" /> : <Plus size={15} />}
            New Chat
          </button>
        </div>
      </div>

      <div style={{ display: 'flex', gap: 20, height: 'calc(100vh - 210px)', minHeight: 520 }}>
        {/* Conversation List Sidebar */}
        <div style={{
          width: 260, flexShrink: 0,
          background: 'var(--bg-card)',
          border: '1px solid var(--border)',
          borderRadius: 'var(--radius-lg)',
          display: 'flex', flexDirection: 'column',
          overflow: 'hidden',
        }}>
          <div style={{ padding: '14px 16px', borderBottom: '1px solid var(--border)', fontWeight: 700, fontSize: 13, color: 'var(--text-secondary)' }}>
            Conversations ({conversations.length})
          </div>
          <div style={{ flex: 1, overflowY: 'auto', padding: 8 }}>
            {loading ? (
              <div className="loading-center" style={{ padding: 40 }}>
                <div className="spinner spinner-sm" />
              </div>
            ) : conversations.length === 0 ? (
              <div style={{ padding: 20, textAlign: 'center', color: 'var(--text-muted)', fontSize: 13 }}>
                No conversations yet. Start a new chat!
              </div>
            ) : (
              conversations.map(conv => (
                <div
                  key={conv.id}
                  onClick={() => openConversation(conv)}
                  style={{
                    padding: '10px 12px',
                    borderRadius: 'var(--radius-md)',
                    background: activeConv?.id === conv.id ? 'rgba(99,102,241,0.12)' : 'transparent',
                    border: `1px solid ${activeConv?.id === conv.id ? 'rgba(99,102,241,0.3)' : 'transparent'}`,
                    cursor: 'pointer',
                    display: 'flex', alignItems: 'center', gap: 8,
                    marginBottom: 4,
                    transition: 'all 0.15s',
                  }}
                >
                  <MessageCircle size={14} style={{ color: activeConv?.id === conv.id ? 'var(--primary)' : 'var(--text-muted)', flexShrink: 0 }} />
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ fontSize: 13, fontWeight: 600, color: 'var(--text-primary)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                      {conv.title}
                    </div>
                    {conv.messages_count !== undefined && (
                      <div style={{ fontSize: 11, color: 'var(--text-muted)' }}>
                        {conv.messages_count} messages
                      </div>
                    )}
                  </div>
                  <button
                    onClick={(e) => deleteConversation(conv.id, e)}
                    style={{ background: 'none', border: 'none', color: 'var(--text-muted)', padding: 2, display: 'flex', flexShrink: 0 }}
                    title="Delete Conversation"
                  >
                    <Trash2 size={13} />
                  </button>
                </div>
              ))
            )}
          </div>
        </div>

        {/* Chat Area */}
        <div style={{
          flex: 1,
          background: 'var(--bg-card)',
          border: '1px solid var(--border)',
          borderRadius: 'var(--radius-lg)',
          display: 'flex', flexDirection: 'column',
          overflow: 'hidden',
        }}>
          {!activeConv ? (
            // Welcome / Empty state
            <div style={{ flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', padding: 40, textAlign: 'center' }}>
              <div style={{
                width: 72, height: 72, borderRadius: '50%',
                background: 'linear-gradient(135deg, var(--primary), var(--purple))',
                display: 'flex', alignItems: 'center', justifyContent: 'center',
                fontSize: 32, marginBottom: 18,
                boxShadow: '0 0 35px var(--primary-glow)',
              }}>
                🧠
              </div>
              <h2 style={{ marginBottom: 8, color: 'var(--text-primary)' }}>TrigMaster AI Tutor</h2>
              <p style={{ maxWidth: 460, marginBottom: 28, color: 'var(--text-muted)', lineHeight: 1.6 }}>
                Need help understanding a trigonometry proof, formula, or problem? Ask a question below or pick a prompt to start!
              </p>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10, maxWidth: 540, width: '100%', marginBottom: 24 }}>
                {suggestions.map((s, i) => (
                  <button
                    key={i}
                    className="btn btn-secondary"
                    style={{ fontSize: 12, textAlign: 'left', justifyContent: 'flex-start', padding: '10px 14px' }}
                    onClick={async () => {
                      const newConv = await createConversation();
                      if (newConv) {
                        setInput(s);
                      }
                    }}
                  >
                    <Sparkles size={12} style={{ color: 'var(--primary)', flexShrink: 0 }} />
                    {s}
                  </button>
                ))}
              </div>
              <button className="btn btn-primary" onClick={() => createConversation()} disabled={creating}>
                {creating ? <Loader2 size={15} className="spin-icon" /> : <Plus size={15} />}
                Start New Conversation
              </button>
            </div>
          ) : (
            <>
              {/* Chat header */}
              <div style={{ padding: '12px 20px', borderBottom: '1px solid var(--border)', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                  <div style={{
                    width: 32, height: 32, borderRadius: '50%',
                    background: 'linear-gradient(135deg, var(--primary), var(--purple))',
                    display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 16,
                  }}>🧠</div>
                  <div>
                    <div style={{ fontWeight: 700, fontSize: 14, color: 'var(--text-primary)' }}>
                      {activeConv.title}
                    </div>
                    <div style={{ fontSize: 11, color: 'var(--text-muted)' }}>TrigMaster AI Assistant</div>
                  </div>
                </div>

                <button
                  className="btn btn-ghost btn-sm"
                  onClick={handleOpenPracticeModal}
                  style={{ fontSize: 12, display: 'flex', alignItems: 'center', gap: 5 }}
                >
                  <Sparkles size={13} style={{ color: 'var(--primary)' }} />
                  Practice Problem
                </button>
              </div>

              {/* Messages */}
              <div className="chat-messages" style={{ flex: 1 }}>
                {messages.length === 0 && (
                  <div style={{ textAlign: 'center', padding: 24, color: 'var(--text-muted)', fontSize: 13 }}>
                    Ask anything about trigonometry — definitions, proofs, practice problems, or step-by-step explanations!
                  </div>
                )}
                {messages.map(msg => (
                  <div key={msg.id} style={{ display: 'flex', flexDirection: 'column' }}>
                    <div style={{ fontSize: 11, color: 'var(--text-muted)', marginBottom: 4, alignSelf: msg.role === 'user' ? 'flex-end' : 'flex-start', padding: '0 4px' }}>
                      {msg.role === 'user' ? 'You' : '🧠 AI Tutor'}
                    </div>
                    <div className={`chat-bubble chat-bubble-${msg.role}`}>
                      {msg.role === 'assistant' ? (
                        <MarkdownContent content={msg.message} />
                      ) : (
                        msg.message.split('\n').map((line, i) => (
                          <span key={i}>{line}{i < msg.message.split('\n').length - 1 ? <br /> : null}</span>
                        ))
                      )}
                    </div>
                  </div>
                ))}
                {sending && (
                  <div style={{ display: 'flex', alignItems: 'center', gap: 8, color: 'var(--text-muted)', fontSize: 13, padding: 8 }}>
                    <div className="spinner spinner-sm" /> Thinking…
                  </div>
                )}
                <div ref={messagesEndRef} />
              </div>

              {/* Input */}
              <div className="chat-input-area">
                <textarea
                  id="chat-input"
                  className="chat-textarea"
                  placeholder="Ask about any trigonometry concept or formula…"
                  value={input}
                  onChange={e => setInput(e.target.value)}
                  onKeyDown={handleKeyDown}
                  rows={1}
                  disabled={sending}
                />
                <button
                  id="send-message-btn"
                  className="btn btn-primary"
                  onClick={() => sendMessage()}
                  disabled={!input.trim() || sending}
                  style={{ height: 48, padding: '0 16px' }}
                >
                  {sending ? <Loader2 size={16} className="spin-icon" /> : <Send size={16} />}
                </button>
              </div>
            </>
          )}
        </div>
      </div>

      {/* Practice Question Generator Modal */}
      {showPracticeModal && (
        <div style={{
          position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.7)',
          display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 1000,
          padding: 20,
        }}>
          <div className="card" style={{ maxWidth: 480, width: '100%', padding: 24, position: 'relative' }}>
            <button
              onClick={() => setShowPracticeModal(false)}
              style={{
                position: 'absolute', top: 16, right: 16, background: 'none', border: 'none',
                color: 'var(--text-muted)', cursor: 'pointer',
              }}
            >
              <X size={18} />
            </button>

            <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 16 }}>
              <div style={{
                width: 36, height: 36, borderRadius: '50%',
                background: 'rgba(99,102,241,0.15)', color: 'var(--primary)',
                display: 'flex', alignItems: 'center', justifyContent: 'center',
              }}>
                <Sparkles size={18} />
              </div>
              <div>
                <h3 style={{ fontSize: '1.1rem', fontWeight: 600 }}>Generate Practice Problem</h3>
                <p style={{ fontSize: '0.8rem', color: 'var(--text-muted)' }}>
                  The AI Tutor will craft a tailored challenge and guide you through solving it
                </p>
              </div>
            </div>

            <div className="form-group" style={{ marginBottom: 16 }}>
              <label className="form-label">Select Topic / Module</label>
              <select
                className="form-input"
                value={selectedTopicId}
                onChange={e => setSelectedTopicId(Number(e.target.value))}
              >
                {topics.length > 0 ? (
                  topics.map(t => (
                    <option key={t.id} value={t.id}>
                      Week {t.week_number}: {t.title}
                    </option>
                  ))
                ) : (
                  [1, 2, 3, 4, 5, 6, 7, 8].map(w => (
                    <option key={w} value={w}>Week {w} Trigonometry Topic</option>
                  ))
                )}
              </select>
            </div>

            <div className="form-group" style={{ marginBottom: 24 }}>
              <label className="form-label">Difficulty Level</label>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 8 }}>
                {(['easy', 'medium', 'hard'] as const).map(diff => (
                  <button
                    key={diff}
                    type="button"
                    className={`btn ${difficulty === diff ? 'btn-primary' : 'btn-secondary'}`}
                    onClick={() => setDifficulty(diff)}
                    style={{ textTransform: 'capitalize', padding: '8px 12px', fontSize: 13 }}
                  >
                    {diff}
                  </button>
                ))}
              </div>
            </div>

            <button
              className="btn btn-primary btn-full"
              onClick={handleGeneratePractice}
              disabled={generatingPractice}
            >
              {generatingPractice ? (
                <><Loader2 size={15} className="spin-icon" /> Formulating Problem…</>
              ) : (
                'Generate & Start Practice 🚀'
              )}
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
