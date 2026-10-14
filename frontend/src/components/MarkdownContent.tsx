import React from 'react';

interface MarkdownContentProps {
  content: string;
}

export const MarkdownContent: React.FC<MarkdownContentProps> = ({ content }) => {
  if (!content) return null;

  // Split into paragraphs / blocks
  const blocks = content.split(/\n\n+/);

  const renderInline = (text: string): React.ReactNode => {
    // Process inline code `...`
    const parts = text.split(/(`[^`]+`|\*\*[^*]+\*\*)/g);
    return parts.map((part, idx) => {
      if (part.startsWith('`') && part.endsWith('`')) {
        return (
          <code
            key={idx}
            style={{
              background: 'rgba(79, 70, 229, 0.08)',
              color: 'var(--primary)',
              padding: '2px 6px',
              borderRadius: '4px',
              fontSize: '0.92em',
              fontFamily: 'monospace',
              fontWeight: 600,
            }}
          >
            {part.slice(1, -1)}
          </code>
        );
      }
      if (part.startsWith('**') && part.endsWith('**')) {
        return <strong key={idx} style={{ color: 'var(--text-primary)', fontWeight: 600 }}>{part.slice(2, -2)}</strong>;
      }
      return part;
    });
  };

  return (
    <div className="markdown-body" style={{ display: 'flex', flexDirection: 'column', gap: '14px', lineHeight: 1.7, color: 'var(--text-secondary)' }}>
      {blocks.map((block, idx) => {
        const trimmed = block.trim();

        // Level 3 Heading: ### Title
        if (trimmed.startsWith('### ')) {
          return (
            <h3
              key={idx}
              style={{
                fontSize: '1.15rem',
                fontWeight: 700,
                color: 'var(--text-primary)',
                marginTop: '12px',
                marginBottom: '2px',
                paddingBottom: '6px',
                borderBottom: '1px solid var(--border)',
              }}
            >
              {renderInline(trimmed.slice(4))}
            </h3>
          );
        }

        // Level 2 Heading: ## Title
        if (trimmed.startsWith('## ')) {
          return (
            <h2
              key={idx}
              style={{
                fontSize: '1.3rem',
                fontWeight: 700,
                color: 'var(--text-primary)',
                marginTop: '16px',
                marginBottom: '4px',
              }}
            >
              {renderInline(trimmed.slice(3))}
            </h2>
          );
        }

        // Unordered List (- item or * item)
        if (trimmed.startsWith('- ') || trimmed.startsWith('* ')) {
          const items = trimmed.split('\n').filter(l => l.trim().length > 0);
          return (
            <ul key={idx} style={{ paddingLeft: '22px', display: 'flex', flexDirection: 'column', gap: '6px', margin: 0 }}>
              {items.map((item, itemIdx) => (
                <li key={itemIdx} style={{ listStyleType: 'disc' }}>
                  {renderInline(item.replace(/^[-*]\s+/, ''))}
                </li>
              ))}
            </ul>
          );
        }

        // Numbered List (1. item)
        if (/^\d+\.\s+/.test(trimmed)) {
          const items = trimmed.split('\n').filter(l => l.trim().length > 0);
          return (
            <ol key={idx} style={{ paddingLeft: '22px', display: 'flex', flexDirection: 'column', gap: '6px', margin: 0 }}>
              {items.map((item, itemIdx) => (
                <li key={itemIdx} style={{ listStyleType: 'decimal' }}>
                  {renderInline(item.replace(/^\d+\.\s+/, ''))}
                </li>
              ))}
            </ol>
          );
        }

        // Blockquote (> text)
        if (trimmed.startsWith('>')) {
          return (
            <div
              key={idx}
              style={{
                padding: '12px 16px',
                borderLeft: '4px solid var(--primary)',
                background: 'rgba(79, 70, 229, 0.04)',
                borderRadius: '0 var(--radius-sm) var(--radius-sm) 0',
                fontStyle: 'italic',
              }}
            >
              {renderInline(trimmed.replace(/^>\s*/, ''))}
            </div>
          );
        }

        // Regular paragraph with potential multiline text
        return (
          <p key={idx} style={{ margin: 0, fontSize: '0.96rem' }}>
            {trimmed.split('\n').map((line, lIdx, arr) => (
              <React.Fragment key={lIdx}>
                {renderInline(line)}
                {lIdx < arr.length - 1 && <br />}
              </React.Fragment>
            ))}
          </p>
        );
      })}
    </div>
  );
};
