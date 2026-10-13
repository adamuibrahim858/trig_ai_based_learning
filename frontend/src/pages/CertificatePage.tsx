import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { certificateService } from '../services/certificateService';
import { getErrorMessage } from '../services/api';
import type { Certificate, CertificateEligibility, PublicVerificationResult } from '../types/api';
import { useAuth } from '../contexts/AuthContext';
import { useToast } from '../contexts/ToastContext';
import {
  Award, Download, CheckCircle, AlertCircle, Copy, Search,
  ArrowRight, ShieldCheck, RefreshCw, Sparkles
} from 'lucide-react';

export default function CertificatePage() {
  const navigate = useNavigate();
  const { user } = useAuth();
  const toast = useToast();

  const [loading, setLoading] = useState(true);
  const [eligibility, setEligibility] = useState<CertificateEligibility | null>(null);
  const [certificates, setCertificates] = useState<Certificate[]>([]);
  const [issuing, setIssuing] = useState(false);

  // Verification lookup tool state
  const [verifyCode, setVerifyCode] = useState('');
  const [verifying, setVerifying] = useState(false);
  const [verifyResult, setVerifyResult] = useState<PublicVerificationResult | null>(null);
  const [verifyError, setVerifyError] = useState<string | null>(null);

  const fetchData = async () => {
    try {
      setLoading(true);
      const [eligRes, certsRes] = await Promise.allSettled([
        certificateService.getEligibility(),
        certificateService.getCertificates(),
      ]);

      if (eligRes.status === 'fulfilled') {
        setEligibility(eligRes.value);
      }
      if (certsRes.status === 'fulfilled') {
        setCertificates(certsRes.value);
      }
    } catch {
      toast.error('Failed to load certificate data');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, []);

  const handleIssueCertificate = async () => {
    try {
      setIssuing(true);
      const cert = await certificateService.issueCertificate();
      toast.success('Congratulations! Your official certificate has been issued!');
      setCertificates(prev => [cert, ...prev]);
      setEligibility(prev => prev ? { ...prev, eligible: false } : null);
    } catch (err: unknown) {
      toast.error(getErrorMessage(err));
    } finally {
      setIssuing(false);
    }
  };

  const handleDownload = async (cert: Certificate) => {
    try {
      toast.info('Generating PDF download...');
      await certificateService.downloadCertificate(cert.id, cert.certificate_number);
      toast.success('Certificate downloaded successfully');
    } catch {
      toast.error('Could not download certificate PDF');
    }
  };

  const handleCopyCode = (code: string) => {
    navigator.clipboard.writeText(code);
    toast.success('Certificate code copied to clipboard');
  };

  const handleVerifyLookup = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!verifyCode.trim()) return;

    try {
      setVerifying(true);
      setVerifyError(null);
      setVerifyResult(null);
      const result = await certificateService.verifyCertificate(verifyCode.trim());
      setVerifyResult(result);
    } catch (err: unknown) {
      setVerifyError(getErrorMessage(err));
    } finally {
      setVerifying(false);
    }
  };

  if (loading) {
    return (
      <div className="page-container">
        <div className="loading-center">
          <div className="spinner" id="certificate-spinner" />
        </div>
      </div>
    );
  }

  const primaryCert = certificates[0];

  return (
    <div className="page-container" id="certificate-page">
      {/* Header */}
      <div className="page-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
        <div>
          <h1 className="page-title" id="certificate-title" style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
            <Award size={30} style={{ color: 'var(--accent)' }} />
            Course Certificate of Completion
          </h1>
          <p className="page-subtitle">
            Demonstrate your mastery of trigonometry with an officially verifiable credential
          </p>
        </div>
        <button
          className="btn btn-secondary"
          onClick={fetchData}
          id="refresh-certificate-btn"
          style={{ display: 'flex', alignItems: 'center', gap: 6 }}
        >
          <RefreshCw size={15} /> Refresh
        </button>
      </div>

      {/* ISSUED CERTIFICATE DISPLAY */}
      {primaryCert ? (
        <div style={{ marginBottom: 40 }} id="certificate-display-section">
          <div className="certificate-card" id={`cert-card-${primaryCert.id}`}>
            <div className="certificate-seal">🏆</div>

            <div style={{ textTransform: 'uppercase', letterSpacing: '0.15em', fontSize: '0.85rem', color: 'var(--primary)', fontWeight: 700, marginBottom: 8 }}>
              Certificate of Academic Achievement
            </div>

            <h2 style={{ fontSize: '2rem', fontFamily: 'Space Grotesk, sans-serif', fontWeight: 700, color: '#fff', marginBottom: 6 }}>
              {user?.name || 'Valued Student'}
            </h2>

            <p style={{ color: 'var(--text-muted)', fontSize: '0.95rem', maxWidth: 500, margin: '0 auto 20px', lineHeight: 1.6 }}>
              has successfully completed all 8 curriculum modules and passed the comprehensive examination in
            </p>

            <h3 style={{ fontSize: '1.4rem', color: 'var(--accent)', fontWeight: 600, marginBottom: 20 }}>
              {primaryCert.course_title || 'AI-Powered Trigonometry Masterclass'}
            </h3>

            {/* Score & Serial */}
            <div
              style={{
                display: 'inline-flex',
                gap: 24,
                padding: '12px 28px',
                background: 'rgba(255, 255, 255, 0.04)',
                borderRadius: 'var(--radius-lg)',
                border: '1px solid rgba(255, 255, 255, 0.08)',
                marginBottom: 28,
              }}
            >
              <div>
                <div style={{ fontSize: '0.75rem', textTransform: 'uppercase', color: 'var(--text-muted)' }}>Final Score</div>
                <div style={{ fontSize: '1.25rem', fontWeight: 700, color: 'var(--success)' }}>{primaryCert.final_score}%</div>
              </div>
              <div style={{ width: 1, background: 'rgba(255, 255, 255, 0.1)' }} />
              <div>
                <div style={{ fontSize: '0.75rem', textTransform: 'uppercase', color: 'var(--text-muted)' }}>Date Issued</div>
                <div style={{ fontSize: '1.25rem', fontWeight: 600 }}>{new Date(primaryCert.issued_at).toLocaleDateString()}</div>
              </div>
              <div style={{ width: 1, background: 'rgba(255, 255, 255, 0.1)' }} />
              <div>
                <div style={{ fontSize: '0.75rem', textTransform: 'uppercase', color: 'var(--text-muted)' }}>Certificate ID</div>
                <div style={{ fontSize: '1.25rem', fontFamily: 'monospace', color: 'var(--primary)' }}>{primaryCert.certificate_number}</div>
              </div>
            </div>

            {/* Action buttons */}
            <div style={{ display: 'flex', gap: 14, justifyContent: 'center', flexWrap: 'wrap' }}>
              <button
                className="btn btn-primary"
                onClick={() => handleDownload(primaryCert)}
                id="download-cert-btn"
                style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '12px 24px' }}
              >
                <Download size={18} />
                Download PDF Certificate
              </button>

              <button
                className="btn btn-secondary"
                onClick={() => handleCopyCode(primaryCert.certificate_number)}
                id="copy-cert-code-btn"
                style={{ display: 'flex', alignItems: 'center', gap: 8 }}
              >
                <Copy size={16} />
                Copy ID
              </button>
            </div>
          </div>
        </div>
      ) : (
        /* NOT ISSUED YET: ELIGIBILITY VIEW */
        <div className="card" style={{ padding: 32, marginBottom: 36, textAlign: 'center' }} id="eligibility-status-card">
          <div
            style={{
              width: 56,
              height: 56,
              borderRadius: '50%',
              background: eligibility?.eligible ? 'rgba(16,185,129,0.15)' : 'rgba(245,158,11,0.15)',
              color: eligibility?.eligible ? 'var(--success)' : 'var(--accent)',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              margin: '0 auto 16px',
            }}
          >
            {eligibility?.eligible ? <Sparkles size={28} /> : <AlertCircle size={28} />}
          </div>

          <h2 style={{ fontSize: '1.4rem', fontWeight: 600, marginBottom: 8 }}>
            {eligibility?.eligible ? 'You Qualify for Your Certificate!' : 'Certificate Locked'}
          </h2>

          <p style={{ color: 'var(--text-muted)', maxWidth: 540, margin: '0 auto 24px', lineHeight: 1.6 }}>
            {eligibility?.eligible
              ? `You've mastered the trigonometry curriculum and successfully passed the final assessment with ${eligibility.final_score ?? 80}%. You can claim your credential now.`
              : (eligibility?.reason || 'To qualify for the course certificate, complete all 8 weeks and attain at least 70% on the final exam.')}
          </p>

          {eligibility?.eligible ? (
            <button
              className="btn btn-primary"
              onClick={handleIssueCertificate}
              disabled={issuing}
              id="claim-certificate-btn"
              style={{ padding: '14px 28px', fontSize: '1.05rem', fontWeight: 600 }}
            >
              {issuing ? 'Generating Certificate...' : 'Issue My Certificate Now 🎓'}
            </button>
          ) : (
            <div style={{ display: 'flex', gap: 12, justifyContent: 'center' }}>
              <button
                className="btn btn-primary"
                onClick={() => navigate('/final-exam')}
                id="goto-final-exam-btn"
              >
                Go to Final Exam <ArrowRight size={16} />
              </button>
              <button
                className="btn btn-secondary"
                onClick={() => navigate('/curriculum')}
                id="goto-curriculum-cert-btn"
              >
                View Curriculum
              </button>
            </div>
          )}
        </div>
      )}

      {/* PUBLIC VERIFICATION VERIFIER CARD */}
      <div className="card" style={{ padding: 28 }} id="verify-lookup-card">
        <h3 style={{ fontSize: '1.15rem', fontWeight: 600, marginBottom: 6, display: 'flex', alignItems: 'center', gap: 8 }}>
          <ShieldCheck size={20} style={{ color: 'var(--primary)' }} />
          Public Credential Verification
        </h3>
        <p style={{ fontSize: '0.9rem', color: 'var(--text-muted)', marginBottom: 20 }}>
          Verify the authenticity of any certificate issued by TrigMaster by entering its unique certificate code.
        </p>

        <form onSubmit={handleVerifyLookup} style={{ display: 'flex', gap: 12, marginBottom: 20 }} id="verify-form">
          <input
            type="text"
            className="form-input"
            placeholder="e.g. TRIG-2026-ABCD-1234"
            value={verifyCode}
            onChange={e => setVerifyCode(e.target.value)}
            style={{ flex: 1 }}
            id="verify-code-input"
          />
          <button
            type="submit"
            className="btn btn-primary"
            disabled={verifying || !verifyCode.trim()}
            id="verify-code-submit-btn"
            style={{ display: 'flex', alignItems: 'center', gap: 6 }}
          >
            <Search size={16} />
            {verifying ? 'Checking...' : 'Verify'}
          </button>
        </form>

        {verifyError && (
          <div
            style={{
              padding: '12px 16px',
              borderRadius: 'var(--radius-md)',
              background: 'rgba(239,68,68,0.1)',
              border: '1px solid rgba(239,68,68,0.3)',
              color: 'var(--danger)',
              fontSize: '0.9rem',
            }}
            id="verify-error-msg"
          >
            {verifyError}
          </div>
        )}

        {verifyResult && (
          <div
            style={{
              padding: 20,
              borderRadius: 'var(--radius-md)',
              background: 'rgba(16,185,129,0.08)',
              border: '1px solid rgba(16,185,129,0.3)',
            }}
            id="verify-result-box"
          >
            <div style={{ display: 'flex', alignItems: 'center', gap: 8, color: 'var(--success)', fontWeight: 600, marginBottom: 12 }}>
              <CheckCircle size={20} />
              Certificate Verified Authentic
            </div>
            <div className="grid-2" style={{ gap: 12, fontSize: '0.9rem' }}>
              <div><strong>Recipient:</strong> {verifyResult.student_name}</div>
              <div><strong>Course:</strong> {verifyResult.course_title}</div>
              <div><strong>Final Score:</strong> {verifyResult.final_score}%</div>
              <div><strong>Issued Date:</strong> {new Date(verifyResult.issued_at).toLocaleDateString()}</div>
              <div><strong>Serial:</strong> <span style={{ fontFamily: 'monospace' }}>{verifyResult.certificate_number}</span></div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
