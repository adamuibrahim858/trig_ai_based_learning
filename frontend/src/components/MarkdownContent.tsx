import React from 'react';
import ReactMarkdown from 'react-markdown';
import remarkMath from 'remark-math';
import rehypeKatex from 'rehype-katex';
import remarkGfm from 'remark-gfm';

interface MarkdownContentProps {
  content: string;
  className?: string;
}

/**
 * Preprocess math delimiters:
 * Converts LaTeX delimiters \[ ... \] to $$ ... $$
 * and \( ... \) to $ ... $ so remark-math recognizes them.
 */
function preprocessMathDelimiters(text: string): string {
  if (!text) return '';
  return text
    // Replace \[ ... \] with $$ ... $$
    .replace(/\\\[([\s\S]*?)\\\]/g, (_, math) => `$$\n${math.trim()}\n$$`)
    // Replace \( ... \) with $ ... $
    .replace(/\\\(([\s\S]*?)\\\)/g, (_, math) => `$${math.trim()}$`);
}

export const MarkdownContent: React.FC<MarkdownContentProps> = ({ content, className = '' }) => {
  if (!content) return null;

  const processed = preprocessMathDelimiters(content);

  return (
    <div
      className={`markdown-content ${className}`.trim()}
      style={{
        lineHeight: 1.65,
        color: 'inherit',
        wordBreak: 'break-word',
      }}
    >
      <ReactMarkdown
        remarkPlugins={[remarkGfm, remarkMath]}
        rehypePlugins={[rehypeKatex]}
        components={{
          h1: ({ node, ...props }) => (
            <h1
              style={{
                fontSize: '1.4rem',
                fontWeight: 700,
                color: 'var(--text-primary)',
                margin: '14px 0 8px 0',
                paddingBottom: '6px',
                borderBottom: '1px solid var(--border)',
              }}
              {...props}
            />
          ),
          h2: ({ node, ...props }) => (
            <h2
              style={{
                fontSize: '1.2rem',
                fontWeight: 700,
                color: 'var(--text-primary)',
                margin: '12px 0 6px 0',
              }}
              {...props}
            />
          ),
          h3: ({ node, ...props }) => (
            <h3
              style={{
                fontSize: '1.05rem',
                fontWeight: 700,
                color: 'var(--text-primary)',
                margin: '10px 0 4px 0',
              }}
              {...props}
            />
          ),
          h4: ({ node, ...props }) => (
            <h4
              style={{
                fontSize: '0.98rem',
                fontWeight: 600,
                color: 'var(--text-primary)',
                margin: '8px 0 4px 0',
              }}
              {...props}
            />
          ),
          p: ({ node, ...props }) => (
            <p
              style={{
                margin: '0 0 10px 0',
                lineHeight: 1.65,
              }}
              {...props}
            />
          ),
          hr: ({ node, ...props }) => (
            <hr
              style={{
                border: 'none',
                borderTop: '1px solid var(--border)',
                margin: '14px 0',
              }}
              {...props}
            />
          ),
          ul: ({ node, ...props }) => (
            <ul
              style={{
                paddingLeft: '22px',
                margin: '6px 0 10px 0',
                display: 'flex',
                flexDirection: 'column',
                gap: '4px',
              }}
              {...props}
            />
          ),
          ol: ({ node, ...props }) => (
            <ol
              style={{
                paddingLeft: '22px',
                margin: '6px 0 10px 0',
                display: 'flex',
                flexDirection: 'column',
                gap: '4px',
              }}
              {...props}
            />
          ),
          li: ({ node, ...props }) => (
            <li
              style={{
                lineHeight: 1.6,
              }}
              {...props}
            />
          ),
          blockquote: ({ node, ...props }) => (
            <blockquote
              style={{
                borderLeft: '3px solid var(--primary)',
                margin: '8px 0 12px 0',
                padding: '8px 14px',
                background: 'rgba(99, 102, 241, 0.05)',
                borderRadius: '0 8px 8px 0',
                fontStyle: 'italic',
              }}
              {...props}
            />
          ),
          code: ({ node, className: codeClassName, children, ...props }) => {
            const isInline = !codeClassName && !String(children).includes('\n');
            if (isInline) {
              return (
                <code
                  style={{
                    background: 'rgba(99, 102, 241, 0.08)',
                    color: 'var(--primary)',
                    padding: '2px 6px',
                    borderRadius: '4px',
                    fontSize: '0.9em',
                    fontFamily: 'monospace',
                    fontWeight: 600,
                  }}
                  {...props}
                >
                  {children}
                </code>
              );
            }
            return (
              <code
                style={{
                  fontFamily: 'monospace',
                  fontSize: '0.9em',
                }}
                className={codeClassName}
                {...props}
              >
                {children}
              </code>
            );
          },
          pre: ({ node, ...props }) => (
            <pre
              style={{
                background: 'var(--bg-card, #1e1e2e)',
                border: '1px solid var(--border)',
                borderRadius: '8px',
                padding: '12px 14px',
                overflowX: 'auto',
                margin: '10px 0',
              }}
              {...props}
            />
          ),
          table: ({ node, ...props }) => (
            <div style={{ overflowX: 'auto', margin: '12px 0' }}>
              <table
                style={{
                  width: '100%',
                  borderCollapse: 'collapse',
                  fontSize: '0.92rem',
                }}
                {...props}
              />
            </div>
          ),
          th: ({ node, ...props }) => (
            <th
              style={{
                border: '1px solid var(--border)',
                padding: '8px 12px',
                background: 'rgba(99, 102, 241, 0.08)',
                fontWeight: 600,
                textAlign: 'left',
              }}
              {...props}
            />
          ),
          td: ({ node, ...props }) => (
            <td
              style={{
                border: '1px solid var(--border)',
                padding: '8px 12px',
              }}
              {...props}
            />
          ),
          strong: ({ node, ...props }) => (
            <strong
              style={{
                fontWeight: 700,
                color: 'var(--text-primary)',
              }}
              {...props}
            />
          ),
        }}
      >
        {processed}
      </ReactMarkdown>
    </div>
  );
};
