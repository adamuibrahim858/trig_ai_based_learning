import { apiClient } from './api';
import type {
  ApiResponse,
  Certificate,
  CertificateEligibility,
  PublicVerificationResult,
} from '../types/api';

export const certificateService = {
  async getEligibility(courseId?: number): Promise<CertificateEligibility> {
    const params = courseId ? { course_id: courseId } : {};
    const res = await apiClient.get<ApiResponse<CertificateEligibility>>('/certificates/eligibility', { params });
    return res.data.data;
  },

  async getCertificates(): Promise<Certificate[]> {
    const res = await apiClient.get<ApiResponse<Certificate[]>>('/certificates');
    return res.data.data;
  },

  async issueCertificate(courseId?: number): Promise<Certificate> {
    const payload = courseId ? { course_id: courseId } : {};
    const res = await apiClient.post<ApiResponse<Certificate>>('/certificates', payload);
    return res.data.data;
  },

  async downloadCertificate(certificateId: number, certificateNumber: string): Promise<void> {
    const response = await apiClient.get(`/certificates/${certificateId}/download`, {
      responseType: 'blob',
    });
    const blob = new Blob([response.data], { type: 'application/pdf' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `Certificate-${certificateNumber}.pdf`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
  },

  async verifyCertificate(certificateNumber: string): Promise<PublicVerificationResult> {
    const res = await apiClient.get<ApiResponse<PublicVerificationResult>>(
      `/certificates/verify/${encodeURIComponent(certificateNumber.trim())}`
    );
    return res.data.data;
  },
};
