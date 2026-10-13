import { useState, useEffect, useRef } from 'react';
import { aiTutorService } from '../services/aiTutorService';
import { useToast } from '../contexts/ToastContext';
import { getErrorMessage } from '../services/api';
import type { AiConversation, AiMessage } from '../types/api';
import {
  Plus, Trash2, Send, Loader2, MessageCircle,
  Sparkles,
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

  useEffect(() => {
    loadConversations();
  }, []);

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  const loadConversations = async () => {
    try {
      const raw = await aiTutorService.getConversations();
      const list = Array.isArray(raw) ? raw : (raw as any).data ?? [];
      setConversations(list as AiConversation[]);
    } catch (err) {
      toast.error(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  };

  const openConversation = async (conv: AiConversation) => {
    try {
      const full = await aiTutorService.getConversation(conv.id);
      setActiveConv(full);
      setMessages(full.messages ?? []);
    } catch (err) {
      toast.error(getErrorMessage(err));
    }
  };

  const createConversation = async () => {
    setCreating(true);
    try {
      const conv = await aiTutorService.createConversation({ title: 'New Conversation' });
      setConversations(prev => [conv, ...prev]);
      setActiveConv(conv);
      setMessages([]);
    } catch (err) {
      toast.error(getErrorMessage(err));
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

  const sendMessage = async () => {
    if (!input.trim() || !activeConv || sending) return;
    const userMsg = input.trim();
    setInput('');
    setSending(true);
    // Optimistic update
    const tempUser: AiMessage = { id: Date.now(), role: 'user', message: userMsg };
    setMessages(prev => [...prev, tempUser]);
    try {
      const { user_message, assistant_message } = await aiTutorService.sendMessage(activeConv.id, userMsg);
      setMessages(prev => [
        ...prev.filter(m => m.id !== tempUser.id),
        user_message,
        assistant_message,
      ]);
    } catch (err) {
      setMessages(prev => prev.filter(m => m.id !== tempUser.id));
      toast.error(getErrorMessage(err));
      setInput(userMsg); // Restore
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

  const suggestions = [
    'Explain the unit circle to me.',
    'What is sin(30°) and why?',
    'How do I remember the SOHCAHTOA rule?',
    'Give me a practice problem on the Pythagorean identity.',
  ];

  return (
    <div>
      <div className="page-header">
        <div>
          <h1 className="page-title">AI Tutor</h1>
          <p className="page-subtitle">Your personal trigonometry assistant powered by Gemini AI</p>
        </div>
        <button
          id="new-conversation-btn"
          className="btn btn-primary"
          onClick={createConversation}
          disabled={creating}
        >
          {creating ? <Loader2 size={15} /> : <Plus size={15} />}
          New Chat
        </button>
      </div>

      <div style={{ display: 'flex', gap: 20, height: 'calc(100vh - 200px)', minHeight: 500 }}>
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
                width: 80, height: 80, borderRadius: '50%',
                background: 'linear-gradient(135deg, var(--primary), var(--purple))',
                display: 'flex', alignItems: 'center', justifyContent: 'center',
                fontSize: 36, marginBottom: 20,
                boxShadow: '0 0 40px var(--primary-glow)',
              }}>
                🧠
              </div>
              <h2 style={{ marginBottom: 8, color: 'var(--text-primary)' }}>Ask your AI Tutor</h2>
              <p style={{ maxWidth: 400, marginBottom: 28 }}>
                Get instant explanations, practice problems, and personalized help with any trigonometry concept.
              </p>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10, maxWidth: 500, width: '100%' }}>
                {suggestions.map((s, i) => (
                  <button
                    key={i}
                    className="btn btn-secondary"
                    style={{ fontSize: 12, textAlign: 'left', justifyContent: 'flex-start', padding: '10px 14px' }}
                    onClick={async () => {
                      await createConversation();
                      setInput(s);
                    }}
                  >
                    <Sparkles size={12} style={{ color: 'var(--primary)', flexShrink: 0 }} />
                    {s}
                  </button>
                ))}
              </div>
              <button className="btn btn-primary mt-24" onClick={createConversation} disabled={creating}>
                {creating ? <Loader2 size={15} /> : <Plus size={15} />}
                Start New Conversation
              </button>
            </div>
          ) : (
            <>
              {/* Chat header */}
              <div style={{ padding: '12px 20px', borderBottom: '1px solid var(--border)', display: 'flex', alignItems: 'center', gap: 10 }}>
                <div style={{
                  width: 32, height: 32, borderRadius: '50%',
                  background: 'linear-gradient(135deg, var(--primary), var(--purple))',
                  display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 16,
                }}>🧠</div>
                <div>
                  <div style={{ fontWeight: 700, fontSize: 14, color: 'var(--text-primary)' }}>
                    {activeConv.title}
                  </div>
                  <div style={{ fontSize: 11, color: 'var(--text-muted)' }}>TrigMaster AI Tutor</div>
                </div>
              </div>

              {/* Messages */}
              <div className="chat-messages" style={{ flex: 1 }}>
                {messages.length === 0 && (
                  <div style={{ textAlign: 'center', padding: 20, color: 'var(--text-muted)', fontSize: 13 }}>
                    Start the conversation — ask anything about trigonometry!
                  </div>
                )}
                {messages.map(msg => (
                  <div key={msg.id} style={{ display: 'flex', flexDirection: 'column' }}>
                    <div style={{ fontSize: 11, color: 'var(--text-muted)', marginBottom: 4, alignSelf: msg.role === 'user' ? 'flex-end' : 'flex-start', padding: '0 4px' }}>
                      {msg.role === 'user' ? 'You' : '🧠 AI Tutor'}
                    </div>
                    <div className={`chat-bubble chat-bubble-${msg.role}`}>
                      {msg.message.split('\n').map((line, i) => (
                        <span key={i}>{line}{i < msg.message.split('\n').length - 1 ? <br /> : null}</span>
                      ))}
                    </div>
                  </div>
                ))}
                {sending && (
                  <div style={{ display: 'flex', alignItems: 'center', gap: 8, color: 'var(--text-muted)', fontSize: 13 }}>
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
                  placeholder="Ask about any trigonometry concept…"
                  value={input}
                  onChange={e => setInput(e.target.value)}
                  onKeyDown={handleKeyDown}
                  rows={1}
                  disabled={sending}
                />
                <button
                  id="send-message-btn"
                  className="btn btn-primary"
                  onClick={sendMessage}
                  disabled={!input.trim() || sending}
                  style={{ height: 48, padding: '0 16px' }}
                >
                  {sending ? <Loader2 size={16} /> : <Send size={16} />}
                </button>
              </div>
            </>
          )}
        </div>
      </div>
    </div>
  );
}
