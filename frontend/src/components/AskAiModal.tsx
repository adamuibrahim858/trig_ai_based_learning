import { useState, useRef, useEffect, useCallback } from 'react';
import { aiTutorService } from '../services/aiTutorService';
import { getErrorMessage } from '../services/api';
import { useToast } from '../contexts/ToastContext';
import { MarkdownContent } from './MarkdownContent';
import {
  X, Send, Brain, Loader2, MessageCircle, Trash2,
} from 'lucide-react';

interface Message {
  role: 'user' | 'assistant';
  text: string;
}

interface AskAiModalProps {
  isOpen: boolean;
  onClose: () => void;
  lessonId: number;
  lessonTitle: string;
  topicTitle?: string;
}

export default function AskAiModal({
  isOpen,
  onClose,
  lessonId,
  lessonTitle,
  topicTitle,
}: AskAiModalProps) {
  const toast = useToast();
  const [messages, setMessages] = useState<Message[]>([]);
  const [input, setInput] = useState('');
  const [sending, setSending] = useState(false);
  const [conversationId, setConversationId] = useState<number | null>(null);
  const [initializing, setInitializing] = useState(false);
  const messagesEndRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLTextAreaElement>(null);

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  useEffect(() => {
    scrollToBottom();
  }, [messages]);

  // Focus input when modal opens
  useEffect(() => {
    if (isOpen) {
      setTimeout(() => inputRef.current?.focus(), 200);
    }
  }, [isOpen]);

  // Reset when modal closes
  const handleClose = () => {
    onClose();
  };

  const initConversation = useCallback(async () => {
    if (conversationId) return conversationId;
    setInitializing(true);
    try {
      const conv = await aiTutorService.createConversation({
        lesson_id: lessonId,
        title: `Ask AI: ${lessonTitle}`,
      });
      setConversationId(conv.id);
      return conv.id;
    } catch (err) {
      toast.error(getErrorMessage(err));
      return null;
    } finally {
      setInitializing(false);
    }
  }, [conversationId, lessonId, lessonTitle, toast]);

  const handleSend = async () => {
    const text = input.trim();
    if (!text || sending) return;

    setInput('');
    setMessages(prev => [...prev, { role: 'user', text }]);
    setSending(true);

    try {
      const convId = await initConversation();
      if (!convId) {
        setSending(false);
        return;
      }

      const res = await aiTutorService.sendMessage(convId, text);
      setMessages(prev => [
        ...prev,
        { role: 'assistant', text: res.assistant_message.message },
      ]);
    } catch (err) {
      toast.error(getErrorMessage(err));
      setMessages(prev => [
        ...prev,
        { role: 'assistant', text: 'Sorry, I could not process your request. Please try again.' },
      ]);
    } finally {
      setSending(false);
    }
  };

  const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSend();
    }
  };

  const handleClear = () => {
    setMessages([]);
    setConversationId(null);
  };

  if (!isOpen) return null;

  return (
    <>
      {/* Backdrop */}
      <div
        style={{
          position: 'fixed',
          inset: 0,
          background: 'rgba(0,0,0,0.4)',
          backdropFilter: 'blur(4px)',
          zIndex: 999,
          animation: 'fadeIn 0.2s ease',
        }}
        onClick={handleClose}
      />

      {/* Modal */}
      <div
        style={{
          position: 'fixed',
          bottom: 20,
          right: 20,
          width: 420,
          maxWidth: 'calc(100vw - 40px)',
          height: 560,
          maxHeight: 'calc(100vh - 40px)',
          background: '#ffffff',
          borderRadius: 16,
          boxShadow: '0 20px 60px rgba(0,0,0,0.2), 0 0 0 1px rgba(0,0,0,0.05)',
          display: 'flex',
          flexDirection: 'column',
          zIndex: 1000,
          animation: 'slideUp 0.25s ease',
          overflow: 'hidden',
        }}
      >
        {/* Header */}
        <div
          style={{
            padding: '14px 16px',
            borderBottom: '1px solid var(--border)',
            display: 'flex',
            alignItems: 'center',
            gap: 10,
            background: 'linear-gradient(135deg, rgba(79,70,229,0.06), rgba(34,211,238,0.04))',
            flexShrink: 0,
          }}
        >
          <div
            style={{
              width: 34,
              height: 34,
              borderRadius: 10,
              background: 'linear-gradient(135deg, var(--primary), #7c3aed)',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              flexShrink: 0,
            }}
          >
            <Brain size={18} style={{ color: '#fff' }} />
          </div>
          <div style={{ flex: 1, minWidth: 0 }}>
            <div style={{ fontWeight: 700, fontSize: 14, color: 'var(--text-primary)' }}>
              Ask AI Tutor
            </div>
            <div
              style={{
                fontSize: 11.5,
                color: 'var(--text-muted)',
                overflow: 'hidden',
                textOverflow: 'ellipsis',
                whiteSpace: 'nowrap',
              }}
            >
              {topicTitle ? `${topicTitle} · ` : ''}{lessonTitle}
            </div>
          </div>
          <div style={{ display: 'flex', gap: 4 }}>
            {messages.length > 0 && (
              <button
                type="button"
                onClick={handleClear}
                title="Clear chat"
                style={{
                  background: 'none',
                  border: 'none',
                  cursor: 'pointer',
                  padding: 6,
                  borderRadius: 8,
                  color: 'var(--text-muted)',
                  display: 'flex',
                }}
              >
                <Trash2 size={15} />
              </button>
            )}
            <button
              type="button"
              onClick={handleClose}
              title="Close"
              style={{
                background: 'none',
                border: 'none',
                cursor: 'pointer',
                padding: 6,
                borderRadius: 8,
                color: 'var(--text-muted)',
                display: 'flex',
              }}
            >
              <X size={17} />
            </button>
          </div>
        </div>

        {/* Messages Area */}
        <div
          style={{
            flex: 1,
            overflowY: 'auto',
            padding: 16,
            display: 'flex',
            flexDirection: 'column',
            gap: 12,
          }}
        >
          {messages.length === 0 && (
            <div
              style={{
                flex: 1,
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                justifyContent: 'center',
                gap: 12,
                padding: 24,
                color: 'var(--text-muted)',
              }}
            >
              <MessageCircle size={36} style={{ opacity: 0.3 }} />
              <div style={{ textAlign: 'center' }}>
                <div style={{ fontWeight: 600, fontSize: 14, color: 'var(--text-secondary)', marginBottom: 6 }}>
                  Ask me anything!
                </div>
                <div style={{ fontSize: 12.5, lineHeight: 1.6 }}>
                  I can explain concepts, solve problems, and quiz you on this lesson.
                </div>
              </div>
              <div
                style={{
                  display: 'flex',
                  flexWrap: 'wrap',
                  gap: 6,
                  justifyContent: 'center',
                  marginTop: 4,
                }}
              >
                {[
                  'Explain this topic simply',
                  'Give me a practice question',
                  'Summarize the key points',
                ].map(suggestion => (
                  <button
                    key={suggestion}
                    type="button"
                    onClick={() => {
                      setInput(suggestion);
                      inputRef.current?.focus();
                    }}
                    style={{
                      padding: '6px 12px',
                      borderRadius: 999,
                      border: '1px solid var(--border)',
                      background: 'var(--bg-elevated)',
                      color: 'var(--text-secondary)',
                      fontSize: 11.5,
                      cursor: 'pointer',
                      transition: 'all 0.15s',
                    }}
                  >
                    {suggestion}
                  </button>
                ))}
              </div>
            </div>
          )}

          {messages.map((msg, i) => (
            <div
              key={i}
              style={{
                display: 'flex',
                justifyContent: msg.role === 'user' ? 'flex-end' : 'flex-start',
              }}
            >
              <div
                style={{
                  maxWidth: '85%',
                  padding: '10px 14px',
                  borderRadius: msg.role === 'user' ? '14px 14px 4px 14px' : '14px 14px 14px 4px',
                  background: msg.role === 'user'
                    ? 'linear-gradient(135deg, var(--primary), #7c3aed)'
                    : 'var(--bg-elevated)',
                  color: msg.role === 'user' ? '#fff' : 'var(--text-primary)',
                  fontSize: 13.5,
                  lineHeight: 1.6,
                  wordBreak: 'break-word',
                }}
              >
                {msg.role === 'assistant' ? (
                  <MarkdownContent content={msg.text} />
                ) : (
                  msg.text
                )}
              </div>
            </div>
          ))}

          {(sending || initializing) && (
            <div style={{ display: 'flex', justifyContent: 'flex-start' }}>
              <div
                style={{
                  padding: '10px 14px',
                  borderRadius: '14px 14px 14px 4px',
                  background: 'var(--bg-elevated)',
                  display: 'flex',
                  alignItems: 'center',
                  gap: 8,
                  color: 'var(--text-muted)',
                  fontSize: 13,
                }}
              >
                <Loader2 size={14} className="spin-icon" />
                Thinking…
              </div>
            </div>
          )}

          <div ref={messagesEndRef} />
        </div>

        {/* Input Area */}
        <div
          style={{
            padding: '10px 14px',
            borderTop: '1px solid var(--border)',
            background: '#fafafa',
            display: 'flex',
            gap: 8,
            alignItems: 'flex-end',
            flexShrink: 0,
          }}
        >
          <textarea
            ref={inputRef}
            value={input}
            onChange={e => setInput(e.target.value)}
            onKeyDown={handleKeyDown}
            placeholder="Ask a question about this lesson…"
            rows={1}
            style={{
              flex: 1,
              resize: 'none',
              border: '1px solid var(--border)',
              borderRadius: 12,
              padding: '10px 14px',
              fontSize: 13.5,
              fontFamily: 'inherit',
              background: '#fff',
              color: 'var(--text-primary)',
              outline: 'none',
              maxHeight: 100,
              lineHeight: 1.5,
            }}
            onInput={e => {
              const target = e.currentTarget;
              target.style.height = 'auto';
              target.style.height = Math.min(target.scrollHeight, 100) + 'px';
            }}
          />
          <button
            type="button"
            onClick={handleSend}
            disabled={!input.trim() || sending}
            style={{
              width: 38,
              height: 38,
              borderRadius: 12,
              border: 'none',
              background: input.trim() && !sending
                ? 'linear-gradient(135deg, var(--primary), #7c3aed)'
                : 'var(--bg-elevated)',
              color: input.trim() && !sending ? '#fff' : 'var(--text-muted)',
              cursor: input.trim() && !sending ? 'pointer' : 'default',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              flexShrink: 0,
              transition: 'all 0.15s',
            }}
          >
            <Send size={16} />
          </button>
        </div>
      </div>

      <style>{`
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
      `}</style>
    </>
  );
}
